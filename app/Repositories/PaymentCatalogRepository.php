<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Payment;

/**
 * Defines read access to retained payment attempts.
 */
interface PaymentCatalogRepository
{
    /** @return list<Payment> */
    public function findAll(?string $status = null): array;

    public function findById(int $id): ?Payment;

    /** @return list<Payment> */
    public function findAllByBookingId(int $bookingId): array;

    public function findVerifiedByBookingId(int $bookingId): ?Payment;
}
