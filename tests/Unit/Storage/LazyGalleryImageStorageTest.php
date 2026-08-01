<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Exceptions\GalleryImageException;
use App\Requests\GalleryImageUploadRequest;
use App\Storage\GalleryImageStorage;
use App\Storage\LazyGalleryImageStorage;
use App\Storage\StoredGalleryImage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LazyGalleryImageStorageTest extends TestCase
{
    public function testItDefersResolutionAndCachesTheStorage(): void
    {
        $calls = 0;
        $delegate = $this->createMock(GalleryImageStorage::class);
        $delegate->expects(self::once())->method('remove')->with(
            'gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.jpg',
        );
        $storage = new LazyGalleryImageStorage(
            static function () use (&$calls, $delegate): GalleryImageStorage {
                $calls++;

                return $delegate;
            },
        );

        self::assertSame(0, $calls);
        $storage->remove('gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.jpg');
        self::assertSame(1, $calls);
    }

    public function testInvalidResolutionAndUnexpectedFailuresAreSanitized(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static function (): never {
                throw new RuntimeException('sensitive storage path');
            },
        ] as $factory) {
            try {
                (new LazyGalleryImageStorage($factory))->remove(
                    'gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.jpg',
                );
                self::fail('Expected a safe gallery image failure.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Gallery image operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('sensitive', $exception->getMessage());
            }
        }
    }
}
