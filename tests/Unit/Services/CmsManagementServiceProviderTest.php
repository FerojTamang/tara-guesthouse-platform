<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\SettingDefinition;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PageRepository;
use App\Repositories\SettingRepository;
use App\Services\AdministratorCmsService;
use App\Services\CmsManagementServiceProvider;
use App\Services\CmsPageService;
use App\Services\CmsSettingsService;
use App\Services\CmsSettingsServiceProvider;
use App\Services\CmsTransaction;
use App\Services\PlainTextCmsPageContentSanitizer;
use App\Services\SettingDefinitionRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CmsManagementServiceProviderTest extends TestCase
{
    public function testProviderBuildsLazilyAndCachesValidService(): void
    {
        $calls = 0;
        $service = new AdministratorCmsService(
            new CmsSettingsService(
                $this->createStub(SettingRepository::class),
                new SettingDefinitionRegistry([new SettingDefinition('hotel.name', 'string', true, 200)]),
            ),
            new CmsPageService($this->createStub(PageRepository::class)),
            new PlainTextCmsPageContentSanitizer(1000),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(CmsTransaction::class),
        );
        $provider = new CmsManagementServiceProvider(static function () use (&$calls, $service): AdministratorCmsService {
            $calls++;
            return $service;
        });

        self::assertSame(0, $calls);
        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }


    public function testPageReadsRemainAvailableWhenSettingDefinitionsAreNotApproved(): void
    {
        $settingsCalls = 0;
        $service = new AdministratorCmsService(
            new CmsSettingsServiceProvider(static function () use (&$settingsCalls): never {
                $settingsCalls++;
                throw new RuntimeException('unapproved setting definitions');
            }),
            new CmsPageService($this->createStub(PageRepository::class)),
            new PlainTextCmsPageContentSanitizer(1000),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(CmsTransaction::class),
        );

        self::assertSame([], $service->listPages());
        self::assertSame(0, $settingsCalls);

        try {
            $service->settingDefinitions();
            self::fail('Expected the settings gate to fail closed.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('CMS operation failed.', $exception->getMessage());
        }

        self::assertSame(1, $settingsCalls);
    }

    public function testInvalidFactoryResultsAndFailuresAreSanitized(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static function (): never { throw new RuntimeException('sensitive provider failure'); },
        ] as $factory) {
            try {
                (new CmsManagementServiceProvider($factory))->service();
                self::fail('Expected a safe CMS provider failure.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('CMS operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('sensitive', $exception->getMessage());
            }
        }
    }
}
