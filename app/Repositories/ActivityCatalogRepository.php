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

    /** @return list<Activity> */
    public function findAll(): array;

    /** @return list<Activity> */
    public function findAllPublic(): array;

    public function create(
        string $title,
        string $slug,
        string $description,
        string $price,
        bool $active,
        bool $published,
        int $sortOrder,
    ): Activity;

    public function update(
        int $id,
        string $title,
        string $slug,
        string $description,
        string $price,
        int $sortOrder,
    ): ?Activity;

    public function setActive(int $id, bool $active): ?Activity;

    public function setPublished(int $id, bool $published): ?Activity;

    public function deactivateAndUnpublish(int $id): ?Activity;
}
