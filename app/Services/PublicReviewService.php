<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Repositories\ReviewRepository;
use App\Requests\ReviewSubmissionRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use SensitiveParameter;
use Throwable;

/** Owns public approved-review reads and verified Guest review submission. */
final readonly class PublicReviewService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private BookingCatalogRepository $bookings,
        private ReviewInvitationRepository $invitations,
        private ReviewRepository $reviews,
        private AuditLogWriteRepository $auditLogs,
        private ReviewTransaction $transaction,
        private ReviewTokenHasher $tokens,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    /**
     * @return list<array{
     *     guest_name: string,
     *     rating: int,
     *     title: ?string,
     *     comment: string,
     *     created_at: string
     * }>
     */
    public function approved(): array
    {
        return $this->execute(function (): array {
            $result = [];

            foreach ($this->reviews->findAllApproved() as $review) {
                if (!$review instanceof Review || !$review->isPublic()) {
                    throw ReviewException::failure();
                }

                $result[] = [
                    'guest_name' => $review->guestNameSnapshot(),
                    'rating' => $review->rating(),
                    'title' => $review->title(),
                    'comment' => $review->comment(),
                    'created_at' => $review->createdAt()->format(DATE_ATOM),
                ];
            }

            return $result;
        });
    }

    public function assertInvitationAvailable(#[SensitiveParameter] string $token): void
    {
        $this->execute(function () use ($token): void {
            $now = $this->now();
            $hash = $this->hashForLookup($token);
            $invitation = $this->invitations->findByTokenHash($hash);
            $this->requireEligibleInvitation($invitation, $now);
        });
    }

    public function submit(
        #[SensitiveParameter] string $token,
        ReviewSubmissionRequest $request,
        ?string $ipAddress,
        ?string $userAgent,
    ): Review {
        return $this->transact(function () use ($token, $request, $ipAddress, $userAgent): Review {
            $now = $this->now();
            $hash = $this->hashForLookup($token);
            $invitation = $this->invitations->lockByTokenHash($hash);
            $booking = $this->requireEligibleInvitation($invitation, $now);

            try {
                $review = $this->reviews->createPending(
                    $booking->id(),
                    $invitation->id(),
                    $booking->guestFullName(),
                    $request->rating(),
                    $request->title(),
                    $request->comment(),
                    $now,
                );
            } catch (ReviewException $exception) {
                if ($exception->isConflict()) {
                    throw ReviewException::invitationUnavailable();
                }

                throw $exception;
            }

            $used = $this->invitations->markUsed($invitation->id(), $now);

            if (!$used instanceof ReviewInvitation || !$used->isUsed()) {
                throw ReviewException::invitationUnavailable();
            }

            $this->auditLogs->append(new AuditLogEntry(
                null,
                'review_submitted',
                'review',
                $review->id(),
                'Guest submitted a verified-stay review for moderation.',
                $this->auditMetadata($booking->id(), $invitation->id()),
                $this->normalizeIpAddress($ipAddress),
                $this->normalizeUserAgent($userAgent),
                $now,
            ));

            return $review;
        });
    }

    private function requireEligibleInvitation(
        ?ReviewInvitation $invitation,
        DateTimeImmutable $now,
    ): Booking {
        if (
            !$invitation instanceof ReviewInvitation
            || $invitation->isUsed()
            || $invitation->isExpiredAt($now)
        ) {
            throw ReviewException::invitationUnavailable();
        }

        $booking = $this->bookings->findById($invitation->bookingId());

        if (
            !$booking instanceof Booking
            || $booking->bookingStatus() !== Booking::STATUS_CHECKED_OUT
            || $booking->isDeleted()
            || $this->reviews->findByBookingId($booking->id()) !== null
            || $this->reviews->findByInvitationId($invitation->id()) !== null
        ) {
            throw ReviewException::invitationUnavailable();
        }

        return $booking;
    }

    private function hashForLookup(#[SensitiveParameter] string $token): string
    {
        try {
            return $this->tokens->hash($token);
        } catch (ReviewException $exception) {
            if ($exception->isValidationFailure()) {
                throw ReviewException::invitationUnavailable();
            }

            throw $exception;
        }
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw ReviewException::failure();
        }

        return $value;
    }

    private function auditMetadata(int $bookingId, int $invitationId): string
    {
        try {
            return json_encode([
                'booking_id' => $bookingId,
                'review_invitation_id' => $invitationId,
                'review_status' => Review::STATUS_PENDING,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $failure) {
            throw ReviewException::failure($failure);
        }
    }

    private function normalizeIpAddress(?string $ipAddress): ?string
    {
        return $ipAddress !== null && filter_var($ipAddress, FILTER_VALIDATE_IP) !== false
            ? $ipAddress
            : null;
    }

    private function normalizeUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        $normalized = trim($userAgent);

        return $normalized !== ''
            && mb_check_encoding($normalized, 'UTF-8')
            && strlen($normalized) <= 255
            && preg_match('/[\x00-\x1F\x7F]/', $normalized) !== 1
                ? $normalized
                : null;
    }

    /** @template T @param callable(): T $operation @return T */
    private function transact(callable $operation): mixed
    {
        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $result = $operation();
            $commitAttempted = true;
            $this->transaction->commit();

            return $result;
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

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw ReviewException::failure();
            }
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ReviewException::failure($failure);
        }
    }

    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ReviewException::failure($failure);
        }
    }
}
