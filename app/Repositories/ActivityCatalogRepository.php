<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Activity;

/**
 * Defines persistence operations for the Version 1 activity catalogue.
 */
interface ActivityCatalogRepository
{
    public function findById(int $id): ?Activity;

    public function lockById(int $id): ?Activity;

    public function findBySlug(string $slug): ?Activity;

    public function findPublicById(int $id): ?Activity;

    /** @return list<Activity> */
    public function findAll(): array;

    /** @return list<Activity> */
    public function findAllPublic(): array;

    /** @return list<Activity> */
    public function findHomepageActivities(int $limit): array;

    public function create(
        string $title,
        string $slug,
        string $description,
        ?string $price,
        bool $active,
        bool $published,
        int $sortOrder,
        ?string $summary = null,
        ?string $featuredImagePath = null,
        ?string $priceUnit = null,
        ?string $durationLabel = null,
        ?string $location = null,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ): Activity;

    public function update(
        int $id,
        string $title,
        string $slug,
        string $description,
        ?string $price,
        int $sortOrder,
        ?string $summary = null,
        ?string $featuredImagePath = null,
        ?string $priceUnit = null,
        ?string $durationLabel = null,
        ?string $location = null,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ): ?Activity;

    public function setActive(int $id, bool $active): ?Activity;

    public function setPublished(int $id, bool $published): ?Activity;

    public function deactivateAndUnpublish(int $id): ?Activity;
}
