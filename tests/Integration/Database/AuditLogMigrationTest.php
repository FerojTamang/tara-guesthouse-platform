<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AuditLogMigrationTest extends DatabaseTestCase
{
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
    }

    public function testAuditLogMigrationExposesTheApprovedAppendOnlySchema(): void
    {
        self::assertSame([
            'id',
            'user_id',
            'event_type',
            'entity_type',
            'entity_id',
            'description',
            'metadata_json',
            'ip_address',
            'user_agent',
            'created_at',
        ], array_keys($this->columns('audit_logs')));

        self::assertSame('InnoDB', $this->tableOption('audit_logs', 'ENGINE'));
        self::assertSame(
            'utf8mb4_unicode_ci',
            $this->tableOption('audit_logs', 'TABLE_COLLATION'),
        );
        self::assertSame(
            ['user_id'],
            $this->indexColumns('audit_logs', 'idx_audit_logs_user'),
        );
        self::assertSame(
            ['event_type', 'created_at', 'id'],
            $this->indexColumns(
                'audit_logs',
                'idx_audit_logs_event_created',
            ),
        );
        self::assertSame(
            ['entity_type', 'entity_id', 'created_at', 'id'],
            $this->indexColumns(
                'audit_logs',
                'idx_audit_logs_entity_created',
            ),
        );
        self::assertSame(
            ['created_at', 'id'],
            $this->indexColumns('audit_logs', 'idx_audit_logs_created'),
        );
        self::assertSame(
            ['RESTRICT', 'RESTRICT'],
            $this->referentialRules('fk_audit_logs_user'),
        );
    }

    public function testAuditConstraintsAndUserRetentionAreEnforced(): void
    {
        $roleId = $this->insertRole();
        $userId = $this->insertUser($roleId);
        $auditId = $this->insertAudit($userId);

        self::assertGreaterThan(0, $auditId);
        $this->assertPdoFailure(fn () => $this->insertAudit(
            $userId,
            ['metadata_json' => '{invalid'],
        ));
        $this->assertPdoFailure(fn () => $this->insertAudit(
            $userId,
            ['event_type' => '   '],
        ));
        $this->assertPdoFailure(fn () => $this->insertAudit(
            $userId,
            ['entity_type' => '   '],
        ));
        $this->assertPdoFailure(fn () => $this->insertAudit(
            $userId,
            ['description' => '   '],
        ));
        $this->assertPdoFailure(
            fn () => $this->connection->exec(
                'DELETE FROM users WHERE id = ' . $userId,
            ),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, '
            . 'COLUMN_DEFAULT, EXTRA, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME '
            . 'FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);
        $columns = [];

        foreach ($statement->fetchAll() as $column) {
            self::assertIsArray($column);
            $columns[$column['COLUMN_NAME']] = $column;
        }

        return $columns;
    }

    private function tableOption(string $table, string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return (string) $row[$column];
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX',
        );
        $statement->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    /** @return array{string, string} */
    private function referentialRules(string $constraint): array
    {
        $statement = $this->connection->prepare(
            'SELECT UPDATE_RULE, DELETE_RULE '
            . 'FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['constraint_name' => $constraint]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return [(string) $row['UPDATE_RULE'], (string) $row['DELETE_RULE']];
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) "
            . "VALUES ('Administrator', 'Audit migration test')",
        );

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(int $roleId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users ('
            . 'role_id, full_name, username, email, password, account_status'
            . ') VALUES ('
            . ':role_id, :full_name, :username, :email, :password, :account_status'
            . ')',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Audit Administrator',
            'username' => 'audit-administrator',
            'email' => 'audit-administrator@example.test',
            'password' => 'test-only-password-hash',
            'account_status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $overrides */
    private function insertAudit(int $userId, array $overrides = []): int
    {
        $values = array_replace([
            'user_id' => $userId,
            'event_type' => 'booking_status_changed',
            'entity_type' => 'booking',
            'entity_id' => 31,
            'description' => 'Administrator changed a booking status.',
            'metadata_json' => '{"from_status":"pending","to_status":"cancelled"}',
            'ip_address' => '203.0.113.40',
            'user_agent' => 'Audit Test Agent',
            'created_at' => '2026-08-01 12:00:00',
        ], $overrides);
        $statement = $this->connection->prepare(
            'INSERT INTO audit_logs ('
            . 'user_id, event_type, entity_type, entity_id, description, '
            . 'metadata_json, ip_address, user_agent, created_at'
            . ') VALUES ('
            . ':user_id, :event_type, :entity_type, :entity_id, :description, '
            . ':metadata_json, :ip_address, :user_agent, :created_at'
            . ')',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected the database operation to fail.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }
}
