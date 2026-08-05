<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ContentMediaException;
use App\Services\ContentMediaStorageProvider;
use App\Storage\LocalContentMediaStorage;
use PHPUnit\Framework\TestCase;

final class ContentMediaStorageProviderTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-content-media-provider-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->basePath . DIRECTORY_SEPARATOR . 'config', 0700, true));
        self::assertTrue(mkdir($this->basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'content', 0700, true));
        $this->writeConfiguration();
    }

    protected function tearDown(): void
    {
        @unlink($this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'content');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'public');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'config');
        @rmdir($this->basePath);
        parent::tearDown();
    }

    public function testItResolvesTheFixedPublicContentMediaPolicyAndStorage(): void
    {
        $provider = new ContentMediaStorageProvider($this->basePath);
        $configuration = $provider->uploadConfiguration();

        self::assertSame(5 * 1024 * 1024, $configuration['maximum_file_size_bytes']);
        self::assertSame(320, $configuration['minimum_width_pixels']);
        self::assertSame('public/uploads/content', $configuration['filesystem_root']);
        self::assertInstanceOf(LocalContentMediaStorage::class, $provider->storage());
        self::assertSame($provider->storage(), $provider->storage());
    }

    public function testMissingStorageDirectoryFailsClosed(): void
    {
        self::assertTrue(rmdir(
            $this->basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'content',
        ));

        $this->expectException(ContentMediaException::class);
        (new ContentMediaStorageProvider($this->basePath))->storage();
    }

    private function writeConfiguration(): void
    {
        $configuration = [
            'content_media' => [
                'allowed_mime_extensions' => [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/webp' => ['webp'],
                ],
                'maximum_file_size_bytes' => 5 * 1024 * 1024,
                'minimum_width_pixels' => 320,
                'minimum_height_pixels' => 180,
                'maximum_width_pixels' => 6000,
                'maximum_height_pixels' => 6000,
                'require_decodability' => true,
                'filesystem_root' => 'public/uploads/content',
                'database_path_prefix' => 'uploads/content/',
            ],
        ];

        self::assertGreaterThan(0, file_put_contents(
            $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php',
            "<?php\nreturn " . var_export($configuration, true) . ";\n",
        ));
    }
}
