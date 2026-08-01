<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\NotificationException;
use App\Models\Notification;
use App\Repositories\NotificationRepository;
use App\Services\AdministratorNotificationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorNotificationServiceTest extends TestCase
{
    private ServiceNotificationRepository $repository;
    private AdministratorNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ServiceNotificationRepository();
        $this->service = new AdministratorNotificationService(
            $this->repository,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-01 06:30:00 UTC'),
        );
    }

    public function testCreateNormalizesApprovedFieldsWithoutInventingTypeVocabulary(): void
    {
        $notification = $this->service->create(
            91,
            'review_submitted',
            '  Review awaiting moderation  ',
            " A verified Guest submitted a review.\r\nOpen the queue. ",
            'review',
            11,
        );

        self::assertSame(91, $notification->userId());
        self::assertSame('review_submitted', $notification->type());
        self::assertSame('Review awaiting moderation', $notification->title());
        self::assertSame("A verified Guest submitted a review.\nOpen the queue.", $notification->message());
        self::assertSame(
            '2026-08-01T06:30:00+00:00',
            $notification->createdAt()->format(DATE_ATOM),
        );
    }

    public function testCreateRejectsMalformedInputsBeforeRepositoryWrite(): void
    {
        foreach ([
            [0, 'review_submitted', 'Title', 'Message', null, null],
            [91, 'Review Submitted', 'Title', 'Message', null, null],
            [91, 'review_submitted', "Bad\nTitle", 'Message', null, null],
            [91, 'review_submitted', 'Title', '   ', null, null],
            [91, 'review_submitted', 'Title', str_repeat('é', 32_768), null, null],
            [91, 'review_submitted', 'Title', 'Message', 'Review', null],
            [91, 'review_submitted', 'Title', 'Message', null, 0],
        ] as $arguments) {
            try {
                $this->service->create(...$arguments);
                self::fail('Malformed notification input was accepted.');
            } catch (NotificationException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }

        self::assertSame(0, $this->repository->createCalls);
    }

    public function testInboxUsesFixedPaginationAndOwnershipScope(): void
    {
        for ($id = 1; $id <= 25; $id++) {
            $this->repository->items[$id] = self::notification($id, 91);
        }
        $this->repository->items[100] = self::notification(100, 92);
        $this->repository->items[1] = self::notification(1, 91, new DateTimeImmutable('2026-08-01 06:20:00 UTC'));

        $page = $this->service->inbox(91, 2);

        self::assertSame(2, $page->page());
        self::assertSame(20, $page->perPage());
        self::assertSame(25, $page->total());
        self::assertSame(24, $page->unreadCount());
        self::assertCount(5, $page->notifications());
        self::assertSame(20, $this->repository->lastLimit);
        self::assertSame(20, $this->repository->lastOffset);
        self::assertSame(91, $this->repository->lastUserId);
    }

    public function testReadOneAndReadAllNeverCrossAdministratorOwnership(): void
    {
        $this->repository->items[7] = self::notification(7, 91);
        $this->repository->items[8] = self::notification(8, 92);

        $read = $this->service->markRead(91, 7);
        self::assertTrue($read->isRead());
        self::assertSame(0, $this->service->markAllRead(91));
        self::assertFalse($this->repository->items[8]->isRead());

        try {
            $this->service->markRead(91, 8);
            self::fail('Another Administrator notification was readable.');
        } catch (NotificationException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testUnexpectedRepositoryAndClockFailuresAreSanitized(): void
    {
        $this->repository->fail = true;

        foreach ([
            fn () => $this->service->create(91, 'review_submitted', 'Title', 'Message'),
            fn () => $this->service->inbox(91, 1),
            fn () => $this->service->unreadCount(91),
            fn () => $this->service->markRead(91, 1),
            fn () => $this->service->markAllRead(91),
        ] as $operation) {
            try {
                $operation();
                self::fail('Repository failure was exposed.');
            } catch (NotificationException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertNull($exception->getPrevious());
            }
        }

        $badClock = new AdministratorNotificationService(
            new ServiceNotificationRepository(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-01 12:15:00 +05:45'),
        );

        try {
            $badClock->markAllRead(91);
            self::fail('Non-UTC clock was accepted.');
        } catch (NotificationException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }
    }

    private static function notification(int $id, int $userId, ?DateTimeImmutable $readAt = null): Notification
    {
        return new Notification(
            $id,
            $userId,
            'review_submitted',
            'Review awaiting moderation',
            'A verified Guest submitted a review.',
            'review',
            11,
            $readAt,
            new DateTimeImmutable('2026-08-01 06:00:00 UTC'),
        );
    }
}

final class ServiceNotificationRepository implements NotificationRepository
{
    /** @var array<int, Notification> */
    public array $items = [];
    public int $createCalls = 0;
    public ?int $lastUserId = null;
    public ?int $lastLimit = null;
    public ?int $lastOffset = null;
    public bool $fail = false;

    public function create(int $userId, string $type, string $title, string $message, ?string $relatedType, ?int $relatedId, DateTimeImmutable $createdAt): Notification
    {
        $this->guard();
        $this->createCalls++;
        return $this->items[999] = new Notification(999, $userId, $type, $title, $message, $relatedType, $relatedId, null, $createdAt);
    }

    public function findPageForUser(int $userId, int $limit, int $offset): array
    {
        $this->guard();
        $this->lastUserId = $userId;
        $this->lastLimit = $limit;
        $this->lastOffset = $offset;
        $items = array_values(array_filter($this->items, static fn (Notification $item): bool => $item->userId() === $userId));
        usort($items, static fn (Notification $left, Notification $right): int => $right->id() <=> $left->id());
        return array_slice($items, $offset, $limit);
    }

    public function countForUser(int $userId): int
    {
        $this->guard();
        return count(array_filter($this->items, static fn (Notification $item): bool => $item->userId() === $userId));
    }

    public function countUnreadForUser(int $userId): int
    {
        $this->guard();
        return count(array_filter($this->items, static fn (Notification $item): bool => $item->userId() === $userId && !$item->isRead()));
    }

    public function findByIdForUser(int $id, int $userId): ?Notification
    {
        $this->guard();
        $item = $this->items[$id] ?? null;
        return $item instanceof Notification && $item->userId() === $userId ? $item : null;
    }

    public function markReadForUser(int $id, int $userId, DateTimeImmutable $readAt): ?Notification
    {
        $this->guard();
        $item = $this->findByIdForUser($id, $userId);
        if (!$item instanceof Notification) return null;
        if ($item->isRead()) return $item;
        return $this->items[$id] = new Notification($item->id(), $item->userId(), $item->type(), $item->title(), $item->message(), $item->relatedType(), $item->relatedId(), $readAt, $item->createdAt());
    }

    public function markAllReadForUser(int $userId, DateTimeImmutable $readAt): int
    {
        $this->guard();
        $updated = 0;
        foreach ($this->items as $id => $item) {
            if ($item->userId() !== $userId || $item->isRead()) continue;
            $this->items[$id] = new Notification($item->id(), $item->userId(), $item->type(), $item->title(), $item->message(), $item->relatedType(), $item->relatedId(), $readAt, $item->createdAt());
            $updated++;
        }
        return $updated;
    }

    private function guard(): void
    {
        if ($this->fail) throw new RuntimeException('sensitive notification repository failure');
    }
}
