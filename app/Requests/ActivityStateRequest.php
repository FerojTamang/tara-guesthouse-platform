<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ActivityException;

/**
 * Validates one exact Administrator-controlled activity boolean state.
 */
final readonly class ActivityStateRequest
{
    private function __construct(private bool $enabled)
    {
    }

    public static function fromInput(mixed $value, string $label): self
    {
        if (!is_bool($value)) {
            throw ActivityException::invalidInput($label . ' must be true or false.');
        }

        return new self($value);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }
}
