<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\NotificationInboxPage;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NotificationModelsTest extends TestCase
{
    public function testNotificationExposesApprovedDataAndReadState(): void
    {
        $notification = self::notification(readAt: new DateTimeImmutable('2026-08-01 06:05:00 UTC'));

        self::assertSame(7, $notification->id());
        self::assertSame(91, $notification->userId());
        self::assertSame('review_submitted', $notification->type());
        self::assertSame('Review awaiting moderation', $notification->title());
        self::assertSame("A verified Guest submitted a review.\nOpen the review queue.", $notification->message());
        self::assertSame('review', $notification->relatedType());
        self::assertSame(11, $notification->relatedId());
        self::assertTrue($notification->isRead());
    }

    public function testNotificationRejectsMalformedValuesAndInvalidTimestamps(): void
    {
        foreach ([
            ['id' => 0],
            ['userId' => 0],
            ['type' => 'Review Submitted'],
            ['type' => 'review-submitted'],
            ['title' => "Invalid\nTitle"],
            ['message' => '   '],
            ['message' => str_repeat('é', 32_768)],
            ['relatedType' => 'Review'],
            ['relatedId' => 0],
            ['readAt' => new DateTimeImmutable('2026-08-01 05:59:59 UTC')],
            ['createdAt' => new DateTimeImmutable('2026-08-01 06:00:00 +05:45')],
        ] as $overrides) {
            try {
                self::notification(...$overrides);
                self::fail('Malformed notification data was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testInboxPageCalculatesPaginationWithoutExposingAnotherShape(): void
    {
        $page = new NotificationInboxPage([self::notification()], 2, 20, 41, 3);

        self::assertSame(2, $page->page());
        self::assertSame(20, $page->perPage());
        self::assertSame(41, $page->total());
        self::assertSame(3, $page->unreadCount());
        self::assertSame(3, $page->totalPages());
        self::assertTrue($page->hasPrevious());
        self::assertTrue($page->hasNext());
    }

    public function testInboxPageRejectsInvalidCountsAndItems(): void
    {
        foreach ([
            [[new \stdClass()], 1, 20, 1, 1],
            [[], 0, 20, 0, 0],
            [[], 1, 0, 0, 0],
            [[], 1, 20, -1, 0],
            [[], 1, 20, 1, 2],
        ] as $arguments) {
            $this->expectExceptionForArguments($arguments);
        }
    }

    /** @param array<int, mixed> $arguments */
    private function expectExceptionForArguments(array $arguments): void
    {
        try {
            new NotificationInboxPage(...$arguments);
            self::fail('Invalid notification inbox page data was accepted.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    private static function notification(
        int $id = 7,
        int $userId = 91,
        string $type = 'review_submitted',
        string $title = 'Review awaiting moderation',
        string $message = "A verified Guest submitted a review.\nOpen the review queue.",
        ?string $relatedType = 'review',
        ?int $relatedId = 11,
        ?DateTimeImmutable $readAt = null,
        ?DateTimeImmutable $createdAt = null,
    ): Notification {
        return new Notification(
            $id,
            $userId,
            $type,
            $title,
            $message,
            $relatedType,
            $relatedId,
            $readAt,
            $createdAt ?? new DateTimeImmutable('2026-08-01 06:00:00 UTC'),
        );
    }
}
