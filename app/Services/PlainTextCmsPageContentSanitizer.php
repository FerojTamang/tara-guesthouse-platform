<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;

/** Normalizes explicitly approved plain-text page bodies without accepting HTML markup. */
final readonly class PlainTextCmsPageContentSanitizer implements CmsPageContentSanitizer
{
    public function __construct(private int $limit)
    {
        if ($limit < 1 || $limit > 1_000_000) {
            throw CmsException::failure();
        }
    }

    public function format(): string
    {
        return 'plain_text';
    }

    public function maximumLength(): int
    {
        return $this->limit;
    }

    public function sanitize(string $content): string
    {
        if (!mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            throw CmsException::invalidInput('The page content is invalid.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $normalized = preg_replace('/[\t ]+$/m', '', $normalized);

        if (!is_string($normalized)) {
            throw CmsException::failure();
        }

        $normalized = trim($normalized);

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > $this->limit
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
            || preg_match('/<\/?[A-Za-z][^>]*>/u', $normalized) === 1
        ) {
            throw CmsException::invalidInput('The page content is invalid.');
        }

        return $normalized;
    }
}
