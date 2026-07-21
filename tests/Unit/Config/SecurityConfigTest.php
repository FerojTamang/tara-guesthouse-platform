<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Core\Config;
use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityConfigTest extends TestCase
{
    public function testItLoadsTheExactTrackedAdministratorBootstrapPolicy(): void
    {
        $configuration = $this->loadActual([
            'ADMINISTRATOR_PASSWORD_MIN_CHARACTERS' => '1',
            'ADMINISTRATOR_PASSWORD_MAX_BYTES' => '4096',
        ]);

        self::assertSame([
            'allowed_environments' => ['local', 'testing', 'staging', 'production'],
            'force_required_environments' => ['staging', 'production'],
            'full_name_min_characters' => 2,
            'full_name_max_characters' => 100,
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'email_max_bytes' => 150,
            'phone_max_characters' => 20,
            'password_min_characters' => 15,
            'password_max_bytes' => 72,
        ], $configuration['administrator_bootstrap']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function malformedPolicies(): iterable
    {
        $valid = self::validConfiguration();

        yield 'missing group' => [[]];
        yield 'group is scalar' => [['administrator_bootstrap' => 'invalid']];

        $missingKey = $valid;
        unset($missingKey['administrator_bootstrap']['password_max_bytes']);
        yield 'missing key' => [$missingKey];

        $wrongType = $valid;
        $wrongType['administrator_bootstrap']['password_min_characters'] = '15';
        yield 'numeric string' => [$wrongType];

        $invalidRange = $valid;
        $invalidRange['administrator_bootstrap']['full_name_min_characters'] = 101;
        yield 'invalid range' => [$invalidRange];

        $unsupported = $valid;
        $unsupported['administrator_bootstrap']['allowed_environments'][] = 'preview';
        yield 'unsupported environment' => [$unsupported];

        $forceOutsideAllowed = $valid;
        $forceOutsideAllowed['administrator_bootstrap']['allowed_environments'] = ['local'];
        yield 'force environment outside allowed set' => [$forceOutsideAllowed];

        $duplicateEnvironment = $valid;
        $duplicateEnvironment['administrator_bootstrap']['allowed_environments'][] = 'local';
        yield 'duplicate environment' => [$duplicateEnvironment];

        $unsafeMaximum = $valid;
        $unsafeMaximum['administrator_bootstrap']['password_max_bytes'] = 73;
        yield 'password maximum exceeds bcrypt boundary' => [$unsafeMaximum];
    }

    /** @param array<string, mixed> $configuration */
    #[DataProvider('malformedPolicies')]
    public function testMalformedSecurityConfigurationFailsClosed(array $configuration): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-security-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $path = $directory . DIRECTORY_SEPARATOR . 'security.php';
        file_put_contents($path, "<?php\nreturn " . var_export($configuration, true) . ";\n");

        try {
            $this->expectException(ConfigurationException::class);
            Config::loadSecurity($directory, Environment::fromArray([]));
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    /** @param array<string, string> $environment */
    private function loadActual(array $environment = []): array
    {
        return Config::loadSecurity(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config',
            Environment::fromArray($environment),
        );
    }

    /** @return array<string, mixed> */
    private static function validConfiguration(): array
    {
        return [
            'administrator_bootstrap' => [
                'allowed_environments' => ['local', 'testing', 'staging', 'production'],
                'force_required_environments' => ['staging', 'production'],
                'full_name_min_characters' => 2,
                'full_name_max_characters' => 100,
                'username_min_characters' => 3,
                'username_max_characters' => 50,
                'email_max_bytes' => 150,
                'phone_max_characters' => 20,
                'password_min_characters' => 15,
                'password_max_bytes' => 72,
            ],
        ];
    }
}
