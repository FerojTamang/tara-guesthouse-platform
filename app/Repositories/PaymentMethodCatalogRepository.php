<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PaymentMethod;

/**
 * Defines persistence operations for configured manual payment methods.
 */
interface PaymentMethodCatalogRepository
{
    public function findById(int $id): ?PaymentMethod;

    /** @return list<PaymentMethod> */
    public function findAll(): array;

    /** @return list<PaymentMethod> */
    public function findAllActive(): array;

    public function create(
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): PaymentMethod;

    public function update(
        int $id,
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): ?PaymentMethod;

    public function setActive(int $id, bool $active): ?PaymentMethod;
}
