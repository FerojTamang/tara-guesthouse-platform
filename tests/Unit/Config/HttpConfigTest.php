<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class HttpConfigTest extends TestCase
{
    public function testTestingPolicyIsCompatibleWithLocalHttpAssetsWithoutHsts(): void
    {
        $configuration = $this->load('testing')['security_headers'];

        self::assertNull($configuration['strict_transport_security']);
        self::assertStringContainsString("img-src 'self' data: http: https:", $configuration['content_security_policy']);
        self::assertStringNotContainsString('upgrade-insecure-requests', $configuration['content_security_policy']);
    }

    public function testProductionPolicyEnforcesHttpsUpgradesAndHsts(): void
    {
        $configuration = $this->load('production')['security_headers'];

        self::assertSame('max-age=31536000', $configuration['strict_transport_security']);
        self::assertStringContainsString("img-src 'self' data: https:", $configuration['content_security_policy']);
        self::assertStringNotContainsString(' data: http: https:', $configuration['content_security_policy']);
        self::assertStringContainsString('upgrade-insecure-requests', $configuration['content_security_policy']);
        self::assertStringContainsString('camera=()', $configuration['permissions_policy']);
    }

    public function testUnsupportedEnvironmentFailsClosed(): void
    {
        $environment = Environment::fromArray(['APP_ENV' => 'preview']);

        $this->expectException(ConfigurationException::class);
        require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'http.php';
    }

    /** @return array<string, mixed> */
    private function load(string $environmentName): array
    {
        $environment = Environment::fromArray(['APP_ENV' => $environmentName]);

        return require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'http.php';
    }
}
