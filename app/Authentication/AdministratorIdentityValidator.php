<?php

declare(strict_types=1);

namespace App\Authentication;

/**
 * Confirms that a persisted Administrator identity remains eligible for access.
 */
interface AdministratorIdentityValidator
{
    public function isValid(AdministratorIdentity $identity): bool;
}
