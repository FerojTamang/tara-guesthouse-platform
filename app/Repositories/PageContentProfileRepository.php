<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PageContentProfile;
use DateTimeImmutable;

/** Persists queryable page metadata and validated block-media references. */
interface PageContentProfileRepository
{
    public function findByPageId(int $pageId): ?PageContentProfile;

    /**
     * @param list<int> $pageIds
     * @return array<int, PageContentProfile>
     */
    public function findByPageIds(array $pageIds): array;

    public function upsertCore(
        int $pageId,
        string $contentType,
        ?string $summary,
        int $updatedBy,
    ): PageContentProfile;

    public function updatePublishing(
        int $pageId,
        ?string $noticeStyle,
        ?DateTimeImmutable $publishAt,
        ?DateTimeImmutable $expiresAt,
        bool $featured,
        bool $pinned,
        ?int $featuredMediaId,
        ?string $socialTitle,
        ?string $socialDescription,
        ?int $socialMediaId,
        bool $seoNoindex,
        int $updatedBy,
    ): PageContentProfile;

    public function updatePublication(
        int $pageId,
        DateTimeImmutable $publishAt,
        int $updatedBy,
    ): void;

    /**
     * @param list<array{media_id: int, block_key: string, sort_order: int}> $usages
     */
    public function replaceBlockMedia(int $pageId, array $usages): void;
}
