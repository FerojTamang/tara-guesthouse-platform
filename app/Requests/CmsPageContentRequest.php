<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\CmsException;

/** Validates and normalizes the exact structured-page content fields. */
final readonly class CmsPageContentRequest
{
    private function __construct(
        private string $title,
        private string $slug,
        private string $content,
        private ?string $metaTitle,
        private ?string $metaDescription,
    ) {
    }

    public static function fromInput(
        mixed $title,
        mixed $slug,
        mixed $content,
        mixed $metaTitle,
        mixed $metaDescription,
    ): self {
        foreach ([$title, $slug, $content] as $value) {
            if (!is_string($value)) {
                throw CmsException::invalidInput('The page payload is invalid.');
            }
        }

        if (($metaTitle !== null && !is_string($metaTitle)) || ($metaDescription !== null && !is_string($metaDescription))) {
            throw CmsException::invalidInput('The page payload is invalid.');
        }

        $metaTitle = self::nullable($metaTitle);
        $metaDescription = self::nullable($metaDescription);

        return new self($title, $slug, $content, $metaTitle, $metaDescription);
    }

    public function title(): string { return $this->title; }
    public function slug(): string { return $this->slug; }
    public function content(): string { return $this->content; }
    public function metaTitle(): ?string { return $this->metaTitle; }
    public function metaDescription(): ?string { return $this->metaDescription; }

    private static function nullable(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
