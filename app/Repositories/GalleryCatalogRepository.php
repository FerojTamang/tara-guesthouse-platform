<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\GalleryCategory;
use App\Models\GalleryItem;

/**
 * Defines persistence operations for the Version 1 gallery catalogue.
 */
interface GalleryCatalogRepository
{
    public function findCategoryById(int $id): ?GalleryCategory;

    public function lockCategoryById(int $id): ?GalleryCategory;

    public function findCategoryBySlug(string $slug): ?GalleryCategory;

    /** @return list<GalleryCategory> */
    public function findAllCategories(): array;

    /** @return list<GalleryCategory> */
    public function findAllActiveCategories(): array;

    public function createCategory(string $name, string $slug, bool $active): GalleryCategory;

    public function updateCategory(int $id, string $name, string $slug): ?GalleryCategory;

    public function setCategoryActive(int $id, bool $active): ?GalleryCategory;

    public function findItemById(int $id): ?GalleryItem;

    public function findPublicItemById(int $id): ?GalleryItem;

    public function lockItemById(int $id): ?GalleryItem;

    /** @return list<GalleryItem> */
    public function findAllItems(): array;

    /** @return list<GalleryItem> */
    public function findAllPublicItems(): array;

    /** @return list<GalleryItem> */
    public function findHomepageGalleryItems(int $limit): array;

    public function createItem(
        ?int $categoryId,
        string $title,
        ?string $caption,
        string $filePath,
        string $altText,
        int $sortOrder,
        bool $active,
        bool $published,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ): GalleryItem;

    public function updateItem(
        int $id,
        ?int $categoryId,
        string $title,
        ?string $caption,
        string $filePath,
        string $altText,
        int $sortOrder,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ): ?GalleryItem;

    public function setItemActive(int $id, bool $active): ?GalleryItem;

    public function setItemPublished(int $id, bool $published): ?GalleryItem;

    public function setItemSortOrder(int $id, int $sortOrder): ?GalleryItem;

    public function deactivateAndUnpublishItem(int $id): ?GalleryItem;
}
