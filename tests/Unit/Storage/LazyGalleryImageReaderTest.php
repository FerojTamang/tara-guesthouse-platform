<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Exceptions\GalleryImageException;
use App\Storage\GalleryImageReader;
use App\Storage\LazyGalleryImageReader;
use App\Storage\RetrievedGalleryImage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LazyGalleryImageReaderTest extends TestCase
{
    public function testItResolvesTheReaderLazilyOnce(): void
    {
        $image = new RetrievedGalleryImage('bytes', 'png', 'image/png', 1, 1);
        $reader = $this->createMock(GalleryImageReader::class);
        $reader->expects(self::exactly(2))->method('read')->with('gallery/' . str_repeat('a', 64) . '.png')->willReturn($image);
        $calls = 0;
        $lazy = new LazyGalleryImageReader(static function () use (&$calls, $reader): GalleryImageReader {
            $calls++;

            return $reader;
        });

        self::assertSame($image, $lazy->read('gallery/' . str_repeat('a', 64) . '.png'));
        self::assertSame($image, $lazy->read('gallery/' . str_repeat('a', 64) . '.png'));
        self::assertSame(1, $calls);
    }

    public function testItSanitizesInvalidOrFailingFactories(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static fn (): never => throw new RuntimeException('private reader detail'),
        ] as $factory) {
            try {
                (new LazyGalleryImageReader($factory))->read('gallery/' . str_repeat('a', 64) . '.png');
                self::fail('Expected a gallery reader failure.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Gallery image operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('private', $exception->getMessage());
            }
        }
    }
}
