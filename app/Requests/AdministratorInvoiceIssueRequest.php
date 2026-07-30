<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AdministratorInvoiceException;

/**
 * Validates the intentionally empty Administrator invoice issue payload.
 */
final readonly class AdministratorInvoiceIssueRequest
{
    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if ($payload !== []) {
            throw AdministratorInvoiceException::validation(
                'Invoice totals and numbers are derived by the server.',
            );
        }

        return new self();
    }
}
