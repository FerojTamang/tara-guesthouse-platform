<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\ConnectionFactory;
use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class MigrationRunnerTest extends DatabaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupTables();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-db-migrations-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->cleanupTables();
        }

        if (isset($this->directory) && is_dir($this->directory)) {
            foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function testStatusCreatesOnlyTheMigrationHistoryTable(): void
    {
        $status = (new MigrationRunner($this->connection, $this->directory))->status();

        self::assertSame([], $status);
        self::assertSame(1, (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'",
        )->fetchColumn());
    }

    public function testMigrateIsOrderedRepeatableAndLastBatchRollbackIsReversed(): void
    {
        $this->writeOrderMigrations();
        $runner = new MigrationRunner($this->connection, $this->directory);

        self::assertSame(
            ['20260101000001_create_order_fixture', '20260101000002_extend_order_fixture'],
            $runner->migrate(),
        );
        self::assertSame([], $runner->migrate());
        self::assertSame(['applied', 'applied'], array_column($runner->status(), 'status'));
        self::assertSame(
            ['20260101000002_extend_order_fixture', '20260101000001_create_order_fixture'],
            $runner->rollbackLastBatch(),
        );
        self::assertSame(
            ['first_up', 'second_up', 'second_down', 'first_down'],
            $this->connection->query('SELECT event FROM s0004_migration_order ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN),
        );
        self::assertSame(['pending', 'pending'], array_column($runner->status(), 'status'));
    }

    public function testFailedMigrationIsNotRecorded(): void
    {
        $this->writeMigration('20260101000001_failed_fixture.php', <<<'PHP'
public function up(\PDO $connection): void
{
    $connection->exec('CREATE TABLE s0004_failed (id INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    throw new \RuntimeException('fixture failure');
}
public function down(\PDO $connection): void { $connection->exec('DROP TABLE IF EXISTS s0004_failed'); }
PHP);
        $runner = new MigrationRunner($this->connection, $this->directory);

        try {
            $runner->migrate();
            self::fail('The failing migration unexpectedly succeeded.');
        } catch (DatabaseException) {
            self::assertSame(0, (int) $this->connection->query(
                "SELECT COUNT(*) FROM schema_migrations WHERE migration = '20260101000001_failed_fixture'",
            )->fetchColumn());
        }
    }

    public function testExistingCallerTransactionIsRefusedWithoutBeingRolledBack(): void
    {
        $this->connection->beginTransaction();

        try {
            try {
                (new MigrationRunner($this->connection, $this->directory))->migrate();
                self::fail('Migration execution accepted an existing transaction.');
            } catch (DatabaseException) {
                self::assertTrue($this->connection->inTransaction());
            }
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }

    public function testChecksumChangesAreRejectedAfterApplication(): void
    {
        $path = $this->writeMigration('20260101000001_checksum_fixture.php', <<<'PHP'
public function up(\PDO $connection): void { $connection->exec('CREATE TABLE s0004_checksum (id INT PRIMARY KEY) ENGINE=InnoDB'); }
public function down(\PDO $connection): void { $connection->exec('DROP TABLE IF EXISTS s0004_checksum'); }
PHP);
        $runner = new MigrationRunner($this->connection, $this->directory);
        $runner->migrate();
        file_put_contents($path, "\n// changed after application\n", FILE_APPEND);

        $this->expectException(DatabaseException::class);
        $runner->status();
    }

    public function testAdvisoryLockPreventsConcurrentMigration(): void
    {
        $secondConnection = (new ConnectionFactory($this->databaseConfiguration))->create();
        $lockName = 'tgh_migrations_' . substr(hash('sha256', $this->databaseConfiguration['database']), 0, 40);
        $statement = $secondConnection->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => $lockName]);
        self::assertSame(1, (int) $statement->fetchColumn());

        try {
            $this->expectException(DatabaseException::class);
            (new MigrationRunner($this->connection, $this->directory))->migrate();
        } finally {
            $statement = $secondConnection->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $statement->execute(['lock_name' => $lockName]);
        }
    }

    public function testFixtureConstraintsAreEnforcedAndRollbackRemovesThem(): void
    {
        $this->writeMigration('20260101000001_constraint_fixture.php', <<<'PHP'
public function up(\PDO $connection): void
{
    $connection->exec('CREATE TABLE s0004_parent (id INT UNSIGNED NOT NULL PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE) ENGINE=InnoDB');
    $connection->exec('CREATE TABLE s0004_child (id INT UNSIGNED NOT NULL PRIMARY KEY, parent_id INT UNSIGNED NOT NULL, CONSTRAINT fk_s0004_child_parent FOREIGN KEY (parent_id) REFERENCES s0004_parent(id)) ENGINE=InnoDB');
}
public function down(\PDO $connection): void
{
    $connection->exec('DROP TABLE s0004_child');
    $connection->exec('DROP TABLE s0004_parent');
}
PHP);
        $runner = new MigrationRunner($this->connection, $this->directory);
        $runner->migrate();
        $this->connection->exec("INSERT INTO s0004_parent (id, code) VALUES (1, 'unique')");

        try {
            $this->connection->exec("INSERT INTO s0004_parent (id, code) VALUES (2, 'unique')");
            self::fail('The unique constraint was not enforced.');
        } catch (PDOException) {
            self::assertTrue(true);
        }

        try {
            $this->connection->exec('INSERT INTO s0004_child (id, parent_id) VALUES (1, 999)');
            self::fail('The foreign key was not enforced.');
        } catch (PDOException) {
            self::assertTrue(true);
        }

        $runner->rollbackLastBatch();
        self::assertSame(0, (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('s0004_parent', 's0004_child')",
        )->fetchColumn());
    }

    private function writeOrderMigrations(): void
    {
        $this->writeMigration('20260101000001_create_order_fixture.php', <<<'PHP'
public function up(\PDO $connection): void
{
    $connection->exec('CREATE TABLE s0004_migration_order (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, event VARCHAR(32) NOT NULL UNIQUE) ENGINE=InnoDB');
    $connection->exec("INSERT INTO s0004_migration_order (event) VALUES ('first_up')");
}
public function down(\PDO $connection): void { $connection->exec("INSERT INTO s0004_migration_order (event) VALUES ('first_down')"); }
PHP);
        $this->writeMigration('20260101000002_extend_order_fixture.php', <<<'PHP'
public function up(\PDO $connection): void { $connection->exec("INSERT INTO s0004_migration_order (event) VALUES ('second_up')"); }
public function down(\PDO $connection): void { $connection->exec("INSERT INTO s0004_migration_order (event) VALUES ('second_down')"); }
PHP);
    }

    private function writeMigration(string $filename, string $methods): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, <<<PHP
<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
{$methods}
};
PHP);

        return $path;
    }

    private function cleanupTables(): void
    {
        $this->dropTables([
            's0004_child',
            's0004_parent',
            's0004_checksum',
            's0004_failed',
            's0004_migration_order',
            'schema_migrations',
        ]);
    }
}
