<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/** Combines one review with its verified-stay booking and invitation context. */
final readonly class AdministratorReviewRecord
{
    public function __construct(
        private Review $review,
        private Booking $booking,
        private ReviewInvitation $invitation,
    ) {
        if (
            $review->bookingId() !== $booking->id()
            || $review->reviewInvitationId() !== $invitation->id()
            || $invitation->bookingId() !== $booking->id()
        ) {
            throw new InvalidArgumentException('Administrator review context is invalid.');
        }
    }

    public function review(): Review
    {
        return $this->review;
    }

    public function booking(): Booking
    {
        return $this->booking;
    }

    public function invitation(): ReviewInvitation
    {
        return $this->invitation;
    }

    public function isVerifiedStay(): bool
    {
        return $this->booking->bookingStatus() === Booking::STATUS_CHECKED_OUT
            && !$this->booking->isDeleted()
            && $this->invitation->isUsed();
    }

    public function canModerate(): bool
    {
        return $this->review->status() === Review::STATUS_PENDING
            && $this->isVerifiedStay();
    }
}
