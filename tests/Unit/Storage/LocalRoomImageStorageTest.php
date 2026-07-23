<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Core\UploadedFile;
use App\Storage\LocalRoomImageStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalRoomImageStorageTest extends TestCase
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

    #[DataProvider('supportedMedia')]
    public function testItStoresSupportedBytesWithCanonicalExtension(string $mime, string $clientName, string $extension): void
    {
        [$root, $source, $bytes] = $this->fixture($clientName);
        $storage = new LocalRoomImageStorage($root);
        $stored = $storage->store($source, $mime);
        $destination = $root . DIRECTORY_SEPARATOR . $stored->filename();
        $this->cleanupPaths[] = $destination;

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}\\.' . $extension . '$/D', $stored->filename());
        self::assertSame('uploads/rooms/' . $stored->filename(), $stored->relativePath());
        self::assertSame($extension, $stored->extension());
        self::assertSame(strlen($bytes), $stored->byteSize());
        self::assertSame($bytes, file_get_contents($destination));
        self::assertSame($bytes, file_get_contents($source->temporaryPath()));
        self::assertStringNotContainsString(pathinfo($clientName, PATHINFO_FILENAME), $stored->filename());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function supportedMedia(): iterable
    {
        yield 'jpeg jpg' => ['image/jpeg', 'guest-name.jpg', 'jpg'];
        yield 'jpeg canonicalizes jpeg' => ['image/jpeg', 'guest-name.jpeg', 'jpg'];
        yield 'png double extension ignored' => ['image/png', 'attack.php.png', 'png'];
        yield 'webp' => ['image/webp', 'guest-name.webp', 'webp'];
    }

    public function testTwoStoresGenerateDifferentNamesAndDoNotChangeSource(): void
    {
        [$root, $source, $bytes] = $this->fixture('same.png');
        $storage = new LocalRoomImageStorage($root);
        $first = $storage->store($source, 'image/png');
        $second = $storage->store($source, 'image/png');
        $this->cleanupPaths[] = $root . DIRECTORY_SEPARATOR . $first->filename();
        $this->cleanupPaths[] = $root . DIRECTORY_SEPARATOR . $second->filename();

        self::assertNotSame($first->filename(), $second->filename());
        self::assertSame($bytes, file_get_contents($source->temporaryPath()));
    }

    public function testItRetriesCollisionWithoutOverwritingExistingFile(): void
    {
        [$root, $source] = $this->fixture('room.png');
        $collisionBytes = str_repeat("\x11", 32);
        $successBytes = str_repeat("\x22", 32);
        $existing = $root . DIRECTORY_SEPARATOR . bin2hex($collisionBytes) . '.png';
        file_put_contents($existing, 'do-not-overwrite');
        $this->cleanupPaths[] = $existing;
        $calls = 0;
        $random = static function () use (&$calls, $collisionBytes, $successBytes): string {
            return $calls++ === 0 ? $collisionBytes : $successBytes;
        };
        $storage = new LocalRoomImageStorage($root, $random(...));
        $stored = $storage->store($source, 'image/png');
        $this->cleanupPaths[] = $root . DIRECTORY_SEPARATOR . $stored->filename();

        self::assertSame(2, $calls);
        self::assertSame('do-not-overwrite', file_get_contents($existing));
        self::assertSame(bin2hex($successBytes) . '.png', $stored->filename());
    }

    public function testItFailsAfterBoundedCollisionRetriesWithoutOverwrite(): void
    {
        [$root, $source] = $this->fixture('room.webp');
        $bytes = str_repeat("\x33", 32);
        $existing = $root . DIRECTORY_SEPARATOR . bin2hex($bytes) . '.webp';
        file_put_contents($existing, 'existing');
        $this->cleanupPaths[] = $existing;
        $storage = new LocalRoomImageStorage($root, static fn (): string => $bytes, null, 2);

        try {
            $storage->store($source, 'image/webp');
            self::fail('Expected collision exhaustion.');
        } catch (RuntimeException $exception) {
            self::assertSame('Room image storage failed.', $exception->getMessage());
            self::assertSame('existing', file_get_contents($existing));
        }
    }

    public function testItRemovesPartialDestinationAfterWriteFailure(): void
    {
        [$root, $source] = $this->fixture('room.jpg');
        $bytes = str_repeat("\x44", 32);
        $filename = bin2hex($bytes) . '.jpg';
        $writer = static function ($input, $output): int {
            $chunk = fread($input, 2);
            self::assertIsString($chunk);
            $written = fwrite($output, $chunk);
            self::assertIsInt($written);

            return $written;
        };
        $storage = new LocalRoomImageStorage($root, static fn (): string => $bytes, $writer(...));

        try {
            $storage->store($source, 'image/jpeg');
            self::fail('Expected partial-write failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Room image storage failed.', $exception->getMessage());
            self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $filename);
        }
    }

    public function testItSanitizesFileSizeFailureAndRemovesDestination(): void
    {
        [$root, $source] = $this->fixture('room.png');
        $bytes = str_repeat("\x45", 32);
        $filename = bin2hex($bytes) . '.png';
        $calls = 0;
        $fileSize = static function (string $path) use (&$calls, $source): int {
            if ($calls++ === 0) {
                return $source->size();
            }

            throw new RuntimeException('filesize failed for ' . $path);
        };
        $storage = new LocalRoomImageStorage(
            $root,
            static fn (): string => $bytes,
            null,
            8,
            $fileSize(...),
        );

        $this->assertSanitizedStorageFailure(
            static fn () => $storage->store($source, 'image/png'),
            [$root, $source->temporaryPath()],
        );
        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $filename);
    }

    public function testItSanitizesReadFailure(): void
    {
        [$root, $source] = $this->fixture('room.jpg');
        $writer = static function ($input, $output, int $size) use ($source): int {
            throw new RuntimeException('read failed for ' . $source->temporaryPath());
        };
        $storage = new LocalRoomImageStorage($root, null, $writer(...));

        $this->assertSanitizedStorageFailure(
            static fn () => $storage->store($source, 'image/jpeg'),
            [$root, $source->temporaryPath()],
        );
    }

    public function testItSanitizesWriteFailureAndRemovesPartialFile(): void
    {
        [$root, $source] = $this->fixture('room.webp');
        $bytes = str_repeat("\x46", 32);
        $filename = bin2hex($bytes) . '.webp';
        $writer = static function ($input, $output): int {
            @fwrite($output, 'partial');
            throw new RuntimeException('write failed for a physical destination');
        };
        $storage = new LocalRoomImageStorage($root, static fn (): string => $bytes, $writer(...));

        $this->assertSanitizedStorageFailure(
            static fn () => $storage->store($source, 'image/webp'),
            [$root, $source->temporaryPath()],
        );
        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $filename);
    }

    public function testItSanitizesFlushFailureAndRemovesPartialFile(): void
    {
        [$root, $source] = $this->fixture('room.png');
        $bytes = str_repeat("\x47", 32);
        $filename = bin2hex($bytes) . '.png';
        $writer = static function ($input, $output, int $size): int {
            $contents = @fread($input, $size);
            self::assertIsString($contents);
            self::assertSame($size, @fwrite($output, $contents));
            @fclose($output);

            return $size;
        };
        $storage = new LocalRoomImageStorage($root, static fn (): string => $bytes, $writer(...));

        $this->assertSanitizedStorageFailure(
            static fn () => $storage->store($source, 'image/png'),
            [$root, $source->temporaryPath()],
        );
        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $filename);
    }

    public function testItRejectsIncorrectDestinationStatAndRemovesPartialFile(): void
    {
        [$root, $source] = $this->fixture('room.jpg');
        $bytes = str_repeat("\x48", 32);
        $filename = bin2hex($bytes) . '.jpg';
        $writer = static function ($input, $output, int $size): int {
            $contents = @fread($input, $size);
            self::assertIsString($contents);
            self::assertSame($size, @fwrite($output, $contents));
            self::assertTrue(@ftruncate($output, $size - 1));

            return $size;
        };
        $storage = new LocalRoomImageStorage($root, static fn (): string => $bytes, $writer(...));

        $this->assertSanitizedStorageFailure(
            static fn () => $storage->store($source, 'image/jpeg'),
            [$root, $source->temporaryPath()],
        );
        self::assertFileDoesNotExist($root . DIRECTORY_SEPARATOR . $filename);
    }

    public function testCleanupFailurePreservesSanitizedOriginalFailure(): void
    {
        [$root, $source] = $this->fixture('room.png');
        $writer = static fn (): never => throw new RuntimeException('original failure with physical path');
        $remover = static fn (string $path): never => throw new RuntimeException('cleanup failed for ' . $path);
        $storage = new LocalRoomImageStorage($root, null, $writer(...), 8, null, $remover(...));

        $this->assertSanitizedStorageFailure(
            static fn () => $storage->store($source, 'image/png'),
            [$root, $source->temporaryPath(), 'cleanup failed'],
        );
    }

    public function testItRejectsUnsupportedMimeWithoutLeakingPaths(): void
    {
        [$root, $source] = $this->fixture('room.gif');

        try {
            (new LocalRoomImageStorage($root))->store($source, 'image/gif');
            self::fail('Expected unsupported MIME failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Room image storage failed.', $exception->getMessage());
            self::assertStringNotContainsString($root, $exception->getMessage());
            self::assertStringNotContainsString($source->temporaryPath(), $exception->getMessage());
        }
    }

    public function testItRejectsMissingSource(): void
    {
        $root = $this->temporaryDirectory();
        $missing = $root . DIRECTORY_SEPARATOR . 'missing.png';
        $file = new UploadedFile('room.png', 'image/png', $missing, UPLOAD_ERR_OK, 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage failed.');

        (new LocalRoomImageStorage($root))->store($file, 'image/png');
    }

    public function testItRejectsSourceWhoseSizeChangedAfterValidation(): void
    {
        [$root, $source] = $this->fixture('room.png');
        file_put_contents($source->temporaryPath(), 'changed-size');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage failed.');

        (new LocalRoomImageStorage($root))->store($source, 'image/png');
    }

    public function testItRejectsAbsentRootWithoutDisclosingIt(): void
    {
        $parent = $this->temporaryDirectory();
        $missing = $parent . DIRECTORY_SEPARATOR . 'absent';

        try {
            new LocalRoomImageStorage($missing);
            self::fail('Expected missing-root failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Room image storage is unavailable.', $exception->getMessage());
            self::assertStringNotContainsString($missing, $exception->getMessage());
        }
    }

    public function testItRejectsRootThatIsAFile(): void
    {
        $root = $this->temporaryDirectory();
        $file = $root . DIRECTORY_SEPARATOR . 'not-a-directory';
        file_put_contents($file, 'file');
        $this->cleanupPaths[] = $file;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage is unavailable.');

        new LocalRoomImageStorage($file);
    }

    public function testItRejectsRelativeRoot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage is unavailable.');

        new LocalRoomImageStorage('public/uploads/rooms');
    }

    public function testItRejectsRootRemovedAfterConstruction(): void
    {
        $root = $this->temporaryDirectory();
        $source = $this->externalSource('room.png');
        $storage = new LocalRoomImageStorage($root);
        self::assertTrue(@rmdir($root));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage failed.');

        $storage->store($source, 'image/png');
    }

    public function testItRejectsRootReplacedByAFileAfterConstruction(): void
    {
        $root = $this->temporaryDirectory();
        $source = $this->externalSource('room.png');
        $storage = new LocalRoomImageStorage($root);
        $backup = $root . '-original';
        self::assertTrue(@rename($root, $backup));
        $this->cleanupPaths[] = $backup;
        self::assertSame(11, file_put_contents($root, 'replacement'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage failed.');

        $storage->store($source, 'image/png');
    }

    public function testItRejectsRootReplacedByDifferentDirectoryAfterConstruction(): void
    {
        $root = $this->temporaryDirectory();
        $source = $this->externalSource('room.png');
        $storage = new LocalRoomImageStorage($root);
        $backup = $root . '-original';
        self::assertTrue(@rename($root, $backup));
        $this->cleanupPaths[] = $backup;
        self::assertTrue(@mkdir($root, 0700));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage failed.');

        $storage->store($source, 'image/png');
    }

    public function testItRejectsRootReplacedBySymlinkAfterConstructionWhenSupported(): void
    {
        $root = $this->temporaryDirectory();
        $target = $this->temporaryDirectory();
        $source = $this->externalSource('room.png');
        $storage = new LocalRoomImageStorage($root);
        $backup = $root . '-original';
        self::assertTrue(@rename($root, $backup));
        $this->cleanupPaths[] = $backup;

        if (!@symlink($target, $root)) {
            self::markTestSkipped('The current Windows/filesystem permissions do not permit replacement symlink creation.');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage failed.');

        $storage->store($source, 'image/png');
    }

    public function testCleanupDoesNotRemoveUnrelatedFileAfterRootReplacement(): void
    {
        $root = $this->temporaryDirectory();
        $source = $this->externalSource('room.png');
        $randomBytes = str_repeat("\x49", 32);
        $filename = bin2hex($randomBytes) . '.png';
        $backup = $root . '-original';
        $unrelated = $root . DIRECTORY_SEPARATOR . $filename;
        $partial = $backup . DIRECTORY_SEPARATOR . $filename;
        $this->cleanupPaths[] = $backup;
        $this->cleanupPaths[] = $partial;
        $this->cleanupPaths[] = $unrelated;
        $writer = function ($input, $output) use ($root, $backup, $unrelated): int {
            self::assertSame(2, @fwrite($output, 'xx'));
            self::assertTrue(@fclose($output));
            self::assertTrue(@rename($root, $backup));
            self::assertTrue(@mkdir($root, 0700));
            self::assertSame(9, file_put_contents($unrelated, 'unrelated'));

            return 2;
        };
        $storage = new LocalRoomImageStorage(
            $root,
            static fn (): string => $randomBytes,
            $writer(...),
        );

        try {
            $storage->store($source, 'image/png');
            self::fail('Expected storage failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Room image storage failed.', $exception->getMessage());
            self::assertSame('unrelated', file_get_contents($unrelated));
            self::assertSame('xx', file_get_contents($partial));
        }
    }

    public function testItRejectsSymlinkedRootWhenSupported(): void
    {
        $target = $this->temporaryDirectory();
        $parent = $this->temporaryDirectory();
        $link = $parent . DIRECTORY_SEPARATOR . 'linked-root';

        if (!@symlink($target, $link)) {
            self::markTestSkipped('The current Windows/filesystem permissions do not permit symlink creation.');
        }

        $this->cleanupPaths[] = $link;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Room image storage is unavailable.');

        new LocalRoomImageStorage($link);
    }

    public function testItRejectsUnreadableSourceWhenPermissionModelSupportsIt(): void
    {
        [$root, $source] = $this->fixture('room.png');

        if (!@chmod($source->temporaryPath(), 0000) || is_readable($source->temporaryPath())) {
            @chmod($source->temporaryPath(), 0600);
            self::markTestSkipped('The current Windows/filesystem permission model cannot make the fixture unreadable.');
        }

        try {
            $this->expectException(RuntimeException::class);
            (new LocalRoomImageStorage($root))->store($source, 'image/png');
        } finally {
            @chmod($source->temporaryPath(), 0600);
        }
    }

    public function testItRejectsUnwritableRootWhenPermissionModelSupportsIt(): void
    {
        [$root, $source] = $this->fixture('room.png');

        if (!@chmod($root, 0500) || is_writable($root)) {
            @chmod($root, 0700);
            self::markTestSkipped('The current Windows/filesystem permission model cannot make the fixture root unwritable.');
        }

        try {
            $this->expectException(RuntimeException::class);
            new LocalRoomImageStorage($root);
        } finally {
            @chmod($root, 0700);
        }
    }

    /** @return array{string, UploadedFile, string} */
    private function fixture(string $clientName): array
    {
        $root = $this->temporaryDirectory();
        $sourcePath = $root . DIRECTORY_SEPARATOR . 'validated-upload.tmp';
        $bytes = "\x00\x01validated-room-image\xff";
        file_put_contents($sourcePath, $bytes);
        $this->cleanupPaths[] = $sourcePath;

        return [
            $root,
            new UploadedFile($clientName, 'client/value', $sourcePath, UPLOAD_ERR_OK, strlen($bytes)),
            $bytes,
        ];
    }

    private function externalSource(string $clientName): UploadedFile
    {
        $directory = $this->temporaryDirectory();
        $path = $directory . DIRECTORY_SEPARATOR . 'validated-upload.tmp';
        $bytes = 'external-validated-image';
        file_put_contents($path, $bytes);
        $this->cleanupPaths[] = $path;

        return new UploadedFile($clientName, 'client/value', $path, UPLOAD_ERR_OK, strlen($bytes));
    }

    /** @param list<string> $forbidden */
    private function assertSanitizedStorageFailure(callable $operation, array $forbidden): void
    {
        try {
            $operation();
            self::fail('Expected sanitized storage failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Room image storage failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());

            foreach ($forbidden as $value) {
                self::assertStringNotContainsString($value, $exception->getMessage());
            }
        }
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-room-storage-' . bin2hex(random_bytes(8));

        if (!mkdir($path, 0700) && !is_dir($path)) {
            self::fail('Temporary storage directory could not be created.');
        }

        $resolved = realpath($path);
        self::assertIsString($resolved);
        $this->cleanupPaths[] = $resolved;

        return $resolved;
    }
}
