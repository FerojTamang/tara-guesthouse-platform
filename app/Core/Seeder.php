<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Defines one deterministic, explicitly grouped seed operation.
 */
abstract class Seeder
{
    abstract public function group(): string;

    abstract public function run(PDO $connection): void;

    public function transactional(): bool
    {
        return true;
    }
}
