<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\NotificationException;
use App\Repositories\PdoNotificationRepository;
use App\Services\AdministratorNotificationService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\NotificationFixture;

#[Group('database')]
final class AdministratorNotificationServiceIntegrationTest extends DatabaseTestCase
{
    private AdministratorNotificationService $service;
    private int $administratorId;
    private int $otherAdministratorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->administratorId = NotificationFixture::insertAdministrator($this->connection, 'service-owner');
        $this->otherAdministratorId = NotificationFixture::insertAdministrator($this->connection, 'service-other');
        $this->service = new AdministratorNotificationService(
            new PdoNotificationRepository($this->connection),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-01 07:00:00 UTC'),
        );
    }

    public function testCreateInboxAndReadActionsPreserveOwnershipAndRows(): void
    {
        $first = $this->service->create(
            $this->administratorId,
            'contact_message_received',
            'New contact message',
            'A Guest submitted a contact message.',
            'contact_message',
            31,
        );
        $second = $this->service->create(
            $this->administratorId,
            'review_submitted',
            'Review awaiting moderation',
            'A verified Guest submitted a review.',
            'review',
            11,
        );
        $other = $this->service->create(
            $this->otherAdministratorId,
            'payment_submitted',
            'Payment awaiting verification',
            'A Guest submitted a payment attempt.',
            'payment',
            44,
        );

        $inbox = $this->service->inbox($this->administratorId, 1);
        self::assertCount(2, $inbox->notifications());
        self::assertSame(2, $inbox->unreadCount());
        self::assertSame($second->id(), $inbox->notifications()[0]->id());

        $read = $this->service->markRead($this->administratorId, $first->id());
        self::assertTrue($read->isRead());
        self::assertSame(1, $this->service->unreadCount($this->administratorId));
        self::assertSame(1, $this->service->markAllRead($this->administratorId));
        self::assertSame(0, $this->service->unreadCount($this->administratorId));
        self::assertSame(1, $this->service->unreadCount($this->otherAdministratorId));
        self::assertSame(3, (int) $this->connection->query('SELECT COUNT(*) FROM notifications')->fetchColumn());
        self::assertSame($other->id(), $this->service->inbox($this->otherAdministratorId, 1)->notifications()[0]->id());
    }

    public function testCrossAdministratorReadReturnsGenericNotFoundWithoutMutation(): void
    {
        $notification = $this->service->create(
            $this->otherAdministratorId,
            'review_submitted',
            'Review awaiting moderation',
            'A verified Guest submitted a review.',
        );

        try {
            $this->service->markRead($this->administratorId, $notification->id());
            self::fail('Cross-Administrator notification read was accepted.');
        } catch (NotificationException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The requested notification was not found.', $exception->getMessage());
        }

        self::assertSame(1, $this->service->unreadCount($this->otherAdministratorId));
    }
}
