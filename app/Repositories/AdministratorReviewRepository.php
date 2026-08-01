<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Review;
use DateTimeImmutable;

/** Defines Administrator review list, lock, and pending-only moderation persistence. */
interface AdministratorReviewRepository
{
    /** @return list<Review> */
    public function findAll(?string $status = null): array;

    public function findById(int $id): ?Review;
    public function lockById(int $id): ?Review;

    public function transitionPending(
        int $reviewId,
        string $targetStatus,
        int $administratorId,
        ?string $moderationNote,
        DateTimeImmutable $moderatedAt,
    ): ?Review;
}
