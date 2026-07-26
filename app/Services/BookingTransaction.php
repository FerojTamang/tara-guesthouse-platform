<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Defines transaction controls used by the booking-creation workflow.
 */
interface BookingTransaction
{
    public function begin(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inProgress(): bool;
}
