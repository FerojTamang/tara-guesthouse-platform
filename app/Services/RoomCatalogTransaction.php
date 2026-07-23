<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Defines the transaction controls required by room administration services.
 */
interface RoomCatalogTransaction
{
    public function begin(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inProgress(): bool;
}
