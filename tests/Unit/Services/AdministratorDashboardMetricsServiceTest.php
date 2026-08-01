<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\DatabaseException;
use App\Models\AdministratorDashboardMetrics;
use App\Repositories\AdministratorDashboardMetricsRepository;
use App\Services\AdministratorDashboardMetricsService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorDashboardMetricsServiceTest extends TestCase
{
    public function testForwardsAdministratorAndKathmanduUtcInclusiveExclusiveBoundariesOnce(): void
    {
        $expected = $this->metrics();
        $repository = $this->createMock(AdministratorDashboardMetricsRepository::class);
        $repository->expects(self::once())->method('metrics')->with(
            7,
            self::callback(static fn (DateTimeImmutable $value): bool => $value->format('Y-m-d H:i:s') === '2026-07-31 18:15:00'),
            self::callback(static fn (DateTimeImmutable $value): bool => $value->format('Y-m-d H:i:s') === '2026-08-01 18:15:00'),
        )->willReturn($expected);

        self::assertSame($expected, (new AdministratorDashboardMetricsService($repository))->today(7, new DateTimeImmutable('2026-08-01 12:00:00 UTC')));
    }

    public function testInvalidAdministratorDoesNotAccessRepository(): void
    {
        $repository = $this->createMock(AdministratorDashboardMetricsRepository::class);
        $repository->expects(self::never())->method('metrics');
        $this->expectException(DatabaseException::class);
        (new AdministratorDashboardMetricsService($repository))->today(0);
    }

    public function testRepositoryFailureIsSanitized(): void
    {
        $repository = $this->createStub(AdministratorDashboardMetricsRepository::class);
        $repository->method('metrics')->willThrowException(new RuntimeException('SQLSTATE[HY000] payments password secret'));
        try {
            (new AdministratorDashboardMetricsService($repository))->today(1);
            self::fail('A repository failure must be converted.');
        } catch (DatabaseException $exception) {
            self::assertSame('Dashboard metrics failed.', $exception->getMessage());
        }
    }

    private function metrics(): AdministratorDashboardMetrics
    {
        return new AdministratorDashboardMetrics(new DateTimeImmutable('2026-07-31 18:15 UTC'), new DateTimeImmutable('2026-08-01 18:15 UTC'), 1, 0, 0, 0, 0, 0, 0, 0, 0);
    }
}
