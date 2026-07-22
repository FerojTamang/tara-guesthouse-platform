<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use DateTimeImmutable;

/**
 * Defines the narrow persistence operations required by Administrator login.
 */
interface AdministratorAuthenticationRepository
{
    public function findAdministratorByUsername(string $username): ?User;

    public function findAdministratorByEmail(string $email): ?User;

    public function recordSuccessfulLogin(
        int $userId,
        #[\SensitiveParameter] string $expectedPasswordHash,
        #[\SensitiveParameter] ?string $replacementPasswordHash,
        DateTimeImmutable $loggedInAt,
    ): void;
}
