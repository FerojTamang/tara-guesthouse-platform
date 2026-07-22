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
    public function testItLoadsTheExactTrackedSecurityPolicy(): void
    {
        $configuration = $this->loadActual([
            'ADMINISTRATOR_PASSWORD_MIN_CHARACTERS' => '1',
            'ADMINISTRATOR_LOGIN_MAX_FAILED_ATTEMPTS' => '999',
        ]);

        self::assertSame(self::validConfiguration(), $configuration);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function malformedPolicies(): iterable
    {
        $valid = self::validConfiguration();

        yield 'missing groups' => [[]];
        yield 'unexpected group' => [$valid + ['other' => []]];

        $missingBootstrapKey = $valid;
        unset($missingBootstrapKey['administrator_bootstrap']['password_max_bytes']);
        yield 'missing bootstrap key' => [$missingBootstrapKey];

        $missingLoginKey = $valid;
        unset($missingLoginKey['administrator_login']['block_duration_seconds']);
        yield 'missing login key' => [$missingLoginKey];

        $wrongBootstrapType = $valid;
        $wrongBootstrapType['administrator_bootstrap']['password_min_characters'] = '15';
        yield 'bootstrap numeric string' => [$wrongBootstrapType];

        $wrongLoginType = $valid;
        $wrongLoginType['administrator_login']['max_failed_attempts'] = '5';
        yield 'login numeric string' => [$wrongLoginType];

        $invalidBootstrapRange = $valid;
        $invalidBootstrapRange['administrator_bootstrap']['full_name_min_characters'] = 101;
        yield 'invalid bootstrap range' => [$invalidBootstrapRange];

        $invalidLoginRange = $valid;
        $invalidLoginRange['administrator_login']['username_min_characters'] = 51;
        yield 'invalid login range' => [$invalidLoginRange];

        $unsupported = $valid;
        $unsupported['administrator_bootstrap']['allowed_environments'][] = 'preview';
        yield 'unsupported environment' => [$unsupported];

        $forceOutsideAllowed = $valid;
        $forceOutsideAllowed['administrator_bootstrap']['allowed_environments'] = ['local'];
        yield 'force environment outside allowed set' => [$forceOutsideAllowed];

        $duplicateEnvironment = $valid;
        $duplicateEnvironment['administrator_bootstrap']['allowed_environments'][] = 'local';
        yield 'duplicate environment' => [$duplicateEnvironment];

        $unsafePasswordMaximum = $valid;
        $unsafePasswordMaximum['administrator_login']['password_max_bytes'] = 73;
        yield 'login password maximum exceeds bcrypt boundary' => [$unsafePasswordMaximum];

        $unsafeAttemptMaximum = $valid;
        $unsafeAttemptMaximum['administrator_login']['max_failed_attempts'] = 21;
        yield 'excessive login attempt maximum' => [$unsafeAttemptMaximum];

        $unsafeWindow = $valid;
        $unsafeWindow['administrator_login']['attempt_window_seconds'] = 86401;
        yield 'excessive attempt window' => [$unsafeWindow];
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
            'administrator_login' => [
                'username_min_characters' => 3,
                'username_max_characters' => 50,
                'identifier_max_bytes' => 150,
                'password_max_bytes' => 72,
                'max_failed_attempts' => 5,
                'attempt_window_seconds' => 900,
                'block_duration_seconds' => 900,
            ],
        ];
    }
}
