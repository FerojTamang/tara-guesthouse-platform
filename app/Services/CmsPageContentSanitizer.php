<?php

declare(strict_types=1);

namespace App\Services;

/** Defines the approved normalization contract for stored CMS page content. */
interface CmsPageContentSanitizer
{
    public function format(): string;

    public function maximumLength(): int;

    public function sanitize(string $content): string;
}
