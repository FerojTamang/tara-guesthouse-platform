<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Requests\GuestBookingRequest;
use App\Services\BookingAccessToken;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmitter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingSubmissionWorkflowTest extends TestCase
{
    public function testItReturnsThePendingBookingAndScopedToken(): void
    {
        $submitter = new WorkflowSubmitterFake();
        $result = (new BookingSubmissionWorkflow(
            $submitter,
            new BookingAccessToken(str_repeat('k', 32)),
        ))->submit(self::request());

        self::assertSame($submitter->booking, $result->booking());
        self::assertMatchesRegularExpression(
            '/^v1\.[A-Za-z0-9_-]{43}$/D',
            $result->bookingAccessToken(),
        );
    }

    public function testDomainFailuresArePreserved(): void
    {
        $submitter = new WorkflowSubmitterFake();
        $submitter->failure = BookingException::roomUnavailable();

        try {
            (new BookingSubmissionWorkflow(
                $submitter,
                new BookingAccessToken(str_repeat('k', 32)),
            ))->submit(self::request());
            self::fail('Expected a booking conflict.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
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
}

final class WorkflowSubmitterFake implements BookingSubmitter
{
    public Booking $booking;

    public ?BookingException $failure = null;

    public function __construct()
    {
        $createdAt = new DateTimeImmutable('2026-08-01 12:00:00 UTC');
        $this->booking = new Booking(
            99,
            'TGH-2026-REFERENCE00000000001',
            null,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 UTC'),
            new DateTimeImmutable('2026-08-12 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            '9000.00',
            '9000.00',
            Booking::STATUS_PENDING,
            null,
            $createdAt,
            'booking-policy-v1',
            new DateTimeImmutable('2026-08-01 18:00:00 UTC'),
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

    public function submit(GuestBookingRequest $request): Booking
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->booking;
    }
}
