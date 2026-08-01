<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotificationException;
use App\Models\Notification;
use App\Models\NotificationInboxPage;
use App\Repositories\NotificationRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Owns Administrator notification creation, listing, and ownership-safe read actions. */
final readonly class AdministratorNotificationService
{
    public const PAGE_SIZE = 20;

    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private NotificationRepository $notifications,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): Notification {
        if ($userId <= 0) {
            throw NotificationException::invalidInput('Notification recipient is invalid.');
        }

        try {
            return $this->notifications->create(
                $userId,
                $this->type($type, 'Notification type is invalid.'),
                $this->singleLine($title, Notification::TITLE_MAXIMUM_LENGTH, 'Notification title is invalid.'),
                $this->message($message),
                $relatedType === null
                    ? null
                    : $this->type($relatedType, 'Notification related type is invalid.'),
                $this->relatedId($relatedId),
                $this->now(),
            );
        } catch (NotificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw NotificationException::failure();
        }
    }

    public function inbox(int $userId, int $page): NotificationInboxPage
    {
        if ($userId <= 0) {
            throw NotificationException::failure();
        }

        if ($page <= 0) {
            throw NotificationException::invalidInput('Notification page is invalid.');
        }

        try {
            $total = $this->notifications->countForUser($userId);
            $unread = $this->notifications->countUnreadForUser($userId);
            $offset = ($page - 1) * self::PAGE_SIZE;

            if ($offset < 0) {
                throw NotificationException::invalidInput('Notification page is invalid.');
            }

            return new NotificationInboxPage(
                $this->notifications->findPageForUser($userId, self::PAGE_SIZE, $offset),
                $page,
                self::PAGE_SIZE,
                $total,
                $unread,
            );
        } catch (NotificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw NotificationException::failure();
        }
    }

    public function unreadCount(int $userId): int
    {
        if ($userId <= 0) {
            throw NotificationException::failure();
        }

        try {
            return $this->notifications->countUnreadForUser($userId);
        } catch (Throwable) {
            throw NotificationException::failure();
        }
    }

    public function markRead(int $userId, int $notificationId): Notification
    {
        if ($userId <= 0 || $notificationId <= 0) {
            throw NotificationException::notFound();
        }

        try {
            $notification = $this->notifications->markReadForUser(
                $notificationId,
                $userId,
                $this->now(),
            );

            if (!$notification instanceof Notification) {
                throw NotificationException::notFound();
            }

            return $notification;
        } catch (NotificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw NotificationException::failure();
        }
    }

    public function markAllRead(int $userId): int
    {
        if ($userId <= 0) {
            throw NotificationException::failure();
        }

        try {
            return $this->notifications->markAllReadForUser($userId, $this->now());
        } catch (Throwable) {
            throw NotificationException::failure();
        }
    }

    private function type(string $value, string $message): string
    {
        $normalized = trim($value);

        if (
            $normalized === ''
            || strlen($normalized) > Notification::TYPE_MAXIMUM_LENGTH
            || preg_match('/^[a-z][a-z0-9_]*$/D', $normalized) !== 1
        ) {
            throw NotificationException::invalidInput($message);
        }

        return $normalized;
    }

    private function singleLine(string $value, int $maximumLength, string $message): string
    {
        $normalized = trim($value);

        if (
            $normalized === ''
            || !mb_check_encoding($normalized, 'UTF-8')
            || mb_strlen($normalized, 'UTF-8') > $maximumLength
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw NotificationException::invalidInput($message);
        }

        return $normalized;
    }

    private function message(string $value): string
    {
        $normalized = preg_replace('/\R/u', "\n", trim($value));

        if (
            !is_string($normalized)
            || $normalized === ''
            || !mb_check_encoding($normalized, 'UTF-8')
            || mb_strlen($normalized, 'UTF-8') > Notification::MESSAGE_MAXIMUM_LENGTH
            || strlen($normalized) > Notification::MESSAGE_MAXIMUM_LENGTH
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw NotificationException::invalidInput('Notification message is invalid.');
        }

        return $normalized;
    }

    private function relatedId(?int $value): ?int
    {
        if ($value !== null && $value <= 0) {
            throw NotificationException::invalidInput('Notification related identifier is invalid.');
        }

        return $value;
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw NotificationException::failure();
        }

        return $value;
    }
}
