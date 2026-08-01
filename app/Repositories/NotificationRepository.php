<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Notification;
use DateTimeImmutable;

/** Defines ownership-scoped persistence for Administrator notifications. */
interface NotificationRepository
{
    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $relatedType,
        ?int $relatedId,
        DateTimeImmutable $createdAt,
    ): Notification;

    /** @return list<Notification> */
    public function findPageForUser(int $userId, int $limit, int $offset): array;

    public function countForUser(int $userId): int;
    public function countUnreadForUser(int $userId): int;
    public function findByIdForUser(int $id, int $userId): ?Notification;
    public function markReadForUser(int $id, int $userId, DateTimeImmutable $readAt): ?Notification;
    public function markAllReadForUser(int $userId, DateTimeImmutable $readAt): int;
}
