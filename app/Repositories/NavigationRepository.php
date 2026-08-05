<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\NavigationItem;

/** Defines persistence operations for Administrator-managed public navigation. */
interface NavigationRepository
{
    public function findById(int $id): ?NavigationItem;

    /** @return list<NavigationItem> */
    public function findAll(): array;

    /** @return list<NavigationItem> */
    public function findAllActive(): array;

    public function create(
        string $location,
        string $label,
        string $linkType,
        ?string $routeKey,
        ?int $pageId,
        ?int $parentId,
        int $sortOrder,
        int $actorId,
    ): NavigationItem;

    public function update(
        int $id,
        string $location,
        string $label,
        string $linkType,
        ?string $routeKey,
        ?int $pageId,
        ?int $parentId,
        int $sortOrder,
        int $actorId,
    ): ?NavigationItem;

    public function setActive(int $id, bool $active, int $actorId): ?NavigationItem;

    public function setSortOrder(int $id, int $sortOrder, int $actorId): bool;
}
