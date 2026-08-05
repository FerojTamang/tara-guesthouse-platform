<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/** Combines one publicly eligible page with its publishing profile and featured media. */
final readonly class PublishedContentItem
{
    public function __construct(
        private CmsPage $page,
        private PageContentProfile $profile,
        private ?ContentMedia $featuredMedia,
    ) {
        if (
            !$page->isPublished()
            || !($page->publishedAt() instanceof \DateTimeImmutable)
            || $page->id() !== $profile->pageId()
            || !in_array($profile->contentType(), ['news', 'notice'], true)
            || (
                $featuredMedia instanceof ContentMedia
                && (
                    $profile->featuredMediaId() !== $featuredMedia->id()
                    || $featuredMedia->isDeleted()
                )
            )
        ) {
            throw new InvalidArgumentException('Published content item data is invalid.');
        }
    }

    public function page(): CmsPage { return $this->page; }
    public function profile(): PageContentProfile { return $this->profile; }
    public function featuredMedia(): ?ContentMedia { return $this->featuredMedia; }
}
