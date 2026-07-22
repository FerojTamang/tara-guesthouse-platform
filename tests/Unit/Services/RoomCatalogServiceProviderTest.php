<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RoomCatalogException;
use App\Repositories\AmenityCatalogRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomTypeCatalogRepository;
use App\Services\AmenityCatalogService;
use App\Services\RoomCatalogService;
use App\Services\RoomCatalogServiceProvider;
use App\Services\RoomCatalogTransaction;
use App\Services\RoomTypeCatalogService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoomCatalogServiceProviderTest extends TestCase
{
    public function testItDefersAndCachesEachServiceIndependently(): void
    {
        $roomTypeCalls = 0;
        $amenityCalls = 0;
        $roomCalls = 0;
        $roomTypeRepository = $this->createStub(RoomTypeCatalogRepository::class);
        $amenityRepository = $this->createStub(AmenityCatalogRepository::class);
        $roomRepository = $this->createStub(RoomCatalogRepository::class);
        $transaction = $this->createStub(RoomCatalogTransaction::class);
        $provider = new RoomCatalogServiceProvider(
            static function () use (&$roomTypeCalls, $roomTypeRepository): RoomTypeCatalogService {
                $roomTypeCalls++;

                return new RoomTypeCatalogService($roomTypeRepository);
            },
            static function () use (&$amenityCalls, $amenityRepository): AmenityCatalogService {
                $amenityCalls++;

                return new AmenityCatalogService($amenityRepository);
            },
            static function () use (
                &$roomCalls,
                $roomRepository,
                $roomTypeRepository,
                $amenityRepository,
                $transaction,
            ): RoomCatalogService {
                $roomCalls++;

                return new RoomCatalogService(
                    $roomRepository,
                    $roomTypeRepository,
                    $amenityRepository,
                    $transaction,
                );
            },
        );

        self::assertSame(0, $roomTypeCalls);
        self::assertSame(0, $amenityCalls);
        self::assertSame(0, $roomCalls);
        self::assertSame($provider->roomTypes(), $provider->roomTypes());
        self::assertSame(1, $roomTypeCalls);
        self::assertSame(0, $amenityCalls);
        self::assertSame(0, $roomCalls);
        self::assertSame($provider->amenities(), $provider->amenities());
        self::assertSame(1, $amenityCalls);
        self::assertSame(0, $roomCalls);
        self::assertSame($provider->rooms(), $provider->rooms());
        self::assertSame(1, $roomCalls);
    }

    public function testUnexpectedFactoryFailuresBecomeSafeCatalogueFailures(): void
    {
        $provider = new RoomCatalogServiceProvider(
            static function (): never {
                throw new RuntimeException('sensitive connection details');
            },
            static fn (): string => 'invalid service',
            static fn (): string => 'invalid room service',
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

        try {
            $provider->rooms();
            self::fail('Expected an invalid-room-service failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }
    }

    public function testMissingRoomFactoryFailsClosed(): void
    {
        $provider = new RoomCatalogServiceProvider(
            fn (): RoomTypeCatalogService => new RoomTypeCatalogService(
                $this->createStub(RoomTypeCatalogRepository::class),
            ),
            fn (): AmenityCatalogService => new AmenityCatalogService(
                $this->createStub(AmenityCatalogRepository::class),
            ),
        );

        $this->expectException(RoomCatalogException::class);

        $provider->rooms();
    }
}
