<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-migrations-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function testDiscoveryUsesLexicalOrderAndCalculatesChecksums(): void
    {
        $this->writeMigration('20260101000002_second_change.php');
        $this->writeMigration('20260101000001_first_change.php');

        $migrations = (new MigrationRunner($this->unusedPdo(), $this->directory))->discover();

        self::assertSame(
            ['20260101000001_first_change', '20260101000002_second_change'],
            array_keys($migrations),
        );
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $migrations['20260101000001_first_change']['checksum']);
    }

    public function testInvalidMigrationFilenameIsRejected(): void
    {
        $this->writeMigration('20260101000001_InvalidName.php');

        $this->expectException(DatabaseException::class);
        (new MigrationRunner($this->unusedPdo(), $this->directory))->discover();
    }

    public function testMigrationDescriptionMustBeginWithALowercaseLetter(): void
    {
        $this->writeMigration('20260101000001_123_invalid.php');

        $this->expectException(DatabaseException::class);
        (new MigrationRunner($this->unusedPdo(), $this->directory))->discover();
    }

    public function testDuplicateMigrationTimestampIsRejected(): void
    {
        $this->writeMigration('20260101000001_first_change.php');
        $this->writeMigration('20260101000001_second_change.php');

        $this->expectException(DatabaseException::class);
        (new MigrationRunner($this->unusedPdo(), $this->directory))->discover();
    }

    public function testMigrationMustReturnAMigrationInstance(): void
    {
        file_put_contents($this->directory . '/20260101000001_invalid_return.php', "<?php\nreturn new stdClass();\n");

        $this->expectException(DatabaseException::class);
        (new MigrationRunner($this->unusedPdo(), $this->directory))->discover();
    }

    public function testMigrationFileOutputIsSuppressed(): void
    {
        $path = $this->writeMigration('20260101000001_quiet_change.php');
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        file_put_contents($path, str_replace("declare(strict_types=1);", "declare(strict_types=1);\necho 'must-not-leak';", $contents));
        ob_start();

        try {
            (new MigrationRunner($this->unusedPdo(), $this->directory))->discover();
            self::assertSame('', ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }

    public function testMigrationSymlinksCannotEscapeTheConfiguredDirectory(): void
    {
        $outsideDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-migration-outside-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($outsideDirectory, 0700));
        $target = $outsideDirectory . DIRECTORY_SEPARATOR . 'target.php';
        file_put_contents($target, "<?php return null;\n");
        $link = $this->directory . DIRECTORY_SEPARATOR . '20260101000001_linked_change.php';

        if (!@symlink($target, $link)) {
            unlink($target);
            rmdir($outsideDirectory);
            self::markTestSkipped('Filesystem symlink creation is unavailable.');
        }

        try {
            $this->expectException(DatabaseException::class);
            (new MigrationRunner($this->unusedPdo(), $this->directory))->discover();
        } finally {
            if (is_link($link) || file_exists($link)) {
                unlink($link);
            }

            unlink($target);
            rmdir($outsideDirectory);
        }
    }

    public function testChecksumMismatchIsRejected(): void
    {
        $path = $this->writeMigration('20260101000001_first_change.php');
        $pdo = $this->historyPdo([[
            'migration' => '20260101000001_first_change',
            'checksum' => str_repeat('0', 64),
            'batch' => 1,
            'executed_at' => '2026-01-01 00:00:00',
        ]]);

        self::assertNotSame(str_repeat('0', 64), hash_file('sha256', $path));
        $this->expectException(DatabaseException::class);
        (new MigrationRunner($pdo, $this->directory))->status();
    }

    public function testMissingAppliedMigrationIsRejected(): void
    {
        $pdo = $this->historyPdo([[
            'migration' => '20260101000001_missing_change',
            'checksum' => str_repeat('0', 64),
            'batch' => 1,
            'executed_at' => '2026-01-01 00:00:00',
        ]]);

        $this->expectException(DatabaseException::class);
        (new MigrationRunner($pdo, $this->directory))->status();
    }

    private function writeMigration(string $filename): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, <<<'PHP'
<?php

declare(strict_types=1);

use App\Core\Migration;
return new class extends Migration {
    public function up(\PDO $connection): void {}
    public function down(\PDO $connection): void {}
};
PHP);

        return $path;
    }

    private function unusedPdo(): PDO
    {
        return new class extends PDO {
            public function __construct() {}
        };
    }

    /** @param list<array<string, mixed>> $rows */
    private function historyPdo(array $rows): PDO
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($rows);
        $pdo = $this->createStub(PDO::class);
        $pdo->method('exec')->willReturn(0);
        $pdo->method('query')->willReturn($statement);

        return $pdo;
    }
}
