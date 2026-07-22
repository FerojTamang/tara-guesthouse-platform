<?php

declare(strict_types=1);

namespace App\Services;

use App\Authentication\AdministratorIdentity;
use App\Requests\AdministratorLoginRequest;

/**
 * Verifies one Administrator login request without owning the PHP session.
 */
interface AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity;
}
