<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Core\UploadedFile;
use App\Exceptions\GalleryImageException;
use App\Requests\GalleryImageUploadRequest;
use App\Storage\LocalGalleryImageStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalGalleryImageStorageTest extends TestCase
{
    /** @var list<string> */
    private array $cleanupPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanupPaths) as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }

        $this->cleanupPaths = [];
    }

    public function testItStoresValidatedBytesUnderRandomCanonicalPrivatePath(): void
    {
        [$root, $request, $bytes] = $this->fixture('guest-name.php.png');
        $random = str_repeat("\x31", 32);
        $storage = new LocalGalleryImageStorage($root, static fn (): string => $random);
        $stored = $storage->store($request);
        $destination = $root . DIRECTORY_SEPARATOR . $stored->filename();
        $this->cleanupPaths[] = $destination;

        self::assertSame(str_repeat('31', 32) . '.png', $stored->filename());
        self::assertSame('gallery/' . $stored->filename(), $stored->relativePath());
        self::assertSame('png', $stored->extension());
        self::assertSame('image/png', $stored->mediaType());
        self::assertSame(strlen($bytes), $stored->byteSize());
        self::assertSame(32, $stored->width());
        self::assertSame(24, $stored->height());
        self::assertSame($bytes, file_get_contents($destination));
        self::assertSame($bytes, file_get_contents($request->file()->temporaryPath()));
        self::assertStringNotContainsString('guest-name', $stored->filename());
    }

    public function testItRetriesExclusiveCreateCollisionsWithoutOverwriting(): void
    {
        [$root, $request] = $this->fixture('gallery.png');
        $first = str_repeat("\x41", 32);
        $second = str_repeat("\x42", 32);
        $existing = $root . DIRECTORY_SEPARATOR . bin2hex($first) . '.png';
        self::assertSame(8, file_put_contents($existing, 'existing'));
        $this->cleanupPaths[] = $existing;
        $calls = 0;
        $storage = new LocalGalleryImageStorage(
            $root,
            static function () use (&$calls, $first, $second): string {
                return $calls++ === 0 ? $first : $second;
            },
        );

        $stored = $storage->store($request);
        $destination = $root . DIRECTORY_SEPARATOR . $stored->filename();
        $this->cleanupPaths[] = $destination;

        self::assertSame(2, $calls);
        self::assertSame('existing', file_get_contents($existing));
        self::assertSame(bin2hex($second) . '.png', $stored->filename());
    }

    public function testWriteFailureRemovesPartialFileAndSanitizesPaths(): void
    {
        [$root, $request] = $this->fixture('gallery.png');
        $random = str_repeat("\x43", 32);
        $filename = bin2hex($random) . '.png';
        $writer = static function ($source, $destination): never {
            @fwrite($destination, 'partial');
            throw new RuntimeException('private destination failure');
        };
        $storage = new LocalGalleryImageStorage($root, static fn (): string => $random, $writer(...));

        try {
            $storage->store($request);
            self::fail('Expected write failure.');
        } catch (GalleryImageException $exception) {
            self::assertTrue($exception->isStorageFailure());
            self::assertSame('Gallery image storage failed.', $exception->getMessage());
            self::assertStringNotContainsString($root, $exception->getMessage());
            self::assertStringNotContainsString($request->file()->temporaryPath(), $exception->getMessage());
        }

        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $filename);
    }

    public function testSameSizeSourceChangedAfterValidationIsRejected(): void
    {
        [$root, $request, $bytes] = $this->fixture('gallery.png');
        self::assertSame(strlen($bytes), file_put_contents(
            $request->file()->temporaryPath(),
            str_repeat('x', strlen($bytes)),
        ));

        $this->expectException(GalleryImageException::class);
        $this->expectExceptionMessage('Gallery image storage failed.');
        (new LocalGalleryImageStorage($root))->store($request);
    }


    public function testWriterCannotSubstituteDifferentSameSizeBytes(): void
    {
        [$root, $request, $bytes] = $this->fixture('gallery.png');
        $random = str_repeat("\x45", 32);
        $filename = bin2hex($random) . '.png';
        $writer = static function ($source, $destination, int $size): int {
            $replacement = str_repeat('z', $size);
            self::assertSame($size, @fwrite($destination, $replacement));

            return $size;
        };
        $storage = new LocalGalleryImageStorage($root, static fn (): string => $random, $writer(...));

        $this->expectException(GalleryImageException::class);
        $this->expectExceptionMessage('Gallery image storage failed.');

        try {
            $storage->store($request);
        } finally {
            self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $filename);
            self::assertNotSame(str_repeat('z', strlen($bytes)), $bytes);
        }
    }

    public function testItRemovesOnlyCanonicalGalleryPathsAndAbsenceIsIdempotent(): void
    {
        $root = $this->temporaryDirectory('storage');
        $filename = str_repeat('a', 64) . '.png';
        $path = $root . DIRECTORY_SEPARATOR . $filename;
        self::assertSame(6, file_put_contents($path, 'stored'));
        $this->cleanupPaths[] = $path;
        $storage = new LocalGalleryImageStorage($root);

        $storage->remove('gallery/' . $filename);
        self::assertFileDoesNotExist($path);
        $storage->remove('gallery/' . $filename);

        $unrelated = $root . DIRECTORY_SEPARATOR . 'unrelated.txt';
        self::assertSame(4, file_put_contents($unrelated, 'keep'));
        $this->cleanupPaths[] = $unrelated;

        foreach ([
            '../' . $filename,
            'gallery/../' . $filename,
            'gallery\\' . $filename,
            '/gallery/' . $filename,
            'gallery/' . str_repeat('A', 64) . '.png',
            'gallery/' . str_repeat('a', 64) . '.php',
            'gallery/' . str_repeat('a', 32) . "\0" . str_repeat('a', 32) . '.png',
        ] as $unsafe) {
            try {
                $storage->remove($unsafe);
                self::fail('Unsafe gallery removal path was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isStorageFailure());
            }
        }

        self::assertSame('keep', file_get_contents($unrelated));
    }

    public function testItRejectsRelativeMissingFileAndSymlinkRoots(): void
    {
        foreach (['storage/private/gallery', $this->temporaryFile()] as $invalidRoot) {
            try {
                new LocalGalleryImageStorage($invalidRoot);
                self::fail('Invalid storage root was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Gallery image operation failed.', $exception->getMessage());
                self::assertStringNotContainsString($invalidRoot, $exception->getMessage());
            }
        }

        $target = $this->temporaryDirectory('target');
        $link = $target . '-link';

        if (!@symlink($target, $link)) {
            self::markTestSkipped('Current Windows/filesystem permissions do not permit directory symlink creation.');
        }

        $this->cleanupPaths[] = $link;
        $this->expectException(GalleryImageException::class);
        new LocalGalleryImageStorage($link);
    }

    public function testRootReplacementAfterConstructionIsDetected(): void
    {
        [$root, $request] = $this->fixture('gallery.png');
        $storage = new LocalGalleryImageStorage($root);
        $backup = $root . '-original';
        self::assertTrue(@rename($root, $backup));
        $this->cleanupPaths[] = $backup;
        self::assertTrue(@mkdir($root, 0700));

        $this->expectException(GalleryImageException::class);
        $this->expectExceptionMessage('Gallery image storage failed.');
        $storage->store($request);
    }

    public function testInvalidRandomGeneratorAndCollisionExhaustionFailClosed(): void
    {
        [$root, $request] = $this->fixture('gallery.png');

        foreach ([
            static fn (): string => 'short',
            static fn (): never => throw new RuntimeException('random source secret'),
        ] as $generator) {
            try {
                (new LocalGalleryImageStorage($root, $generator(...)))->store($request);
                self::fail('Invalid random generator was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isStorageFailure());
                self::assertStringNotContainsString('secret', $exception->getMessage());
            }
        }

        $random = str_repeat("\x44", 32);
        $collision = $root . DIRECTORY_SEPARATOR . bin2hex($random) . '.png';
        self::assertSame(8, file_put_contents($collision, 'existing'));
        $this->cleanupPaths[] = $collision;
        $this->expectException(GalleryImageException::class);
        $this->expectExceptionMessage('Gallery image storage failed.');
        (new LocalGalleryImageStorage($root, static fn (): string => $random, null, 2))->store($request);
    }

    /** @return array{string, GalleryImageUploadRequest, string} */
    private function fixture(string $clientName): array
    {
        $root = $this->temporaryDirectory('storage');
        $bytes = $this->png(32, 24);
        $sourcePath = $this->temporaryFile($bytes);
        $size = filesize($sourcePath);
        self::assertIsInt($size);
        $file = new UploadedFile($clientName, 'client/claim', $sourcePath, UPLOAD_ERR_OK, $size);
        $request = GalleryImageUploadRequest::fromInput($file, $this->policy());

        return [$root, $request, $bytes];
    }

    /** @return array<string, mixed> */
    private function policy(): array
    {
        return [
            'allowed_mime_extensions' => ['image/png' => ['png']],
            'maximum_file_size_bytes' => 1_000_000,
            'minimum_width_pixels' => 16,
            'minimum_height_pixels' => 12,
            'maximum_width_pixels' => 100,
            'maximum_height_pixels' => 100,
            'require_decodability' => true,
        ];
    }

    private function temporaryDirectory(string $suffix): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-gallery-' . $suffix . '-' . bin2hex(random_bytes(8));
        self::assertTrue(@mkdir($path, 0700));
        $resolved = realpath($path);
        self::assertIsString($resolved);
        $this->cleanupPaths[] = $resolved;

        return $resolved;
    }

    private function temporaryFile(string $bytes = 'not-a-directory'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tgh-gallery-file-');
        self::assertIsString($path);
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));
        $this->cleanupPaths[] = $path;

        return $path;
    }

    private function png(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $row = "\0" . str_repeat("\0", $width * 3);
        $pixels = gzcompress(str_repeat($row, $height), 9);
        self::assertIsString($pixels);

        return $signature
            . $this->pngChunk('IHDR', $ihdr)
            . $this->pngChunk('IDAT', $pixels)
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
