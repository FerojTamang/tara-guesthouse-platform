<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingStatusSnapshot;
use App\Models\BookingStatusTransitionResult;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingStatusWriteRepository;
use App\Requests\AdministratorBookingStatusRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Enforces and atomically records Administrator booking state transitions.
 */
final class AdministratorBookingStatusService
{
    /** @var Closure(): mixed */
    private readonly Closure $clock;

    /** @param null|callable(): mixed $clock */
    public function __construct(
        private readonly BookingStatusWriteRepository $bookings,
        private readonly AuditLogWriteRepository $auditLogs,
        private readonly BookingTransaction $transaction,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            )
            : Closure::fromCallable($clock);
    }

    public function transition(
        int $bookingId,
        AdministratorBookingStatusRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): BookingStatusTransitionResult {
        if ($bookingId <= 0 || $administratorId <= 0) {
            throw BookingException::failure();
        }

        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $current = $this->bookings->lockCurrent($bookingId);

            if (!$current instanceof BookingStatusSnapshot) {
                throw BookingException::bookingNotFound();
            }

            $targetStatus = $request->targetStatus();
            $this->assertAllowed($current->status(), $targetStatus);
            $changedAt = ($this->clock)();

            if (
                !$changedAt instanceof DateTimeImmutable
                || $changedAt->getOffset() !== 0
            ) {
                throw new RuntimeException('The booking status clock returned invalid data.');
            }

            $this->bookings->updateStatus(
                $current,
                $targetStatus,
                $request->reason(),
                $changedAt,
            );
            $this->bookings->appendEvent(
                $current->id(),
                $this->eventType($targetStatus),
                $current->status(),
                $targetStatus,
                $administratorId,
                $request->reason(),
                $changedAt,
            );
            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                'booking_status_changed',
                'booking',
                $current->id(),
                'Administrator changed a booking status.',
                $this->auditMetadata($current->status(), $targetStatus),
                $ipAddress,
                $userAgent,
                $changedAt,
            ));

            $commitAttempted = true;
            $this->transaction->commit();

            return new BookingStatusTransitionResult(
                $current->id(),
                $current->bookingReference(),
                $current->status(),
                $targetStatus,
                $changedAt,
            );
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof BookingException
                ? $failure
                : BookingException::failure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw BookingException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw BookingException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw BookingException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function assertAllowed(string $current, string $target): void
    {
        $allowed = match ($current) {
            Booking::STATUS_PENDING => [
                Booking::STATUS_REJECTED,
                Booking::STATUS_CANCELLED,
            ],
            Booking::STATUS_CONFIRMED => [
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CANCELLED,
            ],
            Booking::STATUS_CHECKED_IN => [
                Booking::STATUS_CHECKED_OUT,
            ],
            default => [],
        };

        if (!in_array($target, $allowed, true)) {
            throw BookingException::transitionConflict();
        }
    }

    private function eventType(string $targetStatus): string
    {
        return match ($targetStatus) {
            Booking::STATUS_REJECTED => 'booking_rejected',
            Booking::STATUS_CANCELLED => 'booking_cancelled',
            Booking::STATUS_CHECKED_IN => 'booking_checked_in',
            Booking::STATUS_CHECKED_OUT => 'booking_checked_out',
            default => throw new RuntimeException(
                'The booking event type is invalid.',
            ),
        };
    }

    private function auditMetadata(string $fromStatus, string $toStatus): string
    {
        try {
            return json_encode(
                [
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $failure) {
            throw new RuntimeException(
                'The booking audit metadata could not be encoded.',
                previous: $failure,
            );
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw BookingException::failure();
            }
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
