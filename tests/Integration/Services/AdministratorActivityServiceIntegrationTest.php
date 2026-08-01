<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\ActivityException;
use App\Models\AuditLogEntry;
use App\Repositories\ActivityRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Requests\ActivityContentRequest;
use App\Services\AdministratorActivityService;
use App\Services\PdoActivityTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\ActivityFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorActivityServiceIntegrationTest extends DatabaseTestCase
{
    private ActivityRepository $repository;
    private int $administratorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR
                . 'database'
                . DIRECTORY_SEPARATOR
                . 'migrations',
        ))->migrate();
        $this->repository = new ActivityRepository($this->connection);
        $this->administratorId = $this->insertAdministrator();
    }

    public function testCreateUpdateVisibilityAndRemovalPersistWithAuditEvidence(): void
    {
        $service = $this->service(new PdoAuditLogRepository($this->connection));
        $created = $service->create(
            $this->request(),
            true,
            false,
            $this->administratorId,
            '127.0.0.1',
            'Integration Test',
        );

        self::assertTrue($created->isActive());
        self::assertFalse($created->isPublished());

        $updated = $service->update(
            $created->id(),
            ActivityContentRequest::fromInput(
                'Updated Jungle Walk',
                'updated-jungle-walk',
                'Updated guided jungle walk.',
                'Updated activity content.',
                '3000.00',
                'per person',
                '4 hours',
                'Dhulikhel',
                20,
                true,
                2,
            ),
            $this->administratorId,
            null,
            null,
        );
        self::assertTrue($updated->isActive());
        self::assertFalse($updated->isPublished());

        $published = $service->setPublished(
            $created->id(),
            true,
            $this->administratorId,
            null,
            null,
        );
        self::assertTrue($published->isActive());
        self::assertTrue($published->isPublished());

        $inactive = $service->setActive(
            $created->id(),
            false,
            $this->administratorId,
            null,
            null,
        );
        self::assertFalse($inactive->isActive());
        self::assertTrue($inactive->isPublished());

        $removed = $service->remove(
            $created->id(),
            $this->administratorId,
            null,
            null,
        );
        self::assertFalse($removed->isActive());
        self::assertFalse($removed->isPublished());
        self::assertNotNull($this->repository->findById($created->id()));

        $events = $this->connection->query(
            'SELECT event_type FROM audit_logs WHERE entity_type = \'activity\' '
            . 'ORDER BY id',
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([
            'activity_created',
            'activity_updated',
            'activity_publication_changed',
            'activity_active_changed',
            'activity_removed',
        ], $events);
        $audit = $this->connection->query(
            'SELECT user_id, entity_id, ip_address, user_agent, metadata_json '
            . 'FROM audit_logs WHERE event_type = \'activity_created\' LIMIT 1',
        )->fetch();
        self::assertIsArray($audit);
        self::assertSame((string) $this->administratorId, (string) $audit['user_id']);
        self::assertSame((string) $created->id(), (string) $audit['entity_id']);
        self::assertSame('127.0.0.1', $audit['ip_address']);
        self::assertSame('Integration Test', $audit['user_agent']);
        self::assertStringContainsString('"slug":"jungle-walk"', (string) $audit['metadata_json']);
    }

    public function testAuditFailureRollsBackTheActivityWrite(): void
    {
        $service = $this->service(new AdministratorActivityFailingAuditRepository());

        try {
            $service->create(
                $this->request(),
                true,
                true,
                $this->administratorId,
                null,
                null,
            );
            self::fail('Expected a safe audit failure.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query(
            'SELECT COUNT(*) FROM activities',
        )->fetchColumn());
        self::assertSame(0, (int) $this->connection->query(
            'SELECT COUNT(*) FROM audit_logs WHERE entity_type = \'activity\'',
        )->fetchColumn());
    }

    public function testDuplicateSlugConflictRollsBackWithoutExtraAudit(): void
    {
        ActivityFixture::insert($this->connection, ['slug' => 'jungle-walk']);
        $service = $this->service(new PdoAuditLogRepository($this->connection));

        try {
            $service->create(
                $this->request(),
                true,
                true,
                $this->administratorId,
                null,
                null,
            );
            self::fail('Expected a duplicate slug conflict.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertSame(1, (int) $this->connection->query(
            'SELECT COUNT(*) FROM activities',
        )->fetchColumn());
        self::assertSame(0, (int) $this->connection->query(
            'SELECT COUNT(*) FROM audit_logs WHERE entity_type = \'activity\'',
        )->fetchColumn());
    }

    public function testNoOpStateRequestCommitsWithoutFalseAuditRecord(): void
    {
        $id = ActivityFixture::insert($this->connection, [
            'is_active' => 1,
            'is_published' => 0,
        ]);
        $service = $this->service(new PdoAuditLogRepository($this->connection));

        $result = $service->setActive(
            $id,
            true,
            $this->administratorId,
            null,
            null,
        );

        self::assertTrue($result->isActive());
        self::assertSame(0, (int) $this->connection->query(
            'SELECT COUNT(*) FROM audit_logs WHERE entity_type = \'activity\'',
        )->fetchColumn());
    }

    private function service(AuditLogWriteRepository $audit): AdministratorActivityService
    {
        return new AdministratorActivityService(
            $this->repository,
            $audit,
            new PdoActivityTransaction($this->connection),
            static fn (): DateTimeImmutable => new DateTimeImmutable(
                '2026-07-30 09:00:00 UTC',
            ),
        );
    }

    private function request(): ActivityContentRequest
    {
        return ActivityContentRequest::fromInput(
            'Jungle Walk',
            'jungle-walk',
            'Explore the forest with a local guide.',
            'Explore the surrounding forest.',
            '2500.00',
            'per person',
            '3 hours',
            'Dhulikhel',
            10,
            true,
            1,
        );
    }

    private function insertAdministrator(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) VALUES ('Administrator', 'Test role')",
        );
        $roleId = (int) $this->connection->lastInsertId();
        $statement = $this->connection->prepare(
            'INSERT INTO users ('
            . 'role_id, full_name, username, email, password, account_status'
            . ') VALUES ('
            . ':role_id, :full_name, :username, :email, :password, :account_status'
            . ')',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Administrator',
            'username' => 'activity-admin',
            'email' => 'activity-admin@example.test',
            'password' => password_hash('Test-only-password-123!', PASSWORD_DEFAULT),
            'account_status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }
}

final class AdministratorActivityFailingAuditRepository implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new RuntimeException('sensitive audit storage failure');
    }
}
