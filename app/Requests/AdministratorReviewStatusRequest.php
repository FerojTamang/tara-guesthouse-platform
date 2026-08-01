<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ReviewException;
use App\Models\Review;
use App\Models\ReviewModerationPolicy;

/** Validates the narrow pending-review moderation decision. */
final readonly class AdministratorReviewStatusRequest
{
    private const TARGETS = [
        Review::STATUS_APPROVED,
        Review::STATUS_REJECTED,
    ];

    private function __construct(
        private string $targetStatus,
        private ?string $moderationNote,
    ) {
    }

    public static function fromInput(
        mixed $targetStatus,
        mixed $moderationNote,
        bool $moderationNoteProvided,
        ReviewModerationPolicy $policy,
    ): self {
        if (
            !is_string($targetStatus)
            || $targetStatus === ''
            || $targetStatus !== trim($targetStatus)
            || !in_array($targetStatus, self::TARGETS, true)
        ) {
            throw ReviewException::invalidInput('Review moderation action is invalid.');
        }

        $normalizedNote = self::normalizeNote(
            $moderationNote,
            $moderationNoteProvided,
            $policy,
        );

        if (
            $targetStatus === Review::STATUS_REJECTED
            && $policy->rejectionNoteRequired()
            && $normalizedNote === null
        ) {
            throw ReviewException::invalidInput(
                'A moderation note is required when rejecting a review.',
            );
        }

        return new self($targetStatus, $normalizedNote);
    }

    public function targetStatus(): string
    {
        return $this->targetStatus;
    }

    public function moderationNote(): ?string
    {
        return $this->moderationNote;
    }

    private static function normalizeNote(
        mixed $value,
        bool $provided,
        ReviewModerationPolicy $policy,
    ): ?string {
        if (!$provided) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        if (
            !is_string($value)
            || !mb_check_encoding($value, 'UTF-8')
            || preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u',
                $value,
            ) === 1
        ) {
            throw ReviewException::invalidInput('Moderation note is invalid.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($value));

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized, 'UTF-8') > $policy->maximumNoteCharacters()) {
            throw ReviewException::invalidInput(sprintf(
                'Moderation note must not exceed %d characters.',
                $policy->maximumNoteCharacters(),
            ));
        }

        return $normalized;
    }
}
