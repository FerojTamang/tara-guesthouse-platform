<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class BookingConfigTest extends TestCase
{
    public function testLocalDefaultsUseTheApprovedDeadlineWithoutInventingASecret(): void
    {
        $configuration = $this->load(['APP_ENV' => 'local']);

        self::assertSame(360, $configuration['pending_deadline_minutes']);
        self::assertNull($configuration['access_token_key']);
    }

    public function testTestingUsesAStableNonProductionSigningKey(): void
    {
        $configuration = $this->load(['APP_ENV' => 'testing']);

        self::assertSame(360, $configuration['pending_deadline_minutes']);
        self::assertIsString($configuration['access_token_key']);
        self::assertSame(64, strlen($configuration['access_token_key']));
    }

    public function testExplicitApprovedValuesAreLoadedExactly(): void
    {
        $key = str_repeat('k', 32);
        $configuration = $this->load([
            'APP_ENV' => 'production',
            'BOOKING_PENDING_DEADLINE_MINUTES' => '360',
            'BOOKING_ACCESS_TOKEN_KEY' => $key,
        ]);

        self::assertSame(360, $configuration['pending_deadline_minutes']);
        self::assertSame($key, $configuration['access_token_key']);
    }

    public function testProtectedEnvironmentsKeepTheApprovedDefaultAndDeferMissingSecrets(): void
    {
        foreach (['staging', 'production'] as $environmentName) {
            $configuration = $this->load([
                'APP_ENV' => $environmentName,
            ]);

            self::assertSame(360, $configuration['pending_deadline_minutes']);
            self::assertNull($configuration['access_token_key']);
        }
    }

    public function testMalformedDeadlineAndSigningKeysFailClosed(): void
    {
        foreach ([
            [
                'APP_ENV' => 'local',
                'BOOKING_PENDING_DEADLINE_MINUTES' => '0',
            ],
            [
                'APP_ENV' => 'local',
                'BOOKING_PENDING_DEADLINE_MINUTES' => ' 360 ',
            ],
            [
                'APP_ENV' => 'local',
                'BOOKING_ACCESS_TOKEN_KEY' => 'short',
            ],
            [
                'APP_ENV' => 'local',
                'BOOKING_ACCESS_TOKEN_KEY' => str_repeat('k', 31) . "\n",
            ],
        ] as $environment) {
            try {
                $this->load($environment);
                self::fail('Expected malformed booking configuration to fail.');
            } catch (ConfigurationException) {
                self::assertTrue(true);
            }
        }
    }

    /**
     * @param array<string, string> $values
     * @return array<string, mixed>
     */
    private function load(array $values): array
    {
        $environment = Environment::fromArray($values);

        /** @var array<string, mixed> $configuration */
        $configuration = require dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR
            . 'config'
            . DIRECTORY_SEPARATOR
            . 'booking.php';

        return $configuration;
    }
}
