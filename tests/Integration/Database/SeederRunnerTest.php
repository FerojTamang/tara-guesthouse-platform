<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\SeederRunner;
use App\Exceptions\DatabaseException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class SeederRunnerTest extends DatabaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropTables(['s0004_seed_fixture']);
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-db-seeders-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));

        try {
            $this->connection->exec(
                'CREATE TABLE s0004_seed_fixture ('
                . 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,'
                . 'value_name VARCHAR(64) NOT NULL UNIQUE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            );
        } catch (\Throwable $throwable) {
            rmdir($this->directory);
            throw $throwable;
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->dropTables(['s0004_seed_fixture']);
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

    public function testTestOnlySeedersExecuteInDeterministicOrder(): void
    {
        $this->writeSeeder('b_second_fixture.php', 'testing', "\$connection->exec(\"INSERT INTO s0004_seed_fixture (value_name) VALUES ('second')\");");
        $this->writeSeeder('a_first_fixture.php', 'testing', "\$connection->exec(\"INSERT INTO s0004_seed_fixture (value_name) VALUES ('first')\");");
        $this->writeSeeder('c_development_fixture.php', 'development', "\$connection->exec(\"INSERT INTO s0004_seed_fixture (value_name) VALUES ('development')\");");

        $executed = (new SeederRunner($this->connection, $this->directory))->run('testing', 'testing');

        self::assertSame(['a_first_fixture', 'b_second_fixture'], $executed);
        self::assertSame(
            ['first', 'second'],
            $this->connection->query('SELECT value_name FROM s0004_seed_fixture ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN),
        );
    }

    public function testFailedTransactionalSeederRollsBackAllItsData(): void
    {
        $this->writeSeeder(
            'a_failed_fixture.php',
            'testing',
            "\$connection->exec(\"INSERT INTO s0004_seed_fixture (value_name) VALUES ('must_rollback')\"); throw new \\RuntimeException('fixture failure');",
        );

        try {
            (new SeederRunner($this->connection, $this->directory))->run('testing', 'testing');
            self::fail('The failing seeder unexpectedly succeeded.');
        } catch (DatabaseException) {
            self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM s0004_seed_fixture')->fetchColumn());
        }
    }

    public function testExistingCallerTransactionIsRefusedWithoutBeingRolledBack(): void
    {
        $this->connection->beginTransaction();

        try {
            try {
                (new SeederRunner($this->connection, $this->directory))->run('testing', 'testing');
                self::fail('Seeder execution accepted an existing transaction.');
            } catch (DatabaseException) {
                self::assertTrue($this->connection->inTransaction());
            }
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        }
    }

    private function writeSeeder(string $filename, string $group, string $body): void
    {
        $escapedGroup = var_export($group, true);
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $filename, <<<PHP
<?php

declare(strict_types=1);

use App\Core\Seeder;

return new class extends Seeder {
    public function group(): string { return {$escapedGroup}; }
    public function run(\PDO \$connection): void { {$body} }
};
PHP);
    }
}
