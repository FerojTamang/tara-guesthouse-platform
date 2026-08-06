<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\DatabaseException;
use App\Repositories\PdoDatabaseBackupMetadataRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PdoDatabaseBackupMetadataRepositoryTest extends TestCase
{
    public function testItReturnsBoundedMigrationIdentityWithoutSecrets(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('fetch')->willReturn([
            'migration_count' => '32',
            'latest_migration' => '20260805000300_expand_navigation_routes_for_publishing',
        ]);

        $connection = $this->createMock(PDO::class);
        $connection->expects(self::once())
            ->method('query')
            ->with('SELECT COUNT(*) AS migration_count, MAX(migration) AS latest_migration FROM schema_migrations')
            ->willReturn($statement);

        self::assertSame(
            [
                'migration_count' => 32,
                'latest_migration' => '20260805000300_expand_navigation_routes_for_publishing',
            ],
            (new PdoDatabaseBackupMetadataRepository($connection))->read(),
        );
    }

    public function testItFailsClosedWhenMigrationIdentityIsUnavailable(): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetch')->willReturn([
            'migration_count' => '0',
            'latest_migration' => null,
        ]);

        $connection = $this->createStub(PDO::class);
        $connection->method('query')->willReturn($statement);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Database backup metadata could not be read.');

        (new PdoDatabaseBackupMetadataRepository($connection))->read();
    }
}
