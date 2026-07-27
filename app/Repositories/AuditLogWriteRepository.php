<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AuditLogEntry;

/**
 * Defines append-only persistence for required operational audit records.
 */
interface AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void;
}
