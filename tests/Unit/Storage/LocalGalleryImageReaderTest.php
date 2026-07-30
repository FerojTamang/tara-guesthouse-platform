<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Exceptions\GalleryImageException;
use App\Storage\LocalGalleryImageReader;
use PHPUnit\Framework\TestCase;

final class LocalGalleryImageReaderTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }

        parent::tearDown();
    }

    public function testItReadsAndRevalidatesOneCanonicalPrivateImage(): void
    {
        $root = $this->directory();
        $filename = str_repeat('a', 64) . '.png';
        $path = $root . DIRECTORY_SEPARATOR . $filename;
        $bytes = self::png();
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));
        $this->cleanup[] = $path;
        $image = (new LocalGalleryImageReader($root, self::policy()))->read('gallery/' . $filename);

        self::assertNotNull($image);
        self::assertSame($bytes, $image->bytes());
        self::assertSame('png', $image->extension());
        self::assertSame('image/png', $image->mediaType());
        self::assertSame(1, $image->width());
        self::assertSame(1, $image->height());
        self::assertSame(strlen($bytes), $image->size());
    }

    public function testMissingCanonicalFileReturnsNull(): void
    {
        $root = $this->directory();
        self::assertNull((new LocalGalleryImageReader($root, self::policy()))->read(
            'gallery/' . str_repeat('b', 64) . '.png',
        ));
    }

    public function testUnsafePathsWrongContentAndOversizedFilesFailClosed(): void
    {
        $root = $this->directory();
        $reader = new LocalGalleryImageReader($root, self::policy());

        foreach ([
            '../' . str_repeat('a', 64) . '.png',
            'gallery/../' . str_repeat('a', 64) . '.png',
            'gallery\\' . str_repeat('a', 64) . '.png',
            'gallery/' . str_repeat('A', 64) . '.png',
            'gallery/' . str_repeat('a', 64) . '.php',
        ] as $unsafe) {
            try {
                $reader->read($unsafe);
                self::fail('Unsafe gallery read path was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isStorageFailure());
            }
        }

        $filename = str_repeat('c', 64) . '.png';
        $path = $root . DIRECTORY_SEPARATOR . $filename;
        self::assertSame(12, file_put_contents($path, 'not-an-image'));
        $this->cleanup[] = $path;

        $this->expectException(GalleryImageException::class);
        $this->expectExceptionMessage('Gallery image storage failed.');
        $reader->read('gallery/' . $filename);
    }

    public function testInvalidPolicyAndRootFailWithoutDisclosingPaths(): void
    {
        $root = $this->directory();
        $invalid = self::policy();
        $invalid['maximum_file_size_bytes'] = 0;

        foreach ([
            static fn () => new LocalGalleryImageReader('storage/private/gallery', self::policy()),
            static fn () => new LocalGalleryImageReader($root, $invalid),
        ] as $operation) {
            try {
                $operation();
                self::fail('Invalid gallery reader configuration was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertStringNotContainsString($root, $exception->getMessage());
            }
        }
    }

    /** @return array<string, mixed> */
    private static function policy(): array
    {
        return [
            'allowed_mime_extensions' => ['image/png' => ['png']],
            'maximum_file_size_bytes' => 1024,
            'minimum_width_pixels' => 1,
            'minimum_height_pixels' => 1,
            'maximum_width_pixels' => 100,
            'maximum_height_pixels' => 100,
            'require_decodability' => true,
            'filesystem_root' => 'storage/private/gallery',
            'database_path_prefix' => 'gallery/',
        ];
    }

    private function directory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-gallery-reader-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($path, 0700));
        $this->cleanup[] = $path;

        return $path;
    }

    private static function png(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }
}
