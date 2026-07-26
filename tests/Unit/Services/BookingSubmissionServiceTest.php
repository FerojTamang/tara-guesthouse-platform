<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Requests\GuestBookingRequest;
use App\Services\BookingCreator;
use App\Services\BookingDeadlinePolicy;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingSubmissionService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingSubmissionServiceTest extends TestCase
{
    public function testItSuppliesOneSecureReferenceAndConfiguredDeadline(): void
    {
        $creator = new BookingSubmissionCreatorFake();
        $generator = new BookingReferenceGenerator(
            static fn (int $length): string => str_repeat("\x01", $length),
        );
        $service = new BookingSubmissionService(
            $creator,
            $generator,
            new BookingDeadlinePolicy(360),
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );

        $booking = $service->submit(self::request());

        self::assertSame(1, $creator->calls);
        self::assertSame($booking->bookingReference(), $creator->references[0]);
        self::assertMatchesRegularExpression(
            '/^TGH-2026-[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{20}$/D',
            $booking->bookingReference(),
        );
        self::assertSame(
            '2026-08-01 18:00:00',
            $booking->confirmationDeadlineAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testDuplicateReferenceIsRetriedWithFreshEntropy(): void
    {
        $creator = new BookingSubmissionCreatorFake();
        $creator->duplicateFailuresRemaining = 2;
        $counter = 0;
        $generator = new BookingReferenceGenerator(
            static function (int $length) use (&$counter): string {
                $counter++;

                return str_repeat(chr($counter), $length);
            },
        );
        $service = new BookingSubmissionService(
            $creator,
            $generator,
            new BookingDeadlinePolicy(60),
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );

        $booking = $service->submit(self::request());

        self::assertSame(3, $creator->calls);
        self::assertCount(3, array_unique($creator->references));
        self::assertSame($creator->references[2], $booking->bookingReference());
        self::assertSame(
            ['2026-08-01 13:00:00', '2026-08-01 13:00:00', '2026-08-01 13:00:00'],
            $creator->deadlines,
        );
    }

    public function testReferenceCollisionExhaustionBecomesGenericFailure(): void
    {
        $creator = new BookingSubmissionCreatorFake();
        $creator->duplicateFailuresRemaining = 10;
        $service = new BookingSubmissionService(
            $creator,
            new BookingReferenceGenerator(
                static fn (int $length): string => str_repeat("\x01", $length),
            ),
            new BookingDeadlinePolicy(60),
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );

        try {
            $service->submit(self::request());
            self::fail('Expected booking reference retries to be exhausted.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
            self::assertSame(5, $creator->calls);
        }
    }

    public function testNonDuplicateDomainFailuresAreNotRetried(): void
    {
        $creator = new BookingSubmissionCreatorFake();
        $creator->failure = BookingException::roomUnavailable();
        $service = new BookingSubmissionService(
            $creator,
            new BookingReferenceGenerator(
                static fn (int $length): string => str_repeat("\x01", $length),
            ),
            new BookingDeadlinePolicy(60),
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );

        try {
            $service->submit(self::request());
            self::fail('Expected an unavailable-room failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame(1, $creator->calls);
        }
    }

    public function testInvalidClockFailsBeforeCreation(): void
    {
        $creator = new BookingSubmissionCreatorFake();
        $service = new BookingSubmissionService(
            $creator,
            new BookingReferenceGenerator(),
            new BookingDeadlinePolicy(60),
            static fn (): string => 'not-a-date',
        );

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Booking operation failed.');

        try {
            $service->submit(self::request());
        } finally {
            self::assertSame(0, $creator->calls);
        }
    }

    private static function request(): GuestBookingRequest
    {
        return GuestBookingRequest::fromInput(
            12,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            '2026-08-10',
            '2026-08-12',
            2,
            0,
            null,
            true,
            'booking-policy-v1',
        );
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}

final class BookingSubmissionCreatorFake implements BookingCreator
{
    public int $calls = 0;

    public int $duplicateFailuresRemaining = 0;

    public ?BookingException $failure = null;

    /** @var list<string> */
    public array $references = [];

    /** @var list<string> */
    public array $deadlines = [];

    public function createPending(
        GuestBookingRequest $request,
        string $bookingReference,
        DateTimeImmutable $confirmationDeadlineAt,
    ): Booking {
        $this->calls++;
        $this->references[] = $bookingReference;
        $this->deadlines[] = $confirmationDeadlineAt->format('Y-m-d H:i:s');

        if ($this->duplicateFailuresRemaining > 0) {
            $this->duplicateFailuresRemaining--;

            throw BookingException::duplicateReference();
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $createdAt = new DateTimeImmutable('2026-08-01 12:00:00 UTC');

        return new Booking(
            99,
            $bookingReference,
            null,
            $request->guestFullName(),
            $request->guestEmail(),
            $request->guestPhone(),
            $request->stayPeriod()->checkIn(),
            $request->stayPeriod()->checkOut(),
            $request->adults(),
            $request->children(),
            Booking::CURRENCY_NPR,
            '9000.00',
            '9000.00',
            Booking::STATUS_PENDING,
            $request->specialRequest(),
            $createdAt,
            $request->policyVersion(),
            $confirmationDeadlineAt,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
            null,
        );
    }
}
