<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Defines transaction controls for Administrator gallery mutations.
 */
interface GalleryTransaction
{
    public function begin(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inProgress(): bool;
}
