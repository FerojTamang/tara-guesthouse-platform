<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PublicRoomCatalogException;
use Closure;
use Throwable;

final class PublicRoomCatalogServiceProvider
{
    private readonly Closure $factory;
    private ?PublicRoomCatalogService $service = null;
    public function __construct(callable $factory) { $this->factory = Closure::fromCallable($factory); }
    public function service(): PublicRoomCatalogService
    {
        if ($this->service !== null) { return $this->service; }
        try { $service = ($this->factory)(); if (!$service instanceof PublicRoomCatalogService) { throw PublicRoomCatalogException::failure(); } return $this->service = $service; }
        catch (PublicRoomCatalogException $exception) { throw $exception; }
        catch (Throwable $failure) { throw PublicRoomCatalogException::failure($failure); }
    }
}
