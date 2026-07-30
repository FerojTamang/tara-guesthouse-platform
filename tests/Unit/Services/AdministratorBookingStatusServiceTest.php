<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingStatusSnapshot;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingStatusWriteRepository;
use App\Requests\AdministratorBookingStatusRequest;
use App\Services\AdministratorBookingStatusService;
use App\Services\BookingTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorBookingStatusServiceTest extends TestCase
{
    #[DataProvider('allowedTransitions')]
    public function testItCommitsAllowedTransitionsWithEventAndAudit(
        string $fromStatus,
        string $toStatus,
        ?string $reason,
        string $eventType,
    ): void {
        $bookings = new BookingStatusWriteRepositoryFake();
        $bookings->current = new BookingStatusSnapshot(
            31,
            'TGH-2026-STATUS000000000001',
            $fromStatus,
        );
        $audit = new AuditLogWriteRepositoryFake();
        $transaction = new BookingStatusTransactionFake();
        $service = self::service($bookings, $audit, $transaction);
        $request = AdministratorBookingStatusRequest::fromInput(
            $toStatus,
            $reason,
            $reason !== null,
        );

        $result = $service->transition(
            31,
            $request,
            91,
            '203.0.113.40',
            'Tara Test Agent',
        );

        self::assertSame(1, $transaction->beginCalls);
        self::assertSame(1, $transaction->commitCalls);
        self::assertSame(0, $transaction->rollBackCalls);
        self::assertSame(31, $bookings->lockedBookingId);
        self::assertSame($toStatus, $bookings->updatedStatus);
        self::assertSame($reason, $bookings->updatedReason);
        self::assertSame($eventType, $bookings->eventType);
        self::assertSame($fromStatus, $bookings->eventFromStatus);
        self::assertSame($toStatus, $bookings->eventToStatus);
        self::assertSame(91, $bookings->eventPerformedBy);
        self::assertSame($reason, $bookings->eventReason);
        self::assertSame(31, $result->bookingId());
        self::assertSame($fromStatus, $result->fromStatus());
        self::assertSame($toStatus, $result->toStatus());
        self::assertSame('2026-08-01 12:00:00', $result->changedAt()->format('Y-m-d H:i:s'));

        self::assertNotNull($audit->entry);
        self::assertSame(91, $audit->entry->userId());
        self::assertSame('booking_status_changed', $audit->entry->eventType());
        self::assertSame('booking', $audit->entry->entityType());
        self::assertSame(31, $audit->entry->entityId());
        self::assertSame('203.0.113.40', $audit->entry->ipAddress());
        self::assertSame('Tara Test Agent', $audit->entry->userAgent());
        self::assertSame([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ], json_decode(
            (string) $audit->entry->metadataJson(),
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
    }

    /** @return iterable<string, array{string, string, ?string, string}> */
    public static function allowedTransitions(): iterable
    {
        yield 'pending rejected' => [
            Booking::STATUS_PENDING,
            Booking::STATUS_REJECTED,
            'Unable to accept this request.',
            'booking_rejected',
        ];
        yield 'pending cancelled' => [
            Booking::STATUS_PENDING,
            Booking::STATUS_CANCELLED,
            'Guest requested cancellation.',
            'booking_cancelled',
        ];
        yield 'confirmed checked in' => [
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CHECKED_IN,
            null,
            'booking_checked_in',
        ];
        yield 'confirmed cancelled' => [
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CANCELLED,
            'Property closure.',
            'booking_cancelled',
        ];
        yield 'checked in checked out' => [
            Booking::STATUS_CHECKED_IN,
            Booking::STATUS_CHECKED_OUT,
            null,
            'booking_checked_out',
        ];
    }

    #[DataProvider('forbiddenTransitions')]
    public function testForbiddenAndTerminalTransitionsRollBack(
        string $fromStatus,
        string $toStatus,
        ?string $reason,
    ): void {
        $bookings = new BookingStatusWriteRepositoryFake();
        $bookings->current = new BookingStatusSnapshot(
            31,
            'TGH-2026-STATUS000000000001',
            $fromStatus,
        );
        $audit = new AuditLogWriteRepositoryFake();
        $transaction = new BookingStatusTransactionFake();
        $service = self::service($bookings, $audit, $transaction);
        $request = AdministratorBookingStatusRequest::fromInput(
            $toStatus,
            $reason,
            $reason !== null,
        );

        try {
            $service->transition(31, $request, 91, null, null);
            self::fail('Forbidden transition was accepted.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame(
                'The requested booking status transition is not allowed.',
                $exception->getMessage(),
            );
        }

        self::assertSame(1, $transaction->beginCalls);
        self::assertSame(0, $transaction->commitCalls);
        self::assertSame(1, $transaction->rollBackCalls);
        self::assertNull($bookings->updatedStatus);
        self::assertNull($audit->entry);
    }

    /** @return iterable<string, array{string, string, ?string}> */
    public static function forbiddenTransitions(): iterable
    {
        yield 'pending cannot check in' => [
            Booking::STATUS_PENDING,
            Booking::STATUS_CHECKED_IN,
            null,
        ];
        yield 'confirmed cannot reject' => [
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_REJECTED,
            'Too late to reject.',
        ];
        yield 'checked in cannot cancel' => [
            Booking::STATUS_CHECKED_IN,
            Booking::STATUS_CANCELLED,
            'Not permitted.',
        ];
        yield 'checked out terminal' => [
            Booking::STATUS_CHECKED_OUT,
            Booking::STATUS_CANCELLED,
            'Terminal booking.',
        ];
        yield 'cancelled terminal' => [
            Booking::STATUS_CANCELLED,
            Booking::STATUS_REJECTED,
            'Terminal booking.',
        ];
        yield 'rejected terminal' => [
            Booking::STATUS_REJECTED,
            Booking::STATUS_CANCELLED,
            'Terminal booking.',
        ];
    }

    public function testMissingBookingReturnsNotFoundAndRollsBack(): void
    {
        $bookings = new BookingStatusWriteRepositoryFake();
        $audit = new AuditLogWriteRepositoryFake();
        $transaction = new BookingStatusTransactionFake();
        $service = self::service($bookings, $audit, $transaction);
        $request = AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_CANCELLED,
            'Booking no longer required.',
            true,
        );

        try {
            $service->transition(999, $request, 91, null, null);
            self::fail('Missing booking was accepted.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The booking was not found.', $exception->getMessage());
        }

        self::assertSame(1, $transaction->rollBackCalls);
        self::assertNull($audit->entry);
    }

    public function testAuditFailureRollsBackTheBookingAndEventWrites(): void
    {
        $bookings = new BookingStatusWriteRepositoryFake();
        $bookings->current = new BookingStatusSnapshot(
            31,
            'TGH-2026-STATUS000000000001',
            Booking::STATUS_CONFIRMED,
        );
        $audit = new AuditLogWriteRepositoryFake();
        $audit->failure = new RuntimeException('private audit failure');
        $transaction = new BookingStatusTransactionFake();
        $service = self::service($bookings, $audit, $transaction);
        $request = AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_CHECKED_IN,
            null,
            false,
        );

        try {
            $service->transition(31, $request, 91, null, null);
            self::fail('Audit failure did not fail the transition.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }

        self::assertSame(0, $transaction->commitCalls);
        self::assertSame(1, $transaction->rollBackCalls);
    }

    public function testExistingTransactionFailsBeforeAnyWrite(): void
    {
        $bookings = new BookingStatusWriteRepositoryFake();
        $audit = new AuditLogWriteRepositoryFake();
        $transaction = new BookingStatusTransactionFake();
        $transaction->active = true;
        $service = self::service($bookings, $audit, $transaction);
        $request = AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_CANCELLED,
            'Booking no longer required.',
            true,
        );

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Booking operation failed.');

        try {
            $service->transition(31, $request, 91, null, null);
        } finally {
            self::assertSame(0, $transaction->beginCalls);
            self::assertNull($bookings->lockedBookingId);
        }
    }

    private static function service(
        BookingStatusWriteRepositoryFake $bookings,
        AuditLogWriteRepositoryFake $audit,
        BookingStatusTransactionFake $transaction,
    ): AdministratorBookingStatusService {
        return new AdministratorBookingStatusService(
            $bookings,
            $audit,
            $transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable(
                '2026-08-01 12:00:00 UTC',
            ),
        );
    }
}

final class BookingStatusWriteRepositoryFake implements BookingStatusWriteRepository
{
    public ?BookingStatusSnapshot $current = null;

    public ?int $lockedBookingId = null;

    public ?string $updatedStatus = null;

    public ?string $updatedReason = null;

    public ?string $eventType = null;

    public ?string $eventFromStatus = null;

    public ?string $eventToStatus = null;

    public ?int $eventPerformedBy = null;

    public ?string $eventReason = null;

    public function lockCurrent(int $bookingId): ?BookingStatusSnapshot
    {
        $this->lockedBookingId = $bookingId;

        return $this->current;
    }

    public function updateStatus(
        BookingStatusSnapshot $current,
        string $targetStatus,
        ?string $reason,
        DateTimeImmutable $changedAt,
    ): void {
        $this->updatedStatus = $targetStatus;
        $this->updatedReason = $reason;
    }

    public function appendEvent(
        int $bookingId,
        string $eventType,
        string $fromStatus,
        string $toStatus,
        int $performedBy,
        ?string $reason,
        DateTimeImmutable $createdAt,
        ?string $metadataJson = null,
    ): void {
        $this->eventType = $eventType;
        $this->eventFromStatus = $fromStatus;
        $this->eventToStatus = $toStatus;
        $this->eventPerformedBy = $performedBy;
        $this->eventReason = $reason;
    }
}

final class AuditLogWriteRepositoryFake implements AuditLogWriteRepository
{
    public ?AuditLogEntry $entry = null;

    public ?RuntimeException $failure = null;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->entry = $entry;
    }
}

final class BookingStatusTransactionFake implements BookingTransaction
{
    public bool $active = false;

    public int $beginCalls = 0;

    public int $commitCalls = 0;

    public int $rollBackCalls = 0;

    public function begin(): void
    {
        $this->beginCalls++;
        $this->active = true;
    }

    public function commit(): void
    {
        $this->commitCalls++;
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->rollBackCalls++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
