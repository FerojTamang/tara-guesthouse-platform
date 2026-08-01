<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ActivityException;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Services\ActivityManagementServiceProvider;
use App\Services\ActivityTransaction;
use App\Services\AdministratorActivityService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ActivityManagementServiceProviderTest extends TestCase
{
    public function testItBuildsLazilyAndCachesTheManagementService(): void
    {
        $calls = 0;
        $service = new AdministratorActivityService(
            $this->createStub(ActivityCatalogRepository::class),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(ActivityTransaction::class),
        );
        $provider = new ActivityManagementServiceProvider(
            static function () use (&$calls, $service): AdministratorActivityService {
                $calls++;

                return $service;
            },
        );

        self::assertSame(0, $calls);
        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryResultBecomesASafeFailure(): void
    {
        $provider = new ActivityManagementServiceProvider(
            static fn (): object => new \stdClass(),
        );

        $this->expectException(ActivityException::class);
        $this->expectExceptionMessage('Activity catalogue operation failed.');

        $provider->service();
    }

    public function testUnexpectedFactoryFailureIsSanitized(): void
    {
        $provider = new ActivityManagementServiceProvider(
            static function (): never {
                throw new RuntimeException('sensitive connection details');
            },
        );

        try {
            $provider->service();
            self::fail('Expected a safe activity provider failure.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive connection details', $exception->getMessage());
        }
    }
}
