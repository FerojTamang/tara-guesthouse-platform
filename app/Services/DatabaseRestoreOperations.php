<?php

declare(strict_types=1);

namespace App\Services;

interface DatabaseRestoreOperations
{
    public function preflight(string $backupBasename): DatabaseRestorePreflightResult;

    public function execute(string $backupBasename): DatabaseRestoreResult;
}
