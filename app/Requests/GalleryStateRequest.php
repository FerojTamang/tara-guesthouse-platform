<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\GalleryException;

/**
 * Validates one exact Administrator-controlled gallery boolean state.
 */
final readonly class GalleryStateRequest
{
    private function __construct(private bool $enabled)
    {
    }

    public static function fromInput(mixed $value, string $label): self
    {
        if (!is_bool($value)) {
            throw GalleryException::invalidInput($label . ' must be true or false.');
        }

        return new self($value);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }
}
