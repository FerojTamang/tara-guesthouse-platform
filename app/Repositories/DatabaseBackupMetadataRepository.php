<?php

declare(strict_types=1);

namespace App\Repositories;

interface DatabaseBackupMetadataRepository
{
    /** @return array{migration_count: int, latest_migration: string} */
    public function read(): array;
}
