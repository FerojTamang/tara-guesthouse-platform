<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\AuditLogEntry;
use PDO;
use Throwable;

/**
 * Persists append-only audit records inside a caller-owned transaction.
 */
final readonly class PdoAuditLogRepository implements AuditLogWriteRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function append(AuditLogEntry $entry): void
    {
        if (!$this->connection->inTransaction()) {
            throw new DatabaseException('The audit log write failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO audit_logs ('
                . 'user_id, event_type, entity_type, entity_id, description, '
                . 'metadata_json, ip_address, user_agent, created_at'
                . ') VALUES ('
                . ':user_id, :event_type, :entity_type, :entity_id, :description, '
                . ':metadata_json, :ip_address, :user_agent, :created_at'
                . ')',
            );
            $statement->execute([
                'user_id' => $entry->userId(),
                'event_type' => $entry->eventType(),
                'entity_type' => $entry->entityType(),
                'entity_id' => $entry->entityId(),
                'description' => $entry->description(),
                'metadata_json' => $entry->metadataJson(),
                'ip_address' => $entry->ipAddress(),
                'user_agent' => $entry->userAgent(),
                'created_at' => $entry->createdAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            throw new DatabaseException('The audit log write failed.');
        }
    }
}
