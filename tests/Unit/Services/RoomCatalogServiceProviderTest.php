<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RoomCatalogException;
use App\Repositories\AmenityCatalogRepository;
use App\Repositories\RoomTypeCatalogRepository;
use App\Services\AmenityCatalogService;
use App\Services\RoomCatalogServiceProvider;
use App\Services\RoomTypeCatalogService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoomCatalogServiceProviderTest extends TestCase
{
    public function testItDefersAndCachesEachServiceIndependently(): void
    {
        $roomTypeCalls = 0;
        $amenityCalls = 0;
        $roomTypeRepository = $this->createStub(RoomTypeCatalogRepository::class);
        $amenityRepository = $this->createStub(AmenityCatalogRepository::class);
        $provider = new RoomCatalogServiceProvider(
            static function () use (&$roomTypeCalls, $roomTypeRepository): RoomTypeCatalogService {
                $roomTypeCalls++;

                return new RoomTypeCatalogService($roomTypeRepository);
            },
            static function () use (&$amenityCalls, $amenityRepository): AmenityCatalogService {
                $amenityCalls++;

                return new AmenityCatalogService($amenityRepository);
            },
        );

        self::assertSame(0, $roomTypeCalls);
        self::assertSame(0, $amenityCalls);
        self::assertSame($provider->roomTypes(), $provider->roomTypes());
        self::assertSame(1, $roomTypeCalls);
        self::assertSame(0, $amenityCalls);
        self::assertSame($provider->amenities(), $provider->amenities());
        self::assertSame(1, $amenityCalls);
    }

    public function testUnexpectedFactoryFailuresBecomeSafeCatalogueFailures(): void
    {
        $provider = new RoomCatalogServiceProvider(
            static function (): never {
                throw new RuntimeException('sensitive connection details');
            },
            static fn (): string => 'invalid service',
        );

        try {
            $provider->roomTypes();
            self::fail('Expected a safe room catalogue failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive connection details', $exception->getMessage());
        }

        try {
            $provider->amenities();
            self::fail('Expected an invalid-service failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }
    }
}
