<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Defines one reversible, versioned database change.
 */
abstract class Migration
{
    abstract public function up(PDO $connection): void;

    abstract public function down(PDO $connection): void;

    public function transactional(): bool
    {
        return false;
    }
}
