<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class PublicDocumentSecurityTest extends TestCase
{
    public function testApacheFrontControllerDeniesHiddenAndExecutablePublicPaths(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
        $configuration = file_get_contents($root . DIRECTORY_SEPARATOR . '.htaccess');

        self::assertIsString($configuration);
        self::assertStringContainsString('Options -Indexes -MultiViews', $configuration);
        self::assertStringContainsString('Header always unset X-Powered-By', $configuration);
        self::assertStringContainsString('RewriteCond %{REQUEST_URI} !^/index\\.php$', $configuration);
        self::assertStringContainsString('php(?:[0-9]+|s)?', $configuration);
        self::assertStringContainsString('well-known', $configuration);
    }

    public function testRuntimeUploadRootsCarryExecutableFileDenialRules(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';

        foreach (['uploads', 'payment-methods'] as $directory) {
            $path = $root . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . '.htaccess';
            $configuration = file_get_contents($path);

            self::assertIsString($configuration, $directory);
            self::assertStringContainsString('Require all denied', $configuration, $directory);
            self::assertStringContainsString('RemoveHandler .php', $configuration, $directory);
            self::assertStringContainsString('Options -Indexes', $configuration, $directory);
        }
    }
}
