<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Review;
use DateTimeImmutable;

/** Defines retained verified-stay review persistence. */
interface ReviewRepository
{
    public function createPending(
        int $bookingId,
        int $reviewInvitationId,
        string $guestNameSnapshot,
        int $rating,
        ?string $title,
        string $comment,
        DateTimeImmutable $createdAt,
    ): Review;

    public function findById(int $id): ?Review;
    public function findByBookingId(int $bookingId): ?Review;
    public function findByInvitationId(int $reviewInvitationId): ?Review;
}
