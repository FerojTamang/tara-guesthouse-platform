<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AdministratorPaymentRecord;

/**
 * Defines read access for authenticated Administrator payment review.
 */
interface AdministratorPaymentCatalogRepository
{
    /** @return list<AdministratorPaymentRecord> */
    public function findAll(?string $status = null): array;

    public function findById(int $id): ?AdministratorPaymentRecord;
}
