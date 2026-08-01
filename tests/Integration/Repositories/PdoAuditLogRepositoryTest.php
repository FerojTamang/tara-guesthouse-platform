<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\AuditLogEntry;
use App\Repositories\PdoAuditLogRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PdoAuditLogRepositoryTest extends DatabaseTestCase
{
    private PdoAuditLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner($this->connection, dirname(__DIR__, 3) . '/database/migrations'))->migrate();
        $this->repository = new PdoAuditLogRepository($this->connection);
    }

    public function testAppendRequiresCallerOwnedTransactionAndPersistsOneEntry(): void
    {
        $entry = new AuditLogEntry(null, 'contact_message_submitted', 'contact_message', 17, 'Guest submitted a contact message.', '{"contact_message_id":17}', '203.0.113.17', 'Audit integration test', new DateTimeImmutable('2026-08-01 12:00:00 UTC'));
        $this->expectException(DatabaseException::class);
        $this->repository->append($entry);
    }

    public function testAppendRoundTripsMetadataInsideTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->repository->append(new AuditLogEntry(null, 'contact_message_submitted', 'contact_message', 17, 'Guest submitted a contact message.', '{"contact_message_id":17}', null, null, new DateTimeImmutable('2026-08-01 12:00:00 UTC')));
        $this->connection->commit();
        $row = $this->connection->query('SELECT event_type, entity_type, entity_id, metadata_json FROM audit_logs')->fetch();
        self::assertIsArray($row);
        self::assertSame('contact_message_submitted', $row['event_type']);
        self::assertSame('contact_message', $row['entity_type']);
        self::assertSame('17', (string) $row['entity_id']);
        self::assertSame('{"contact_message_id":17}', $row['metadata_json']);
    }
}
