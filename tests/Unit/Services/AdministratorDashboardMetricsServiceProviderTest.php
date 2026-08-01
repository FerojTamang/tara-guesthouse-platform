<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\DatabaseException;
use App\Repositories\AdministratorDashboardMetricsRepository;
use App\Services\AdministratorDashboardMetricsService;
use App\Services\AdministratorDashboardMetricsServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorDashboardMetricsServiceProviderTest extends TestCase
{
    public function testProviderIsLazyAndCachesTheFirstService(): void
    {
        $calls = 0;
        $service = new AdministratorDashboardMetricsService($this->createStub(AdministratorDashboardMetricsRepository::class));
        $provider = new AdministratorDashboardMetricsServiceProvider(static function () use (&$calls, $service): AdministratorDashboardMetricsService {
            $calls++;
            return $service;
        });

        self::assertSame(0, $calls);
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testDependencyFailureIsSanitized(): void
    {
        $provider = new AdministratorDashboardMetricsServiceProvider(static function (): never {
            throw new RuntimeException('SQLSTATE PDO password');
        });
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Dashboard metrics failed.');
        $provider->service();
    }
}
