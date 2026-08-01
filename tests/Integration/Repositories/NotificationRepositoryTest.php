<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Repositories\PdoNotificationRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\NotificationFixture;

#[Group('database')]
final class NotificationRepositoryTest extends DatabaseTestCase
{
    private PdoNotificationRepository $repository;
    private int $administratorId;
    private int $otherAdministratorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->administratorId = NotificationFixture::insertAdministrator($this->connection, 'owner');
        $this->otherAdministratorId = NotificationFixture::insertAdministrator($this->connection, 'other');
        $this->repository = new PdoNotificationRepository($this->connection);
    }

    public function testCreateAndOwnershipScopedPaginationMapExactRecords(): void
    {
        $created = $this->repository->create(
            $this->administratorId,
            'review_submitted',
            'Review awaiting moderation',
            "A verified Guest submitted a review.\nOpen the queue.",
            'review',
            11,
            new DateTimeImmutable('2026-08-01 06:30:00 UTC'),
        );
        self::assertSame($this->administratorId, $created->userId());
        self::assertFalse($created->isRead());

        NotificationFixture::insert($this->connection, $this->administratorId, [
            'type' => 'payment_submitted',
            'title' => 'Payment awaiting verification',
            'created_at' => '2026-08-01 07:00:00',
        ]);
        NotificationFixture::insert($this->connection, $this->otherAdministratorId, [
            'title' => 'Other Administrator notification',
            'created_at' => '2026-08-01 08:00:00',
        ]);

        $page = $this->repository->findPageForUser($this->administratorId, 1, 0);
        self::assertCount(1, $page);
        self::assertSame('Payment awaiting verification', $page[0]->title());
        self::assertSame(2, $this->repository->countForUser($this->administratorId));
        self::assertSame(2, $this->repository->countUnreadForUser($this->administratorId));
        self::assertNull($this->repository->findByIdForUser($created->id(), $this->otherAdministratorId));
    }

    public function testReadOneIsIdempotentAndOwnershipSafe(): void
    {
        $id = NotificationFixture::insert($this->connection, $this->administratorId);
        $firstRead = new DateTimeImmutable('2026-08-01 07:00:00 UTC');
        $secondRead = new DateTimeImmutable('2026-08-01 08:00:00 UTC');

        self::assertNull($this->repository->markReadForUser($id, $this->otherAdministratorId, $firstRead));
        $notification = $this->repository->markReadForUser($id, $this->administratorId, $firstRead);
        self::assertNotNull($notification);
        self::assertSame(
            $firstRead->format(DATE_ATOM),
            $notification->readAt()?->format(DATE_ATOM),
        );
        $again = $this->repository->markReadForUser($id, $this->administratorId, $secondRead);
        self::assertNotNull($again);
        self::assertSame(
            $firstRead->format(DATE_ATOM),
            $again->readAt()?->format(DATE_ATOM),
        );
    }

    public function testReadAllUpdatesOnlyCurrentAdministratorUnreadRowsWithoutDeleting(): void
    {
        $first = NotificationFixture::insert($this->connection, $this->administratorId);
        $second = NotificationFixture::insert($this->connection, $this->administratorId, [
            'type' => 'review_submitted',
            'title' => 'Second notification',
            'created_at' => '2026-08-01 06:10:00',
        ]);
        $other = NotificationFixture::insert($this->connection, $this->otherAdministratorId, [
            'title' => 'Other notification',
        ]);

        self::assertSame(2, $this->repository->markAllReadForUser(
            $this->administratorId,
            new DateTimeImmutable('2026-08-01 07:00:00 UTC'),
        ));
        self::assertSame(0, $this->repository->countUnreadForUser($this->administratorId));
        self::assertSame(1, $this->repository->countUnreadForUser($this->otherAdministratorId));
        self::assertNotNull($this->repository->findByIdForUser($first, $this->administratorId));
        self::assertNotNull($this->repository->findByIdForUser($second, $this->administratorId));
        self::assertNotNull($this->repository->findByIdForUser($other, $this->otherAdministratorId));
        self::assertSame(0, $this->repository->markAllReadForUser(
            $this->administratorId,
            new DateTimeImmutable('2026-08-01 08:00:00 UTC'),
        ));
    }
}
