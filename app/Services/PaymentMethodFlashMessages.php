<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

/**
 * Stores one safe payment-method success message for the next Administrator request.
 */
final readonly class PaymentMethodFlashMessages
{
    private const SUCCESS_KEY = 'administrator_payment_method_success';

    public function __construct(private Session $session)
    {
    }

    public function success(string $message): void
    {
        $this->session->set(self::SUCCESS_KEY, $message);
    }

    public function pullSuccess(): ?string
    {
        $message = $this->session->pull(self::SUCCESS_KEY);

        return is_string($message) ? $message : null;
    }
}
