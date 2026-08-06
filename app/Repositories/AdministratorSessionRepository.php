<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

/**
 * Defines the narrow account lookup needed to revalidate Administrator sessions.
 */
interface AdministratorSessionRepository
{
    public function findAdministratorById(int $id): ?User;
}
