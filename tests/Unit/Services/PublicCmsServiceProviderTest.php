<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\SettingDefinition;
use App\Presentation\RoomImageUrl;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Repositories\PageRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Repositories\SettingRepository;
use App\Services\PublicActivityService;
use App\Services\PublicCmsService;
use App\Services\PublicCmsServiceProvider;
use App\Services\PublicGalleryService;
use App\Services\PublicRoomCatalogService;
use App\Services\SettingDefinitionRegistry;
use App\Storage\GalleryImageReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicCmsServiceProviderTest extends TestCase
{
    public function testItBuildsOnceAndCachesThePublicCmsService(): void
    {
        $service = $this->service();
        $calls = 0;
        $provider = new PublicCmsServiceProvider(static function () use ($service, &$calls): PublicCmsService {
            $calls++;
            return $service;
        });

        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoriesFailSafely(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static fn (): never => throw new RuntimeException('private factory detail'),
        ] as $factory) {
            try {
                (new PublicCmsServiceProvider($factory))->service();
                self::fail('Expected a safe public CMS provider failure.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('CMS operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('private', $exception->getMessage());
            }
        }
    }

    private function service(): PublicCmsService
    {
        return new PublicCmsService(
            $this->createStub(PageRepository::class),
            $this->createStub(SettingRepository::class),
            static fn (): SettingDefinitionRegistry => new SettingDefinitionRegistry([
                new SettingDefinition('contact.email', 'email', true, 254),
            ]),
            new PublicRoomCatalogService(
                $this->createStub(RoomCatalogRepository::class),
                $this->createStub(RoomImageCatalogRepository::class),
                new RoomImageUrl(),
            ),
            new PublicActivityService($this->createStub(ActivityCatalogRepository::class)),
            new PublicGalleryService(
                $this->createStub(GalleryCatalogRepository::class),
                $this->createStub(GalleryImageReader::class),
            ),
            'Tara Guest House',
            'https://example.test',
        );
    }
}
