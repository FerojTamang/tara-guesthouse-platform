<?php
declare(strict_types=1);
namespace App\Services;
use App\Exceptions\DatabaseException; use Closure; use Throwable;
final class AdministratorDashboardMetricsServiceProvider { private readonly Closure $factory; private ?AdministratorDashboardMetricsService $service=null; public function __construct(callable $factory){$this->factory=Closure::fromCallable($factory);} public function service():AdministratorDashboardMetricsService { if($this->service!==null)return $this->service; try{$service=($this->factory)(); if(!$service instanceof AdministratorDashboardMetricsService)throw new DatabaseException('Dashboard metrics failed.'); return $this->service=$service;}catch(Throwable $e){throw new DatabaseException('Dashboard metrics failed.');} } }
