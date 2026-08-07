<?php

declare(strict_types=1);

namespace App\Repositories;

/** Supplies active Administrator recipients for system-generated in-app notifications. */
interface AdministratorNotificationRecipientRepository
{
    /** @return list<int> */
    public function findActiveAdministratorIds(): array;
}
