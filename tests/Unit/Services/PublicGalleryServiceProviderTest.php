<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GalleryException;
use App\Repositories\GalleryCatalogRepository;
use App\Services\PublicGalleryService;
use App\Services\PublicGalleryServiceProvider;
use App\Storage\GalleryImageReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicGalleryServiceProviderTest extends TestCase
{
    public function testItConstructsTheServiceLazilyOnce(): void
    {
        $repository = $this->createStub(GalleryCatalogRepository::class);
        $reader = $this->createStub(GalleryImageReader::class);
        $calls = 0;
        $provider = new PublicGalleryServiceProvider(
            static function () use (&$calls, $repository, $reader): PublicGalleryService {
                $calls++;

                return new PublicGalleryService($repository, $reader);
            },
        );

        self::assertSame($provider->service(), $provider->service());
        self::assertSame(1, $calls);
    }

    public function testItSanitizesInvalidOrFailingFactories(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static fn (): never => throw new RuntimeException('private provider detail'),
        ] as $factory) {
            try {
                (new PublicGalleryServiceProvider($factory))->service();
                self::fail('Expected a public gallery provider failure.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Gallery catalogue operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('private', $exception->getMessage());
            }
        }
    }
}
