<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CmsPage;
use DateTimeImmutable;

/** Defines persistence operations for structured CMS pages. */
interface PageRepository
{
    public function findById(int $id): ?CmsPage;
    public function findBySlug(string $slug): ?CmsPage;
    public function findPublishedBySlug(string $slug): ?CmsPage;

    /** @return list<CmsPage> */
    public function findAll(): array;

    /** @return list<CmsPage> */
    public function findAllPublished(): array;

    public function createDraft(
        string $title,
        string $slug,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        int $actorId,
    ): CmsPage;

    public function updateContent(
        int $id,
        string $title,
        string $slug,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        int $actorId,
    ): ?CmsPage;

    public function transitionStatus(
        int $id,
        string $expectedStatus,
        string $newStatus,
        ?DateTimeImmutable $publishedAt,
        int $actorId,
    ): ?CmsPage;
}
