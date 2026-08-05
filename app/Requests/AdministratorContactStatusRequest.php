<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ContactException;
use App\Models\ContactMessage;

/** Validates the narrow Administrator close/reopen contact workflow. */
final readonly class AdministratorContactStatusRequest
{
    private const TARGETS = [
        ContactMessage::STATUS_CLOSED,
        ContactMessage::STATUS_IN_PROGRESS,
    ];

    private function __construct(private string $targetStatus)
    {
    }

    public static function fromInput(mixed $targetStatus): self
    {
        if (
            !is_string($targetStatus)
            || $targetStatus !== trim($targetStatus)
            || !in_array($targetStatus, self::TARGETS, true)
        ) {
            throw ContactException::invalidInput('Contact workflow action is invalid.');
        }

        return new self($targetStatus);
    }

    public function targetStatus(): string
    {
        return $this->targetStatus;
    }
}
