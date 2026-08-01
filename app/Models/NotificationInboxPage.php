<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/** Represents one ownership-scoped page of Administrator notifications. */
final readonly class NotificationInboxPage
{
    /** @param list<Notification> $notifications */
    public function __construct(
        private array $notifications,
        private int $page,
        private int $perPage,
        private int $total,
        private int $unreadCount,
    ) {
        if (
            !array_is_list($notifications)
            || array_filter($notifications, static fn (mixed $item): bool => !$item instanceof Notification) !== []
            || $page <= 0
            || $perPage <= 0
            || $total < 0
            || $unreadCount < 0
            || $unreadCount > $total
            || count($notifications) > $perPage
        ) {
            throw new InvalidArgumentException('Notification inbox page data is invalid.');
        }
    }

    /** @return list<Notification> */
    public function notifications(): array { return $this->notifications; }
    public function page(): int { return $this->page; }
    public function perPage(): int { return $this->perPage; }
    public function total(): int { return $this->total; }
    public function unreadCount(): int { return $this->unreadCount; }
    public function totalPages(): int { return max(1, (int) ceil($this->total / $this->perPage)); }
    public function hasPrevious(): bool { return $this->page > 1; }
    public function hasNext(): bool { return $this->page < $this->totalPages(); }
}
