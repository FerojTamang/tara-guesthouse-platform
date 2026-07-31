<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\IssuedReviewInvitation;
use App\Models\ReviewInvitationPolicy;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Repositories\ReviewRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Throwable;

/** Issues one audited invitation for one verified checked-out stay. */
final readonly class ReviewInvitationService
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
        private ReviewInvitationPolicy $policy,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    public function issueForBooking(
        int $bookingId,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): IssuedReviewInvitation {
        $this->assertIdentifiers($bookingId, $administratorId);

        $issued = $this->transact(fn (): ?IssuedReviewInvitation => $this->issue(
            $bookingId,
            $administratorId,
            $ipAddress,
            $userAgent,
            false,
        ));

        if (!$issued instanceof IssuedReviewInvitation) {
            throw ReviewException::failure();
        }

        return $issued;
    }

    /**
     * Creates one invitation within a transaction owned by the checkout workflow.
     *
     * A pre-existing invitation is an idempotent no-op because its raw token can
     * never be reconstructed or exposed.
     */
    public function issueForBookingIfAbsentInCurrentTransaction(
        int $bookingId,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?IssuedReviewInvitation {
        $this->assertIdentifiers($bookingId, $administratorId);
        $this->requireExistingTransaction();

        return $this->issue(
            $bookingId,
            $administratorId,
            $ipAddress,
            $userAgent,
            true,
        );
    }

    private function issue(
        int $bookingId,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        bool $allowExistingInvitation,
    ): ?IssuedReviewInvitation {
        $booking = $this->bookings->findById($bookingId);

        if (
            !$booking instanceof Booking
            || $booking->bookingStatus() !== Booking::STATUS_CHECKED_OUT
            || $booking->isDeleted()
        ) {
            throw ReviewException::bookingNotEligible();
        }

        if ($this->reviews->findByBookingId($bookingId) !== null) {
            throw ReviewException::reviewConflict();
        }

        if ($this->invitations->findByBookingId($bookingId) !== null) {
            if ($allowExistingInvitation) {
                return null;
            }

            throw ReviewException::invitationConflict();
        }

        $createdAt = $this->now();
        $expiresAt = $this->policy->expiresAt($createdAt);
        $token = $this->tokens->generate();

        try {
            $invitation = $this->invitations->create(
                $bookingId,
                $this->tokens->hash($token),
                $expiresAt,
                $createdAt,
            );
        } catch (ReviewException $exception) {
            if (
                $allowExistingInvitation
                && $exception->isConflict()
                && $this->invitations->findByBookingId($bookingId) !== null
            ) {
                return null;
            }

            throw $exception;
        }

        $this->auditLogs->append(new AuditLogEntry(
            $administratorId,
            'review_invitation_created',
            'review_invitation',
            $invitation->id(),
            'Administrator created a verified-stay review invitation.',
            $this->auditMetadata($bookingId, $invitation->expiresAt()),
            $this->normalizeIpAddress($ipAddress),
            $this->normalizeUserAgent($userAgent),
            $createdAt,
        ));

        return new IssuedReviewInvitation($invitation, $token);
    }

    private function assertIdentifiers(int $bookingId, int $administratorId): void
    {
        if ($bookingId <= 0 || $administratorId <= 0) {
            throw ReviewException::failure();
        }
    }

    private function requireExistingTransaction(): void
    {
        try {
            if (!$this->transaction->inProgress()) {
                throw ReviewException::failure();
            }
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ReviewException::failure($failure);
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

    private function auditMetadata(int $bookingId, DateTimeImmutable $expiresAt): string
    {
        try {
            return json_encode([
                'booking_id' => $bookingId,
                'expires_at' => $expiresAt->format(DATE_ATOM),
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
            } catch (Throwable $stateFailure) {
                throw ReviewException::failure();
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
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
}
