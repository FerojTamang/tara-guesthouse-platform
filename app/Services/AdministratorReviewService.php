<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewException;
use App\Models\AdministratorReviewRecord;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Repositories\AdministratorReviewRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Requests\AdministratorReviewStatusRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Throwable;

/** Owns protected review listing, verified-stay detail, and pending-only moderation. */
final readonly class AdministratorReviewService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private AdministratorReviewRepository $reviews,
        private BookingCatalogRepository $bookings,
        private ReviewInvitationRepository $invitations,
        private AuditLogWriteRepository $auditLogs,
        private ReviewTransaction $transaction,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            ),
        );
    }

    /** @return list<Review> */
    public function list(?string $status = null): array
    {
        $normalized = $this->statusFilter($status);

        return $this->execute(function () use ($normalized): array {
            $reviews = $this->reviews->findAll($normalized);

            foreach ($reviews as $review) {
                if (
                    !$review instanceof Review
                    || ($normalized !== null && $review->status() !== $normalized)
                ) {
                    throw ReviewException::failure();
                }
            }

            return $reviews;
        });
    }

    public function get(int $reviewId): AdministratorReviewRecord
    {
        if ($reviewId <= 0) {
            throw ReviewException::reviewNotFound();
        }

        return $this->execute(function () use ($reviewId): AdministratorReviewRecord {
            $review = $this->reviews->findById($reviewId);

            if (!$review instanceof Review) {
                throw ReviewException::reviewNotFound();
            }

            return $this->record($review);
        });
    }

    public function transition(
        int $reviewId,
        AdministratorReviewStatusRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): Review {
        if ($reviewId <= 0 || $administratorId <= 0) {
            throw ReviewException::invalidInput('Review moderation request is invalid.');
        }

        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $current = $this->reviews->lockById($reviewId);

            if (!$current instanceof Review) {
                throw ReviewException::reviewNotFound();
            }

            if ($current->status() !== Review::STATUS_PENDING) {
                throw ReviewException::moderationConflict();
            }

            $record = $this->record($current);

            if (!$record->isVerifiedStay()) {
                throw ReviewException::moderationConflict();
            }

            $moderatedAt = $this->now();
            $moderated = $this->reviews->transitionPending(
                $current->id(),
                $request->targetStatus(),
                $administratorId,
                $request->moderationNote(),
                $moderatedAt,
            );

            if (
                !$moderated instanceof Review
                || $moderated->id() !== $current->id()
                || $moderated->bookingId() !== $current->bookingId()
                || $moderated->reviewInvitationId() !== $current->reviewInvitationId()
                || $moderated->status() !== $request->targetStatus()
                || $moderated->moderatedBy() !== $administratorId
                || !$moderated->moderatedAt() instanceof DateTimeImmutable
                || $moderated->moderatedAt() != $moderatedAt
                || $moderated->moderationNote() !== $request->moderationNote()
            ) {
                throw ReviewException::moderationConflict();
            }

            $this->auditLogs->append(new AuditLogEntry(
                $administratorId,
                $this->auditEventType($moderated->status()),
                'review',
                $moderated->id(),
                $this->auditDescription($moderated->status()),
                $this->auditMetadata(
                    $record->booking()->id(),
                    $record->invitation()->id(),
                    $current->status(),
                    $moderated->status(),
                ),
                $this->normalizeIpAddress($ipAddress),
                $this->normalizeUserAgent($userAgent),
                $moderatedAt,
            ));

            $commitAttempted = true;
            $this->transaction->commit();

            return $moderated;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof ReviewException
                ? $failure
                : ReviewException::failure();

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable) {
                throw ReviewException::failure();
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable) {
                    throw ReviewException::failure();
                }
            }

            if ($commitAttempted) {
                throw ReviewException::failure();
            }

            throw $safeFailure;
        }
    }

    private function record(Review $review): AdministratorReviewRecord
    {
        $booking = $this->bookings->findById($review->bookingId());
        $invitation = $this->invitations->findById($review->reviewInvitationId());

        if (
            !$booking instanceof Booking
            || !$invitation instanceof ReviewInvitation
        ) {
            throw ReviewException::failure();
        }

        try {
            return new AdministratorReviewRecord($review, $booking, $invitation);
        } catch (Throwable) {
            throw ReviewException::failure();
        }
    }

    private function statusFilter(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        if (
            $status !== trim($status)
            || !in_array($status, Review::STATUSES, true)
        ) {
            throw ReviewException::invalidInput('Review status filter is invalid.');
        }

        return $status;
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw ReviewException::failure();
        }

        return $value;
    }

    private function auditEventType(string $status): string
    {
        return match ($status) {
            Review::STATUS_APPROVED => 'review_approved',
            Review::STATUS_REJECTED => 'review_rejected',
            default => throw ReviewException::failure(),
        };
    }

    private function auditDescription(string $status): string
    {
        return match ($status) {
            Review::STATUS_APPROVED => 'Administrator approved a pending verified-stay review.',
            Review::STATUS_REJECTED => 'Administrator rejected a pending verified-stay review.',
            default => throw ReviewException::failure(),
        };
    }

    private function auditMetadata(
        int $bookingId,
        int $invitationId,
        string $fromStatus,
        string $toStatus,
    ): string {
        try {
            return json_encode([
                'booking_id' => $bookingId,
                'review_invitation_id' => $invitationId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw ReviewException::failure();
        }
    }

    private function normalizeIpAddress(?string $ipAddress): ?string
    {
        return $ipAddress !== null
            && filter_var($ipAddress, FILTER_VALIDATE_IP) !== false
                ? $ipAddress
                : null;
    }

    private function normalizeUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || !mb_check_encoding($userAgent, 'UTF-8')) {
            return null;
        }

        $normalized = preg_replace('/[\x00-\x20\x7F]+/u', ' ', trim($userAgent));

        return is_string($normalized) && $normalized !== ''
            ? mb_strcut($normalized, 0, 255, 'UTF-8')
            : null;
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw ReviewException::failure();
            }
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ReviewException::failure();
        }
    }

    /** @template T @param callable(): T $operation @return T */
    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ReviewException::failure();
        }
    }
}
