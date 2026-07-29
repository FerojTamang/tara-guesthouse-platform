<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Requests\GuestPaymentRequest;

/**
 * Defines the transactional Guest payment-submission use case.
 */
interface GuestPaymentSubmitter
{
    public function submit(GuestPaymentRequest $request): Payment;
}
