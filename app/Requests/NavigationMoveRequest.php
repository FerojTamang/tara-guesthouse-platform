<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\CmsException;

/** Validates one move-up or move-down navigation command. */
final readonly class NavigationMoveRequest
{
    private function __construct(private string $direction)
    {
    }

    public static function fromInput(mixed $direction): self
    {
        if (!is_string($direction) || !in_array($direction, ['up', 'down'], true)) {
            throw CmsException::invalidInput('The navigation move direction is invalid.');
        }

        return new self($direction);
    }

    public function direction(): string
    {
        return $this->direction;
    }
}
