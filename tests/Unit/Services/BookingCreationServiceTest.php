<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingQuote;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayPeriod;
use App\Repositories\AvailabilityRepository;
use App\Repositories\BookingWriteRepository;
use App\Repositories\RoomCatalogRepository;
use App\Requests\GuestBookingRequest;
use App\Services\BookingCreationService;
use App\Services\BookingService;
use App\Services\BookingTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BookingCreationServiceTest extends TestCase
{
    public function testItLocksThenRechecksThenWritesAndCommits(): void
    {
        $events = [];
        $room = self::room();
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())
            ->method('lockCurrentById')
            ->with(12)
            ->willReturnCallback(static function () use (&$events, $room): Room {
                $events[] = 'lock';

                return $room;
            });
        $rooms->expects(self::once())
            ->method('findById')
            ->with(12)
            ->willReturnCallback(static function () use (&$events, $room): Room {
                $events[] = 'find';

                return $room;
            });

        $availability = new BookingCreationAvailabilityFake($events);
        $writer = new BookingCreationWriterFake($events);
        $transaction = new BookingCreationTransactionFake($events);
        $checkedAt = self::timestamp('2026-08-01 12:00:00');
        $service = new BookingCreationService(
            $rooms,
            new BookingService(
                $rooms,
                $availability,
                static function () use (&$events, $checkedAt): DateTimeImmutable {
                    $events[] = 'clock';

                    return $checkedAt;
                },
            ),
            $writer,
            $transaction,
        );

        $booking = $service->createPending(
            self::request(),
            'TGH-CREATE-0001',
            self::timestamp('2026-08-01 18:00:00'),
        );

        self::assertSame(
            ['state', 'begin', 'lock', 'clock', 'find', 'overlap', 'write', 'commit'],
            $events,
        );
        self::assertSame('TGH-CREATE-0001', $booking->bookingReference());
        self::assertSame(Booking::STATUS_PENDING, $booking->bookingStatus());
        self::assertFalse($transaction->active);
        self::assertNotNull($writer->quote);
        self::assertSame($checkedAt, $writer->quote->checkedAt());
    }

    public function testAFinalOverlapConflictRollsBackWithoutWriting(): void
    {
        $events = [];
        $room = self::room();
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('lockCurrentById')->willReturn($room);
        $rooms->method('findById')->willReturn($room);
        $availability = new BookingCreationAvailabilityFake($events);
        $availability->blocking = true;
        $writer = new BookingCreationWriterFake($events);
        $transaction = new BookingCreationTransactionFake($events);
        $service = self::service($rooms, $availability, $writer, $transaction);

        try {
            $service->createPending(
                self::request(),
                'TGH-CONFLICT-0001',
                self::timestamp('2026-08-01 18:00:00'),
            );
            self::fail('Expected a final overlap conflict.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame(
                'The selected room is not available for this stay.',
                $exception->getMessage(),
            );
        }

        self::assertNull($writer->quote);
        self::assertFalse($transaction->active);
        self::assertContains('rollback', $events);
        self::assertNotContains('commit', $events);
    }

    public function testMissingLockedRoomRollsBackBeforePreparation(): void
    {
        $events = [];
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('lockCurrentById')->with(12)->willReturn(null);
        $rooms->expects(self::never())->method('findById');
        $availability = new BookingCreationAvailabilityFake($events);
        $writer = new BookingCreationWriterFake($events);
        $transaction = new BookingCreationTransactionFake($events);
        $service = self::service($rooms, $availability, $writer, $transaction);

        try {
            $service->createPending(
                self::request(),
                'TGH-MISSING-0001',
                self::timestamp('2026-08-01 18:00:00'),
            );
            self::fail('Expected a missing room failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isNotFound());
        }

        self::assertSame(0, $availability->overlapCalls);
        self::assertNull($writer->quote);
        self::assertFalse($transaction->active);
    }

    public function testDuplicateReferenceIsPreservedAfterRollback(): void
    {
        $events = [];
        $room = self::room();
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('lockCurrentById')->willReturn($room);
        $rooms->method('findById')->willReturn($room);
        $availability = new BookingCreationAvailabilityFake($events);
        $writer = new BookingCreationWriterFake($events);
        $writer->failure = BookingException::duplicateReference();
        $transaction = new BookingCreationTransactionFake($events);
        $service = self::service($rooms, $availability, $writer, $transaction);

        try {
            $service->createPending(
                self::request(),
                'TGH-DUPLICATE-0001',
                self::timestamp('2026-08-01 18:00:00'),
            );
            self::fail('Expected a duplicate-reference failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isDuplicateReference());
            self::assertSame(
                'The booking reference is already in use.',
                $exception->getMessage(),
            );
        }

        self::assertFalse($transaction->active);
        self::assertContains('rollback', $events);
    }

    public function testExistingTransactionFailsBeforeBeginningAnotherOne(): void
    {
        $events = [];
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::never())->method('lockCurrentById');
        $transaction = new BookingCreationTransactionFake($events);
        $transaction->active = true;
        $service = self::service(
            $rooms,
            new BookingCreationAvailabilityFake($events),
            new BookingCreationWriterFake($events),
            $transaction,
        );

        try {
            $service->createPending(
                self::request(),
                'TGH-NESTED-0001',
                self::timestamp('2026-08-01 18:00:00'),
            );
            self::fail('Expected an existing-transaction failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
        }

        self::assertSame(['state'], $events);
        self::assertTrue($transaction->active);
    }

    public function testRollbackFailureBecomesGenericSystemFailure(): void
    {
        $events = [];
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('lockCurrentById')->willReturn(null);
        $transaction = new BookingCreationTransactionFake($events);
        $transaction->rollbackFailure = new RuntimeException('private rollback details');
        $service = self::service(
            $rooms,
            new BookingCreationAvailabilityFake($events),
            new BookingCreationWriterFake($events),
            $transaction,
        );

        try {
            $service->createPending(
                self::request(),
                'TGH-ROLLBACK-0001',
                self::timestamp('2026-08-01 18:00:00'),
            );
            self::fail('Expected a rollback failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }
    }

    private static function service(
        RoomCatalogRepository $rooms,
        AvailabilityRepository $availability,
        BookingWriteRepository $writer,
        BookingTransaction $transaction,
    ): BookingCreationService {
        return new BookingCreationService(
            $rooms,
            new BookingService(
                $rooms,
                $availability,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            $writer,
            $transaction,
        );
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
            'Late arrival.',
            true,
            'booking-policy-v1',
        );
    }

    private static function room(): Room
    {
        $timestamp = self::timestamp('2026-07-01 10:00:00');
        $type = new RoomType(
            1,
            'Deluxe',
            'deluxe',
            null,
            '4500.00',
            2,
            1,
            true,
            $timestamp,
            $timestamp,
        );

        return new Room(
            12,
            $type,
            'D-12',
            'Deluxe Room',
            'deluxe-room-12',
            null,
            '4500.00',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
            $timestamp,
            $timestamp,
            null,
        );
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}

final class BookingCreationAvailabilityFake implements AvailabilityRepository
{
    public bool $blocking = false;

    public int $overlapCalls = 0;

    /** @param list<string> $events */
    public function __construct(private array &$events)
    {
    }

    public function findAvailableRoomIds(
        StayPeriod $stayPeriod,
        int $adults,
        int $children,
        DateTimeImmutable $asOf,
    ): array {
        return [];
    }

    public function hasBlockingOverlap(
        int $roomId,
        StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): bool {
        $this->events[] = 'overlap';
        $this->overlapCalls++;

        return $this->blocking;
    }

    public function findBlockingStayPeriods(
        int $roomId,
        StayPeriod $window,
        DateTimeImmutable $asOf,
    ): array {
        return [];
    }
}

final class BookingCreationWriterFake implements BookingWriteRepository
{
    public ?BookingQuote $quote = null;

    public ?BookingException $failure = null;

    /** @param list<string> $events */
    public function __construct(private array &$events)
    {
    }

    public function createPending(
        BookingQuote $quote,
        string $bookingReference,
        DateTimeImmutable $confirmationDeadlineAt,
    ): Booking {
        $this->events[] = 'write';
        $this->quote = $quote;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $request = $quote->request();

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
            $quote->currency(),
            $quote->roomAmount(),
            $quote->totalAmount(),
            Booking::STATUS_PENDING,
            $request->specialRequest(),
            $quote->checkedAt(),
            $request->policyVersion(),
            $confirmationDeadlineAt,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $quote->checkedAt(),
            $quote->checkedAt(),
            null,
        );
    }
}

final class BookingCreationTransactionFake implements BookingTransaction
{
    public bool $active = false;

    public ?RuntimeException $rollbackFailure = null;

    /** @param list<string> $events */
    public function __construct(private array &$events)
    {
    }

    public function begin(): void
    {
        $this->events[] = 'begin';
        $this->active = true;
    }

    public function commit(): void
    {
        $this->events[] = 'commit';
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->events[] = 'rollback';

        if ($this->rollbackFailure !== null) {
            throw $this->rollbackFailure;
        }

        $this->active = false;
    }

    public function inProgress(): bool
    {
        $this->events[] = 'state';

        return $this->active;
    }
}
