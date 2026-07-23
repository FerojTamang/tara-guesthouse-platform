<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Storage\StoredRoomImage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StoredRoomImageTest extends TestCase
{
    private const BASENAME = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testItExposesValidImmutableStorageMetadata(): void
    {
        $image = new StoredRoomImage(
            'uploads/rooms/' . self::BASENAME . '.jpg',
            self::BASENAME . '.jpg',
            'jpg',
            1234,
        );

        self::assertSame('uploads/rooms/' . self::BASENAME . '.jpg', $image->relativePath());
        self::assertSame(self::BASENAME . '.jpg', $image->filename());
        self::assertSame('jpg', $image->extension());
        self::assertSame(1234, $image->byteSize());
    }

    #[DataProvider('invalidMetadata')]
    public function testItRejectsInvalidStorageMetadata(
        string $path,
        string $filename,
        string $extension,
        int $size,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stored room image data is invalid.');

        new StoredRoomImage($path, $filename, $extension, $size);
    }

    /** @return iterable<string, array{string, string, string, int}> */
    public static function invalidMetadata(): iterable
    {
        $name = self::BASENAME . '.png';

        yield 'wrong relative directory' => ['rooms/' . $name, $name, 'png', 1];
        yield 'absolute path' => ['/uploads/rooms/' . $name, $name, 'png', 1];
        yield 'drive path' => ['C:\\uploads\\rooms\\' . $name, $name, 'png', 1];
        yield 'UNC path' => ['\\\\server\\share\\' . $name, $name, 'png', 1];
        yield 'NUL byte' => ["uploads/rooms/\0" . $name, $name, 'png', 1];
        yield 'traversal' => ['uploads/rooms/../' . $name, $name, 'png', 1];
        yield 'slash in filename' => ['uploads/rooms/' . $name, 'nested/' . $name, 'png', 1];
        yield 'backslash in filename' => ['uploads/rooms/' . $name, 'nested\\' . $name, 'png', 1];
        yield 'short random name' => ['uploads/rooms/abcd.png', 'abcd.png', 'png', 1];
        yield 'uppercase random name' => [
            'uploads/rooms/' . strtoupper(self::BASENAME) . '.png',
            strtoupper(self::BASENAME) . '.png',
            'png',
            1,
        ];
        yield 'double extension' => [
            'uploads/rooms/' . self::BASENAME . '.php.png',
            self::BASENAME . '.php.png',
            'png',
            1,
        ];
        yield 'unsupported extension' => [
            'uploads/rooms/' . self::BASENAME . '.gif',
            self::BASENAME . '.gif',
            'gif',
            1,
        ];
        yield 'extension mismatch' => [
            'uploads/rooms/' . self::BASENAME . '.png',
            self::BASENAME . '.png',
            'jpg',
            1,
        ];
        yield 'path mismatch' => [
            'uploads/rooms/' . self::BASENAME . '.jpg',
            self::BASENAME . '.png',
            'png',
            1,
        ];
        yield 'zero size' => ['uploads/rooms/' . $name, $name, 'png', 0];
        yield 'negative size' => ['uploads/rooms/' . $name, $name, 'png', -1];
    }
}
