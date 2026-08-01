<?php

declare(strict_types=1);

namespace App\Repositories;

interface DatabaseRestorePreflightRepository
{
    /** @return array{exists: bool, table_count: int, view_count: int} */
    public function inspectTarget(string $databaseName): array;
}
