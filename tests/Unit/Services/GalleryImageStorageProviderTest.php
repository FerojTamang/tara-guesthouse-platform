<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Environment;
use App\Exceptions\GalleryImageException;
use App\Services\GalleryImageStorageProvider;
use App\Storage\GalleryImageStorage;
use PHPUnit\Framework\TestCase;

final class GalleryImageStorageProviderTest extends TestCase
{
    private string $basePath;

    /** @var list<string> */
    private array $cleanupPaths = [];

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-gallery-provider-' . bin2hex(random_bytes(8));
        self::assertTrue(@mkdir($this->basePath . DIRECTORY_SEPARATOR . 'config', 0700, true));
        self::assertTrue(@mkdir($this->basePath . DIRECTORY_SEPARATOR . 'public', 0700));
        self::assertTrue(@mkdir(
            $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'gallery',
            0700,
            true,
        ));
        $this->cleanupPaths = [
            $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php',
            $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'gallery',
            $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private',
            $this->basePath . DIRECTORY_SEPARATOR . 'storage',
            $this->basePath . DIRECTORY_SEPARATOR . 'public',
            $this->basePath . DIRECTORY_SEPARATOR . 'config',
            $this->basePath,
        ];
        $this->writeDescriptor();
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupPaths as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }
    }

    public function testItResolvesApprovedEnvironmentPolicyAndCachesStorage(): void
    {
        $provider = new GalleryImageStorageProvider($this->basePath, $this->environment());
        $configuration = $provider->uploadConfiguration();

        self::assertSame([
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ], $configuration['allowed_mime_extensions']);
        self::assertSame(2_000_000, $configuration['maximum_file_size_bytes']);
        self::assertSame(320, $configuration['minimum_width_pixels']);
        self::assertSame(240, $configuration['minimum_height_pixels']);
        self::assertSame(4000, $configuration['maximum_width_pixels']);
        self::assertSame(4000, $configuration['maximum_height_pixels']);
        self::assertTrue($configuration['require_decodability']);
        self::assertSame('storage/private/gallery', $configuration['filesystem_root']);
        self::assertSame('gallery/', $configuration['database_path_prefix']);
        self::assertSame($configuration, $provider->uploadConfiguration());
        self::assertInstanceOf(GalleryImageStorage::class, $provider->storage());
        self::assertSame($provider->storage(), $provider->storage());
    }

    public function testMissingDecisionValuesFailClosedWithoutPathsOrSecrets(): void
    {
        foreach (array_keys($this->values()) as $missing) {
            $values = $this->values();
            unset($values[$missing]);
            $this->assertSystemFailure(
                fn () => (new GalleryImageStorageProvider(
                    $this->basePath,
                    Environment::fromArray($values),
                ))->uploadConfiguration(),
                [$missing, $this->basePath],
            );
        }
    }

    public function testMalformedAndUnapprovedPoliciesFailClosed(): void
    {
        $cases = [
            'empty formats' => ['GALLERY_IMAGE_FORMATS' => ''],
            'surrounding whitespace' => ['GALLERY_IMAGE_FORMATS' => ' jpeg,png'],
            'duplicate formats' => ['GALLERY_IMAGE_FORMATS' => 'png,png'],
            'unsupported format' => ['GALLERY_IMAGE_FORMATS' => 'svg'],
            'zero bytes' => ['GALLERY_IMAGE_MAX_BYTES' => '0'],
            'bad integer' => ['GALLERY_IMAGE_MIN_WIDTH' => '12px'],
            'width inversion' => ['GALLERY_IMAGE_MIN_WIDTH' => '5000'],
            'height inversion' => ['GALLERY_IMAGE_MIN_HEIGHT' => '5000'],
            'decoding not approved' => ['GALLERY_IMAGE_REQUIRE_DECODABILITY' => 'false'],
        ];

        foreach ($cases as $secret => $override) {
            $provider = new GalleryImageStorageProvider(
                $this->basePath,
                Environment::fromArray(array_replace($this->values(), $override)),
            );
            $this->assertSystemFailure(
                static fn () => $provider->uploadConfiguration(),
                [$secret, implode(',', $override), $this->basePath],
            );
        }
    }

    public function testMalformedDescriptorAndMissingPrivateRootFailClosed(): void
    {
        $this->writeDescriptor(['database_path_prefix' => 'private/secret/']);
        $this->assertSystemFailure(
            fn () => (new GalleryImageStorageProvider($this->basePath, $this->environment()))->storage(),
            ['private/secret/', $this->basePath],
        );

        $this->writeDescriptor();
        $root = $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'gallery';
        self::assertTrue(@rmdir($root));
        $this->assertSystemFailure(
            fn () => (new GalleryImageStorageProvider($this->basePath, $this->environment()))->storage(),
            [$root],
        );
    }

    public function testPrivateRootResolvingInsidePublicIsRejectedWhenSymlinksAreSupported(): void
    {
        $root = $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'gallery';
        $publicTarget = $this->basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'gallery-target';
        self::assertTrue(@mkdir($publicTarget, 0700));
        $this->cleanupPaths = array_merge([$root, $publicTarget], $this->cleanupPaths);
        self::assertTrue(@rmdir($root));

        if (!@symlink($publicTarget, $root)) {
            self::markTestSkipped('Current Windows/filesystem permissions do not permit directory symlink creation.');
        }

        $this->assertSystemFailure(
            fn () => (new GalleryImageStorageProvider($this->basePath, $this->environment()))->storage(),
            [$root, $publicTarget],
        );
    }

    /** @return array<string, string> */
    private function values(): array
    {
        return [
            'GALLERY_IMAGE_FORMATS' => 'jpeg,png,webp',
            'GALLERY_IMAGE_MAX_BYTES' => '2000000',
            'GALLERY_IMAGE_MIN_WIDTH' => '320',
            'GALLERY_IMAGE_MIN_HEIGHT' => '240',
            'GALLERY_IMAGE_MAX_WIDTH' => '4000',
            'GALLERY_IMAGE_MAX_HEIGHT' => '4000',
            'GALLERY_IMAGE_REQUIRE_DECODABILITY' => 'true',
        ];
    }

    private function environment(): Environment
    {
        return Environment::fromArray($this->values());
    }

    /** @param array<string, string> $overrides */
    private function writeDescriptor(array $overrides = []): void
    {
        $gallery = array_replace([
            'allowed_formats_environment_key' => 'GALLERY_IMAGE_FORMATS',
            'maximum_file_size_environment_key' => 'GALLERY_IMAGE_MAX_BYTES',
            'minimum_width_environment_key' => 'GALLERY_IMAGE_MIN_WIDTH',
            'minimum_height_environment_key' => 'GALLERY_IMAGE_MIN_HEIGHT',
            'maximum_width_environment_key' => 'GALLERY_IMAGE_MAX_WIDTH',
            'maximum_height_environment_key' => 'GALLERY_IMAGE_MAX_HEIGHT',
            'require_decodability_environment_key' => 'GALLERY_IMAGE_REQUIRE_DECODABILITY',
            'filesystem_root' => 'storage/private/gallery',
            'database_path_prefix' => 'gallery/',
        ], $overrides);
        $contents = "<?php\nreturn " . var_export(['gallery_images' => $gallery], true) . ";\n";
        self::assertNotFalse(file_put_contents(
            $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php',
            $contents,
        ));
    }

    /** @param list<string> $forbidden */
    private function assertSystemFailure(callable $operation, array $forbidden): void
    {
        try {
            $operation();
            self::fail('Expected gallery image provider failure.');
        } catch (GalleryImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Gallery image operation failed.', $exception->getMessage());

            foreach ($forbidden as $value) {
                if ($value === '') {
                    continue;
                }

                self::assertStringNotContainsString($value, $exception->getMessage());
            }
        }
    }
}
