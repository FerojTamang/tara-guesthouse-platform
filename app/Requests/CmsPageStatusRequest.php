<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\CmsException;
use App\Models\CmsPage;

/** Validates one exact CMS page status transition target. */
final readonly class CmsPageStatusRequest
{
    private function __construct(private string $status)
    {
    }

    public static function fromInput(mixed $status): self
    {
        if (!is_string($status) || !in_array($status, CmsPage::STATUSES, true)) {
            throw CmsException::invalidInput('The page status is invalid.');
        }

        return new self($status);
    }

    public function status(): string
    {
        return $this->status;
    }
}
