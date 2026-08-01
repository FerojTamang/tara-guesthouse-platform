<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Core\Migration;
use PHPUnit\Framework\TestCase;

final class OperationalQueryIndexMigrationContractTest extends TestCase
{
    private string $path;
    private string $source;

    protected function setUp(): void
    {
        $this->path = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'database'
            . DIRECTORY_SEPARATOR . 'migrations'
            . DIRECTORY_SEPARATOR . '20260801000400_add_operational_query_indexes.php';
        $source = file_get_contents($this->path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testMigrationTargetsOnlyMeasuredTablesWithExactOrderedIndexes(): void
    {
        preg_match_all('/ALTER TABLE\s+([a-z_]+)/i', $this->source, $tables);
        self::assertSame(['rooms', 'bookings'], array_values(array_unique($tables[1])));

        preg_match_all('/ADD INDEX\s+([a-z_]+)\s*\(([^)]+)\)/is', $this->source, $matches, PREG_SET_ORDER);
        $indexes = [];
        foreach ($matches as $match) {
            $indexes[$match[1]] = array_values(array_filter(array_map(
                static fn (string $column): string => trim($column),
                explode(',', $match[2]),
            )));
        }

        self::assertSame([
            'idx_rooms_public_order' => [
                'deleted_at', 'operational_status', 'is_published', 'room_number', 'id',
            ],
            'idx_bookings_deleted_created' => ['deleted_at', 'created_at', 'id'],
            'idx_bookings_status_deleted_created' => [
                'booking_status', 'deleted_at', 'created_at', 'id',
            ],
            'idx_bookings_deleted_checkout' => ['deleted_at', 'check_out'],
        ], $indexes);
        self::assertCount(count(array_unique(array_keys($indexes))), $indexes);
    }

    public function testMigrationHasExactRollbackAndNoDataChangingStatement(): void
    {
        preg_match_all('/DROP INDEX\s+([a-z_]+)/i', $this->source, $matches);
        self::assertSame([
            'idx_bookings_deleted_checkout',
            'idx_bookings_status_deleted_created',
            'idx_bookings_deleted_created',
            'idx_rooms_public_order',
        ], $matches[1]);
        self::assertDoesNotMatchRegularExpression('/\b(?:INSERT|UPDATE|DELETE|TRUNCATE)\b/i', $this->source);
        self::assertStringNotContainsString('DROP TABLE', strtoupper($this->source));

        $migration = require $this->path;
        self::assertInstanceOf(Migration::class, $migration);
        self::assertFalse($migration->transactional());
    }
}
