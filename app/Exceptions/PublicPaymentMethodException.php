<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents a safe public payment-method catalogue failure.
 */
final class PublicPaymentMethodException extends RuntimeException
{
    public static function failure(?Throwable $previous = null): self
    {
        return new self('Public payment method catalogue failed.', 0, $previous);
    }
}
