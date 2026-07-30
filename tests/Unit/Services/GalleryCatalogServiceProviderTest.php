<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GalleryException;
use App\Repositories\GalleryCatalogRepository;
use App\Services\GalleryCatalogService;
use App\Services\GalleryCatalogServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GalleryCatalogServiceProviderTest extends TestCase
{
    public function testItConstructsTheServiceLazilyOnce(): void
    {
        $repository = $this->createStub(GalleryCatalogRepository::class);
        $calls = 0;
        $provider = new GalleryCatalogServiceProvider(
            static function () use (&$calls, $repository): GalleryCatalogService {
                $calls++;

                return new GalleryCatalogService($repository);
            },
        );

        self::assertSame($provider->service(), $provider->service());
        self::assertSame(1, $calls);
    }

    public function testItSanitizesInvalidOrFailingFactories(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static fn (): never => throw new RuntimeException('private database detail'),
        ] as $factory) {
            try {
                (new GalleryCatalogServiceProvider($factory))->service();
                self::fail('Expected a gallery provider failure.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Gallery catalogue operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('private', $exception->getMessage());
            }
        }
    }
}
