<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ContentMediaException;

/** Holds normalized accessible metadata for one content image. */
final readonly class ContentMediaMetadataRequest
{
    private function __construct(
        private ?string $title,
        private string $altText,
        private ?string $caption,
    ) {
    }

    public static function fromInput(mixed $title, mixed $altText, mixed $caption): self
    {
        return new self(
            self::optionalText($title, 200, 'Media title'),
            self::requiredText($altText, 255, 'Alternative text'),
            self::optionalText($caption, 500, 'Media caption'),
        );
    }

    public function title(): ?string { return $this->title; }
    public function altText(): string { return $this->altText; }
    public function caption(): ?string { return $this->caption; }

    private static function requiredText(mixed $value, int $maximumLength, string $label): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw ContentMediaException::invalidInput($label . ' is invalid.');
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value, 'UTF-8') > $maximumLength) {
            throw ContentMediaException::invalidInput($label . ' is invalid.');
        }

        return $value;
    }

    private static function optionalText(mixed $value, int $maximumLength, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::requiredText($value, $maximumLength, $label);
    }
}
