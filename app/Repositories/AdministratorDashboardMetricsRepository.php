<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Models\AdministratorDashboardMetrics;
use DateTimeImmutable;
interface AdministratorDashboardMetricsRepository { public function metrics(int $administratorId, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): AdministratorDashboardMetrics; }
