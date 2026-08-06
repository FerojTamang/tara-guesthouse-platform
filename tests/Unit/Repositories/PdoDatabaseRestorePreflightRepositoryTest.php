<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\PdoDatabaseRestorePreflightRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PdoDatabaseRestorePreflightRepositoryTest extends TestCase
{
    public function testItUsesParameterizedReadOnlyMetadataQueriesForOnlyTheConfiguredDatabase(): void
    {
        $databaseName = 'fake_restore_target';
        $existence = $this->createMock(PDOStatement::class);
        $existence->expects(self::once())
            ->method('execute')
            ->with(['database_name' => $databaseName])
            ->willReturn(true);
        $existence->expects(self::once())->method('fetchColumn')->willReturn('1');

        $objects = $this->createMock(PDOStatement::class);
        $objects->expects(self::once())
            ->method('execute')
            ->with(['database_name' => $databaseName])
            ->willReturn(true);
        $objects->expects(self::once())->method('fetch')->willReturn(['table_count' => '0', 'view_count' => '0']);

        $queries = [];
        $connection = $this->createMock(PDO::class);
        $connection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnCallback(static function (string $query) use (&$queries, $existence, $objects): PDOStatement {
                $queries[] = $query;

                return count($queries) === 1 ? $existence : $objects;
            });

        $result = (new PdoDatabaseRestorePreflightRepository($connection))->inspectTarget($databaseName);

        self::assertSame(['exists' => true, 'table_count' => 0, 'view_count' => 0], $result);
        self::assertCount(2, $queries);
        self::assertStringContainsString('information_schema.SCHEMATA', $queries[0]);
        self::assertStringContainsString('SCHEMA_NAME = :database_name', $queries[0]);
        self::assertStringContainsString('information_schema.TABLES', $queries[1]);
        self::assertStringContainsString('TABLE_SCHEMA = :database_name', $queries[1]);
        self::assertStringNotContainsString($databaseName, implode(' ', $queries));
        self::assertDoesNotMatchRegularExpression('/\b(?:DROP|CREATE|TRUNCATE|ALTER|GRANT)\b/i', implode(' ', $queries));
    }

    public function testItVerifiesRestoredObjectsAndMigrationIdentity(): void
    {
        $databaseName = 'fake_restore_target';
        $objects = $this->createMock(PDOStatement::class);
        $objects->expects(self::once())
            ->method('execute')
            ->with(['database_name' => $databaseName])
            ->willReturn(true);
        $objects->expects(self::once())->method('fetch')->willReturn([
            'table_count' => '41',
            'view_count' => '2',
        ]);

        $migrationTable = $this->createMock(PDOStatement::class);
        $migrationTable->expects(self::once())
            ->method('execute')
            ->with(['database_name' => $databaseName])
            ->willReturn(true);
        $migrationTable->expects(self::once())->method('fetchColumn')->willReturn('1');

        $migrationState = $this->createMock(PDOStatement::class);
        $migrationState->expects(self::once())->method('fetch')->willReturn([
            'migration_count' => '32',
            'latest_migration' => '20260805000300_expand_navigation_routes_for_publishing',
        ]);

        $queries = [];
        $connection = $this->createMock(PDO::class);
        $connection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnCallback(static function (string $query) use (&$queries, $objects, $migrationTable): PDOStatement {
                $queries[] = $query;

                return count($queries) === 1 ? $objects : $migrationTable;
            });
        $connection->expects(self::once())
            ->method('query')
            ->with('SELECT COUNT(*) AS migration_count, MAX(migration) AS latest_migration FROM schema_migrations')
            ->willReturn($migrationState);

        self::assertSame(
            [
                'table_count' => 41,
                'view_count' => 2,
                'migration_count' => 32,
                'latest_migration' => '20260805000300_expand_navigation_routes_for_publishing',
            ],
            (new PdoDatabaseRestorePreflightRepository($connection))->inspectRestoredTarget($databaseName),
        );
        self::assertStringContainsString("TABLE_NAME = 'schema_migrations'", $queries[1]);
        self::assertStringNotContainsString($databaseName, implode(' ', $queries));
    }
}
