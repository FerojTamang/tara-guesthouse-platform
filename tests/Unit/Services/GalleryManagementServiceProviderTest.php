<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GalleryException;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Services\AdministratorGalleryService;
use App\Services\GalleryManagementServiceProvider;
use App\Services\GalleryTransaction;
use App\Storage\GalleryImageStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GalleryManagementServiceProviderTest extends TestCase
{
    public function testItBuildsLazilyAndCachesTheService(): void
    {
        $calls = 0;
        $service = new AdministratorGalleryService(
            $this->createStub(GalleryCatalogRepository::class),
            $this->createStub(GalleryImageStorage::class),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(GalleryTransaction::class),
        );
        $provider = new GalleryManagementServiceProvider(
            static function () use (&$calls, $service): AdministratorGalleryService {
                $calls++;

                return $service;
            },
        );

        self::assertSame(0, $calls);
        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryResultsAndFailuresAreSanitized(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static function (): never {
                throw new RuntimeException('sensitive connection details');
            },
        ] as $factory) {
            try {
                (new GalleryManagementServiceProvider($factory))->service();
                self::fail('Expected a safe gallery provider failure.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Gallery catalogue operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('sensitive', $exception->getMessage());
            }
        }
    }
}
