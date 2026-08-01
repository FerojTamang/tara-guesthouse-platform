<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\Migration;
use App\Core\MigrationRunner;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class OperationalQueryIndexMigrationTest extends DatabaseTestCase
{
    private MigrationRunner $runner;
    private string $migrationPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrationPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database'
            . DIRECTORY_SEPARATOR . 'migrations';
        $this->runner = new MigrationRunner($this->connection, $this->migrationPath);
    }

    public function testMigrationAddsEachOperationalIndexOnceWithExactColumnOrder(): void
    {
        $this->runner->migrate();

        self::assertSame(
            ['deleted_at', 'operational_status', 'is_published', 'room_number', 'id'],
            $this->indexColumns('rooms', 'idx_rooms_public_order'),
        );
        self::assertSame(
            ['deleted_at', 'created_at', 'id'],
            $this->indexColumns('bookings', 'idx_bookings_deleted_created'),
        );
        self::assertSame(
            ['booking_status', 'deleted_at', 'created_at', 'id'],
            $this->indexColumns('bookings', 'idx_bookings_status_deleted_created'),
        );
        self::assertSame(
            ['deleted_at', 'check_out'],
            $this->indexColumns('bookings', 'idx_bookings_deleted_checkout'),
        );

        foreach ([
            ['rooms', 'idx_rooms_public_order'],
            ['bookings', 'idx_bookings_deleted_created'],
            ['bookings', 'idx_bookings_status_deleted_created'],
            ['bookings', 'idx_bookings_deleted_checkout'],
        ] as [$table, $index]) {
            self::assertSame(1, $this->indexCount($table, $index));
        }
    }

    public function testRollbackRemovesOnlyTheOperationalIndexesAndReapplyRestoresThem(): void
    {
        $this->runner->migrate();
        $migration = require $this->migrationPath
            . DIRECTORY_SEPARATOR . '20260801000400_add_operational_query_indexes.php';
        self::assertInstanceOf(Migration::class, $migration);

        $migration->down($this->connection);
        self::assertSame([], $this->indexColumns('rooms', 'idx_rooms_public_order'));
        self::assertSame([], $this->indexColumns('bookings', 'idx_bookings_deleted_created'));
        self::assertSame([], $this->indexColumns('bookings', 'idx_bookings_status_deleted_created'));
        self::assertSame([], $this->indexColumns('bookings', 'idx_bookings_deleted_checkout'));
        self::assertSame(['id'], $this->indexColumns('rooms', 'PRIMARY'));
        self::assertSame(['id'], $this->indexColumns('bookings', 'PRIMARY'));

        $migration->up($this->connection);
        self::assertSame(
            ['deleted_at', 'operational_status', 'is_published', 'room_number', 'id'],
            $this->indexColumns('rooms', 'idx_rooms_public_order'),
        );
        self::assertSame(
            ['booking_status', 'deleted_at', 'created_at', 'id'],
            $this->indexColumns('bookings', 'idx_bookings_status_deleted_created'),
        );
    }

    public function testRepresentativePlansRecognizeTheOperationalIndexesWithoutFixedRowEstimates(): void
    {
        $this->runner->migrate();

        $this->assertPossibleKey(
            'SELECT id FROM rooms WHERE deleted_at IS NULL '
                . 'AND operational_status = :operational_status '
                . 'AND is_published = :is_published ORDER BY room_number ASC, id ASC',
            'idx_rooms_public_order',
            ['operational_status' => 'active', 'is_published' => 1],
        );
        $this->assertPossibleKey(
            'SELECT id FROM bookings WHERE deleted_at IS NULL ORDER BY created_at DESC, id DESC',
            'idx_bookings_deleted_created',
        );
        $this->assertPossibleKey(
            'SELECT id FROM bookings WHERE booking_status = :booking_status '
                . 'AND deleted_at IS NULL ORDER BY created_at DESC, id DESC',
            'idx_bookings_status_deleted_created',
            ['booking_status' => 'pending'],
        );
        $this->assertPossibleKey(
            'SELECT COUNT(*) FROM bookings WHERE deleted_at IS NULL '
                . 'AND check_out >= :start_date AND check_out < :end_date',
            'idx_bookings_deleted_checkout',
            ['start_date' => '2026-08-01', 'end_date' => '2026-08-02'],
        );
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX',
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    private function indexCount(string $table, string $index): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND INDEX_NAME = :index_name',
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, int|string> $parameters */
    private function assertPossibleKey(string $sql, string $index, array $parameters = []): void
    {
        $statement = $this->connection->prepare('EXPLAIN ' . $sql);
        $statement->execute($parameters);
        $rows = $statement->fetchAll();
        self::assertIsArray($rows);
        self::assertNotSame([], $rows);
        $possible = implode(',', array_map(
            static fn (array $row): string => (string) ($row['possible_keys'] ?? ''),
            $rows,
        ));
        self::assertStringContainsString($index, $possible);
    }
}
