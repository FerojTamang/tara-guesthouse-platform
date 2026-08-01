<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/** Holds the required Administrator review-moderation note policy. */
final readonly class ReviewModerationPolicy
{
    public function __construct(
        private int $maximumNoteCharacters,
        private bool $rejectionNoteRequired,
    ) {
        if (
            $maximumNoteCharacters <= 0
            || $maximumNoteCharacters > 65535
            || $rejectionNoteRequired !== true
        ) {
            throw new InvalidArgumentException('Review moderation policy is invalid.');
        }
    }

    public function maximumNoteCharacters(): int
    {
        return $this->maximumNoteCharacters;
    }

    public function rejectionNoteRequired(): bool
    {
        return $this->rejectionNoteRequired;
    }
}
