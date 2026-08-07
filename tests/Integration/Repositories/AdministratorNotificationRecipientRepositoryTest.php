<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Repositories\PdoAdministratorNotificationRecipientRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\NotificationFixture;

#[Group('database')]
final class AdministratorNotificationRecipientRepositoryTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
    }

    public function testOnlyActiveNonDeletedAdministratorsReceiveSystemNotifications(): void
    {
        $first = NotificationFixture::insertAdministrator($this->connection, 'first');
        $inactive = NotificationFixture::insertAdministrator($this->connection, 'inactive');
        $locked = NotificationFixture::insertAdministrator($this->connection, 'locked');
        $deleted = NotificationFixture::insertAdministrator($this->connection, 'deleted');
        $second = NotificationFixture::insertAdministrator($this->connection, 'second');

        $this->connection->exec("UPDATE users SET account_status = 'inactive' WHERE id = $inactive");
        $this->connection->exec("UPDATE users SET account_status = 'locked' WHERE id = $locked");
        $this->connection->exec("UPDATE users SET deleted_at = UTC_TIMESTAMP() WHERE id = $deleted");

        $repository = new PdoAdministratorNotificationRecipientRepository($this->connection);

        self::assertSame([$first, $second], $repository->findActiveAdministratorIds());
    }
}
