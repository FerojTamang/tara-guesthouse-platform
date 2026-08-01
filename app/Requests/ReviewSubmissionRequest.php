<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ReviewException;

/** Validates and normalizes one verified-stay Guest review submission. */
final readonly class ReviewSubmissionRequest
{
    private function __construct(
        private int $rating,
        private ?string $title,
        private string $comment,
    ) {
    }

    public static function fromInput(mixed $rating, mixed $title, mixed $comment): self
    {
        return new self(
            self::normalizeRating($rating),
            self::normalizeTitle($title),
            self::normalizeComment($comment),
        );
    }

    public function rating(): int { return $this->rating; }
    public function title(): ?string { return $this->title; }
    public function comment(): string { return $this->comment; }

    private static function normalizeRating(mixed $value): int
    {
        if (is_string($value) && preg_match('/^[1-5]$/D', $value) === 1) {
            $value = (int) $value;
        }

        if (!is_int($value) || $value < 1 || $value > 5) {
            throw ReviewException::invalidInput('Rating must be a whole number from 1 to 5.');
        }

        return $value;
    }

    private static function normalizeTitle(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ReviewException::invalidInput('Title is invalid.');
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > 150
            || preg_match('/[\x00-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ReviewException::invalidInput('Title is invalid.');
        }

        return $normalized;
    }

    private static function normalizeComment(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ReviewException::invalidInput('Comment is required.');
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $value));

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > 65535
            || strlen($normalized) > 65535
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ReviewException::invalidInput('Comment is invalid.');
        }

        return $normalized;
    }
}
