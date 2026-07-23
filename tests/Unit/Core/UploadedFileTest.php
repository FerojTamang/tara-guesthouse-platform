<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\UploadedFile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UploadedFileTest extends TestCase
{
    public function testItIsImmutableAndRemovesClientPathSegments(): void
    {
        $file = new UploadedFile(
            '..\\unsafe/path/photo.JPG',
            'application/untrusted',
            'C:\\temporary\\php123.tmp',
            UPLOAD_ERR_OK,
            123,
        );

        self::assertTrue((new ReflectionClass($file))->isReadOnly());
        self::assertSame('photo.JPG', $file->originalName());
        self::assertSame('application/untrusted', $file->clientMediaType());
        self::assertSame('C:\\temporary\\php123.tmp', $file->temporaryPath());
        self::assertSame(UPLOAD_ERR_OK, $file->error());
        self::assertSame(123, $file->size());
    }

    public function testItRejectsNullBytesAndInvalidUploadMetadata(): void
    {
        foreach ([
            ["photo\0.jpg", 'image/jpeg', 'temporary', UPLOAD_ERR_OK, 1],
            ['photo.jpg', 'image/jpeg', "temp\0orary", UPLOAD_ERR_OK, 1],
            ['photo.jpg', 'image/jpeg', 'temporary', 99, 1],
            ['photo.jpg', 'image/jpeg', 'temporary', UPLOAD_ERR_OK, -1],
            ['', 'image/jpeg', 'temporary', UPLOAD_ERR_OK, 1],
            ['photo.jpg', 'image/jpeg', '', UPLOAD_ERR_OK, 1],
        ] as $arguments) {
            try {
                new UploadedFile(...$arguments);
                self::fail('Invalid uploaded-file metadata was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testNoFileErrorAllowsTheEmptyPhpUploadShape(): void
    {
        $file = new UploadedFile('', '', '', UPLOAD_ERR_NO_FILE, 0);

        self::assertSame('', $file->originalName());
        self::assertSame(UPLOAD_ERR_NO_FILE, $file->error());
    }
}
