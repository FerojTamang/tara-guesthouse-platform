<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DatabaseException;
use App\Models\AdministratorDashboardMetrics;
use App\Repositories\AdministratorDashboardMetricsRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final readonly class AdministratorDashboardMetricsService
{
    public function __construct(private AdministratorDashboardMetricsRepository $repository) {}

    public function today(int $administratorId, ?DateTimeImmutable $now = null): AdministratorDashboardMetrics
    {
        try {
            if ($administratorId <= 0) {
                throw new DatabaseException('Dashboard metrics failed.');
            }

            $kathmandu = new DateTimeZone('Asia/Kathmandu');
            $utc = new DateTimeZone('UTC');
            $local = ($now ?? new DateTimeImmutable('now', $utc))->setTimezone($kathmandu);
            $start = (new DateTimeImmutable($local->format('Y-m-d') . ' 00:00:00', $kathmandu))
                ->setTimezone($utc);
            $end = $start->modify('+1 day');

            return $this->repository->metrics($administratorId, $start, $end);
        } catch (Throwable) {
            throw new DatabaseException('Dashboard metrics failed.');
        }
    }
}
