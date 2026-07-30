<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

final class GalleryImageUploadConfigTest extends TestCase
{
    public function testTrackedConfigurationContainsOnlyGateKeysAndPrivatePaths(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $gallery = $configuration['gallery_images'] ?? null;

        self::assertSame([
            'allowed_formats_environment_key' => 'GALLERY_IMAGE_FORMATS',
            'maximum_file_size_environment_key' => 'GALLERY_IMAGE_MAX_BYTES',
            'minimum_width_environment_key' => 'GALLERY_IMAGE_MIN_WIDTH',
            'minimum_height_environment_key' => 'GALLERY_IMAGE_MIN_HEIGHT',
            'maximum_width_environment_key' => 'GALLERY_IMAGE_MAX_WIDTH',
            'maximum_height_environment_key' => 'GALLERY_IMAGE_MAX_HEIGHT',
            'require_decodability_environment_key' => 'GALLERY_IMAGE_REQUIRE_DECODABILITY',
            'filesystem_root' => 'storage/private/gallery',
            'database_path_prefix' => 'gallery/',
        ], $gallery);
        self::assertStringNotContainsString('public/', $gallery['filesystem_root']);
    }

    public function testExampleEnvironmentLeavesEveryDecisionGateUnapproved(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/.env.example');
        self::assertIsString($contents);

        foreach ([
            'GALLERY_IMAGE_FORMATS',
            'GALLERY_IMAGE_MAX_BYTES',
            'GALLERY_IMAGE_MIN_WIDTH',
            'GALLERY_IMAGE_MIN_HEIGHT',
            'GALLERY_IMAGE_MAX_WIDTH',
            'GALLERY_IMAGE_MAX_HEIGHT',
            'GALLERY_IMAGE_REQUIRE_DECODABILITY',
        ] as $key) {
            self::assertMatchesRegularExpression('/^' . $key . '=$/mD', $contents);
        }
    }

    public function testPrivateDirectoryContainsDefenseInDepthApacheDenial(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/storage/private/gallery/.htaccess');
        self::assertIsString($contents);
        self::assertStringContainsString('Require all denied', $contents);
        self::assertStringContainsString('Options -Indexes -ExecCGI', $contents);
        self::assertStringContainsString('RemoveHandler .php', $contents);

        $gitignore = file_get_contents(dirname(__DIR__, 3) . '/.gitignore');
        self::assertIsString($gitignore);
        self::assertStringContainsString('/storage/private/gallery/*', $gitignore);
        self::assertStringContainsString('!/storage/private/gallery/.htaccess', $gitignore);
    }
}
