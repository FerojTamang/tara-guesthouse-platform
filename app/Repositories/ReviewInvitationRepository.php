<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ReviewInvitation;
use DateTimeImmutable;

/** Defines persistence for single-use verified-stay invitations. */
interface ReviewInvitationRepository
{
    public function create(
        int $bookingId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): ReviewInvitation;

    public function findById(int $id): ?ReviewInvitation;
    public function findByBookingId(int $bookingId): ?ReviewInvitation;
    public function findByTokenHash(string $tokenHash): ?ReviewInvitation;
    public function lockByTokenHash(string $tokenHash): ?ReviewInvitation;

    public function markUsed(
        int $invitationId,
        DateTimeImmutable $usedAt,
    ): ?ReviewInvitation;
}
