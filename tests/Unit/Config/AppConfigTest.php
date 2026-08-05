<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testLocalConfigurationAcceptsAPlainHttpOriginAndOnlyLocalDebug(): void
    {
        $configuration = $this->load([
            'APP_ENV' => 'local',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'http://localhost:8000/',
        ]);

        self::assertTrue($configuration['debug']);
        self::assertSame('http://localhost:8000/', $configuration['url']);
    }

    public function testProductionRequiresAnHttpsOriginAndNeverEnablesDebug(): void
    {
        $configuration = $this->load([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'https://guesthouse.example',
        ]);

        self::assertFalse($configuration['debug']);
        self::assertSame('https://guesthouse.example', $configuration['url']);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidOrigins(): iterable
    {
        yield 'missing production origin' => [[
            'APP_ENV' => 'production',
            'APP_URL' => '',
        ]];
        yield 'plain http in staging' => [[
            'APP_ENV' => 'staging',
            'APP_URL' => 'http://guesthouse.example',
        ]];
        yield 'origin with credentials' => [[
            'APP_URL' => 'https://user:secret@guesthouse.example',
        ]];
        yield 'origin with path' => [[
            'APP_URL' => 'https://guesthouse.example/app',
        ]];
        yield 'origin with query' => [[
            'APP_URL' => 'https://guesthouse.example/?debug=1',
        ]];
        yield 'unsupported scheme' => [[
            'APP_URL' => 'javascript://guesthouse.example',
        ]];
    }

    /** @param array<string, string> $overrides */
    #[DataProvider('invalidOrigins')]
    public function testInvalidApplicationOriginsFailClosed(array $overrides): void
    {
        $this->expectException(ConfigurationException::class);
        $this->load($overrides);
    }

    /** @param array<string, string> $overrides
     *  @return array<string, mixed>
     */
    private function load(array $overrides): array
    {
        $environment = Environment::fromArray($overrides + [
            'APP_NAME' => 'Tara Guest House Test',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'Asia/Kathmandu',
        ]);

        return require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php';
    }
}
