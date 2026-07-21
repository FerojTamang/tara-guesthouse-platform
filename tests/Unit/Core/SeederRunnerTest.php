<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\SeederRunner;
use App\Exceptions\DatabaseException;
use PDO;
use PHPUnit\Framework\TestCase;

final class SeederRunnerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-seeders-' . bin2hex(random_bytes(8));
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

    public function testSeederDiscoveryIsOrderedAndGroupsAreExplicit(): void
    {
        $this->writeSeeder('second_fixture.php', 'testing');
        $this->writeSeeder('first_fixture.php', 'development');

        $seeders = (new SeederRunner($this->unusedPdo(), $this->directory))->discover();

        self::assertSame(['first_fixture', 'second_fixture'], array_keys($seeders));
        self::assertSame('development', $seeders['first_fixture']['group']);
        self::assertSame('testing', $seeders['second_fixture']['group']);
    }

    public function testInvalidSeederFilenameIsRejected(): void
    {
        $this->writeSeeder('InvalidSeeder.php', 'testing');

        $this->expectException(DatabaseException::class);
        (new SeederRunner($this->unusedPdo(), $this->directory))->discover();
    }

    public function testInvalidSeederGroupIsRejected(): void
    {
        $this->writeSeeder('invalid_group.php', 'unapproved');

        $this->expectException(DatabaseException::class);
        (new SeederRunner($this->unusedPdo(), $this->directory))->discover();
    }

    public function testSeederFileOutputIsSuppressed(): void
    {
        $this->writeSeeder('quiet_fixture.php', 'testing');
        $path = $this->directory . DIRECTORY_SEPARATOR . 'quiet_fixture.php';
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        file_put_contents($path, str_replace("declare(strict_types=1);", "declare(strict_types=1);\necho 'must-not-leak';", $contents));
        ob_start();

        try {
            (new SeederRunner($this->unusedPdo(), $this->directory))->discover();
            self::assertSame('', ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }

    public function testSeederSymlinksCannotEscapeTheConfiguredDirectory(): void
    {
        $outsideDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-seeder-outside-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($outsideDirectory, 0700));
        $target = $outsideDirectory . DIRECTORY_SEPARATOR . 'target.php';
        file_put_contents($target, "<?php return null;\n");
        $link = $this->directory . DIRECTORY_SEPARATOR . 'linked_fixture.php';

        if (!@symlink($target, $link)) {
            unlink($target);
            rmdir($outsideDirectory);
            self::markTestSkipped('Filesystem symlink creation is unavailable.');
        }

        try {
            $this->expectException(DatabaseException::class);
            (new SeederRunner($this->unusedPdo(), $this->directory))->discover();
        } finally {
            if (is_link($link) || file_exists($link)) {
                unlink($link);
            }

            unlink($target);
            rmdir($outsideDirectory);
        }
    }

    public function testProductionRefusesDevelopmentTestingAndDemoGroups(): void
    {
        foreach (['development', 'testing', 'demo'] as $group) {
            try {
                (new SeederRunner($this->unusedPdo(), $this->directory))->run($group, 'production');
                self::fail('Production accepted a non-production seeder group.');
            } catch (DatabaseException) {
                self::assertTrue(true);
            }
        }
    }

    private function writeSeeder(string $filename, string $group): void
    {
        $escapedGroup = var_export($group, true);
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $filename, <<<PHP
<?php

declare(strict_types=1);

use App\Core\Seeder;
return new class extends Seeder {
    public function group(): string { return {$escapedGroup}; }
    public function run(\PDO \$connection): void {}
};
PHP);
    }

    private function unusedPdo(): PDO
    {
        return new class extends PDO {
            public function __construct() {}
        };
    }
}
