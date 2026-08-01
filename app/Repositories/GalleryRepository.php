<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Exceptions\GalleryException;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Provides PDO persistence for Version 1 gallery categories and items.
 */
final class GalleryRepository implements GalleryCatalogRepository
{
    use MapsRepositoryRows;

    private const CATEGORY_COLUMNS = <<<'SQL'
        SELECT
            id,
            name,
            slug,
            is_active,
            created_at,
            updated_at
        FROM gallery_categories
        SQL;

    private const ITEM_COLUMNS = <<<'SQL'
        SELECT
            g.id,
            g.gallery_category_id,
            g.title,
            g.caption,
            g.file_path,
            g.alt_text,
            g.sort_order,
            g.is_active,
            g.is_published,
            g.is_homepage_featured,
            g.homepage_sort_order,
            g.created_at,
            g.updated_at
        FROM gallery g
        SQL;

    private const CATEGORY_ORDER = ' ORDER BY name ASC, id ASC';
    private const ITEM_ORDER = ' ORDER BY sort_order ASC, id ASC';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findCategoryById(int $id): ?GalleryCategory
    {
        if ($id <= 0) {
            return null;
        }

        return $this->categoryLookup(
            self::CATEGORY_COLUMNS . ' WHERE id = :lookup LIMIT 1',
            $id,
            PDO::PARAM_INT,
        );
    }

    public function lockCategoryById(int $id): ?GalleryCategory
    {
        if ($id <= 0) {
            return null;
        }

        return $this->categoryLookup(
            self::CATEGORY_COLUMNS . ' WHERE id = :lookup LIMIT 1 FOR UPDATE',
            $id,
            PDO::PARAM_INT,
        );
    }

    public function findCategoryBySlug(string $slug): ?GalleryCategory
    {
        if ($slug === '') {
            return null;
        }

        return $this->categoryLookup(
            self::CATEGORY_COLUMNS . ' WHERE slug = :lookup LIMIT 1',
            $slug,
            PDO::PARAM_STR,
        );
    }

    /**
     * @return list<GalleryCategory>
     */
    public function findAllCategories(): array
    {
        return $this->categoryListing(self::CATEGORY_COLUMNS . self::CATEGORY_ORDER);
    }

    /**
     * @return list<GalleryCategory>
     */
    public function findAllActiveCategories(): array
    {
        return $this->categoryListing(
            self::CATEGORY_COLUMNS . ' WHERE is_active = 1' . self::CATEGORY_ORDER,
        );
    }

    public function createCategory(string $name, string $slug, bool $active): GalleryCategory
    {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO gallery_categories (name, slug, is_active) '
                . 'VALUES (:name, :slug, :is_active)',
            );
            $statement->execute([
                'name' => $name,
                'slug' => $slug,
                'is_active' => $active ? 1 : 0,
            ]);
            $category = $this->findCategoryById($this->generatedIdentifier());

            if (!$category instanceof GalleryCategory) {
                throw new RuntimeException('The created gallery category could not be loaded.');
            }

            return $category;
        } catch (PDOException $exception) {
            $this->throwCategoryWriteFailure($exception);
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The gallery category write failed.');
        }
    }

    public function updateCategory(int $id, string $name, string $slug): ?GalleryCategory
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE gallery_categories SET name = :name, slug = :slug WHERE id = :id',
            );
            $statement->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);

            return $this->findCategoryById($id);
        } catch (PDOException $exception) {
            $this->throwCategoryWriteFailure($exception);
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The gallery category write failed.');
        }
    }

    public function setCategoryActive(int $id, bool $active): ?GalleryCategory
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE gallery_categories SET is_active = :is_active WHERE id = :id',
            );
            $statement->execute(['id' => $id, 'is_active' => $active ? 1 : 0]);

            return $this->findCategoryById($id);
        } catch (Throwable) {
            throw new DatabaseException('The gallery category state update failed.');
        }
    }

    public function findItemById(int $id): ?GalleryItem
    {
        if ($id <= 0) {
            return null;
        }

        return $this->itemLookup(
            self::ITEM_COLUMNS . ' WHERE g.id = :lookup LIMIT 1',
            $id,
        );
    }

    public function findPublicItemById(int $id): ?GalleryItem
    {
        if ($id <= 0) {
            return null;
        }

        return $this->itemLookup(
            self::ITEM_COLUMNS
            . ' LEFT JOIN gallery_categories c ON c.id = g.gallery_category_id'
            . ' WHERE g.id = :lookup AND g.is_active = 1 AND g.is_published = 1'
            . ' AND (g.gallery_category_id IS NULL OR c.is_active = 1)'
            . ' LIMIT 1',
            $id,
        );
    }

    public function lockItemById(int $id): ?GalleryItem
    {
        if ($id <= 0) {
            return null;
        }

        return $this->itemLookup(
            self::ITEM_COLUMNS . ' WHERE g.id = :lookup LIMIT 1 FOR UPDATE',
            $id,
        );
    }

    /**
     * @return list<GalleryItem>
     */
    public function findAllItems(): array
    {
        return $this->itemListing(self::ITEM_COLUMNS . self::ITEM_ORDER);
    }

    /**
     * @return list<GalleryItem>
     */
    public function findAllPublicItems(): array
    {
        return $this->itemListing(
            self::ITEM_COLUMNS
            . ' LEFT JOIN gallery_categories c ON c.id = g.gallery_category_id'
            . ' WHERE g.is_active = 1 AND g.is_published = 1'
            . ' AND (g.gallery_category_id IS NULL OR c.is_active = 1)'
            . self::ITEM_ORDER,
        );
    }

    public function findHomepageGalleryItems(int $limit): array
    {
        if ($limit < 1 || $limit > 6) {
            throw GalleryException::invalidInput('Homepage gallery limit is invalid.');
        }

        return $this->itemListing(
            self::ITEM_COLUMNS
                . ' LEFT JOIN gallery_categories c ON c.id = g.gallery_category_id'
                . ' WHERE g.is_active = 1 AND g.is_published = 1'
                . ' AND g.is_homepage_featured = 1'
                . ' AND (g.gallery_category_id IS NULL OR c.is_active = 1)'
                . ' ORDER BY g.homepage_sort_order ASC, g.sort_order ASC, g.id ASC LIMIT :limit',
            $limit,
        );
    }

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
    ): GalleryItem {
        $this->assertItemInput($categoryId, $filePath, $sortOrder);

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO gallery ('
                . 'gallery_category_id, title, caption, file_path, alt_text, sort_order, '
                . 'is_active, is_published, is_homepage_featured, homepage_sort_order'
                . ') VALUES ('
                . ':gallery_category_id, :title, :caption, :file_path, :alt_text, :sort_order, '
                . ':is_active, :is_published, :is_homepage_featured, :homepage_sort_order'
                . ')',
            );
            $statement->bindValue(
                'gallery_category_id',
                $categoryId,
                $categoryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
            );
            $statement->bindValue('title', $title, PDO::PARAM_STR);
            $statement->bindValue('caption', $caption, $caption === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('file_path', $filePath, PDO::PARAM_STR);
            $statement->bindValue('alt_text', $altText, PDO::PARAM_STR);
            $statement->bindValue('sort_order', $sortOrder, PDO::PARAM_INT);
            $statement->bindValue('is_active', $active ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('is_published', $published ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('is_homepage_featured', $homepageFeatured ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('homepage_sort_order', $homepageSortOrder, PDO::PARAM_INT);
            $statement->execute();
            $item = $this->findItemById($this->generatedIdentifier());

            if (!$item instanceof GalleryItem) {
                throw new RuntimeException('The created gallery item could not be loaded.');
            }

            return $item;
        } catch (PDOException) {
            throw new DatabaseException('The gallery item write failed.');
        } catch (Throwable $throwable) {
            if ($throwable instanceof DatabaseException || $throwable instanceof GalleryException) {
                throw $throwable;
            }

            throw new DatabaseException('The gallery item write failed.');
        }
    }

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
    ): ?GalleryItem {
        if ($id <= 0) {
            return null;
        }

        $this->assertItemInput($categoryId, $filePath, $sortOrder);

        try {
            $statement = $this->connection->prepare(
                'UPDATE gallery SET '
                . 'gallery_category_id = :gallery_category_id, title = :title, caption = :caption, '
                . 'file_path = :file_path, alt_text = :alt_text, sort_order = :sort_order, '
                . 'is_homepage_featured = :is_homepage_featured, '
                . 'homepage_sort_order = :homepage_sort_order '
                . 'WHERE id = :id',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->bindValue(
                'gallery_category_id',
                $categoryId,
                $categoryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
            );
            $statement->bindValue('title', $title, PDO::PARAM_STR);
            $statement->bindValue('caption', $caption, $caption === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('file_path', $filePath, PDO::PARAM_STR);
            $statement->bindValue('alt_text', $altText, PDO::PARAM_STR);
            $statement->bindValue('sort_order', $sortOrder, PDO::PARAM_INT);
            $statement->bindValue('is_homepage_featured', $homepageFeatured ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('homepage_sort_order', $homepageSortOrder, PDO::PARAM_INT);
            $statement->execute();

            return $this->findItemById($id);
        } catch (PDOException) {
            throw new DatabaseException('The gallery item write failed.');
        } catch (Throwable $throwable) {
            if ($throwable instanceof DatabaseException || $throwable instanceof GalleryException) {
                throw $throwable;
            }

            throw new DatabaseException('The gallery item write failed.');
        }
    }

    public function setItemActive(int $id, bool $active): ?GalleryItem
    {
        return $this->setItemBooleanState($id, 'is_active', $active);
    }

    public function setItemPublished(int $id, bool $published): ?GalleryItem
    {
        return $this->setItemBooleanState($id, 'is_published', $published);
    }

    public function setItemSortOrder(int $id, int $sortOrder): ?GalleryItem
    {
        if ($id <= 0) {
            return null;
        }

        if ($sortOrder < 0) {
            throw GalleryException::invalidInput('Gallery sort order must be non-negative.');
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE gallery SET sort_order = :sort_order WHERE id = :id',
            );
            $statement->execute(['id' => $id, 'sort_order' => $sortOrder]);

            return $this->findItemById($id);
        } catch (Throwable) {
            throw new DatabaseException('The gallery item order update failed.');
        }
    }

    public function deactivateAndUnpublishItem(int $id): ?GalleryItem
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE gallery SET is_active = 0, is_published = 0 WHERE id = :id',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->execute();

            return $this->findItemById($id);
        } catch (Throwable) {
            throw new DatabaseException('The gallery item state update failed.');
        }
    }

    private function categoryLookup(string $sql, int|string $value, int $type): ?GalleryCategory
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, $type);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The gallery category could not be mapped.');
            }

            return $this->mapCategory($row);
        } catch (Throwable) {
            throw new DatabaseException('The gallery category lookup failed.');
        }
    }

    /** @return list<GalleryCategory> */
    private function categoryListing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The gallery categories could not be mapped.');
            }

            return array_map(function (mixed $row): GalleryCategory {
                if (!is_array($row)) {
                    throw new RuntimeException('The gallery categories could not be mapped.');
                }

                return $this->mapCategory($row);
            }, $rows);
        } catch (Throwable) {
            throw new DatabaseException('The gallery category listing failed.');
        }
    }

    private function itemLookup(string $sql, int $id): ?GalleryItem
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $id, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The gallery item could not be mapped.');
            }

            return $this->mapItem($row);
        } catch (Throwable) {
            throw new DatabaseException('The gallery item lookup failed.');
        }
    }

    /** @return list<GalleryItem> */
    private function itemListing(string $sql, ?int $limit = null): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            if ($limit !== null) {
                $statement->bindValue('limit', $limit, PDO::PARAM_INT);
            }
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The gallery items could not be mapped.');
            }

            return array_map(function (mixed $row): GalleryItem {
                if (!is_array($row)) {
                    throw new RuntimeException('The gallery items could not be mapped.');
                }

                return $this->mapItem($row);
            }, $rows);
        } catch (Throwable) {
            throw new DatabaseException('The gallery item listing failed.');
        }
    }

    private function setItemBooleanState(int $id, string $column, bool $value): ?GalleryItem
    {
        if ($id <= 0) {
            return null;
        }

        if (!in_array($column, ['is_active', 'is_published'], true)) {
            throw new DatabaseException('The gallery item state update failed.');
        }

        try {
            $statement = $this->connection->prepare(
                sprintf('UPDATE gallery SET %s = :state WHERE id = :id', $column),
            );
            $statement->execute(['id' => $id, 'state' => $value ? 1 : 0]);

            return $this->findItemById($id);
        } catch (Throwable) {
            throw new DatabaseException('The gallery item state update failed.');
        }
    }

    /** @param array<string, mixed> $row */
    private function mapCategory(array $row): GalleryCategory
    {
        return new GalleryCategory(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'name'),
            $this->string($row, 'slug'),
            $this->boolean($row, 'is_active'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function mapItem(array $row): GalleryItem
    {
        return new GalleryItem(
            $this->positiveInteger($row, 'id'),
            $this->nullablePositiveInteger($row, 'gallery_category_id'),
            $this->string($row, 'title'),
            $this->nullableString($row, 'caption'),
            $this->string($row, 'file_path'),
            $this->string($row, 'alt_text'),
            $this->nonNegativeInteger($row, 'sort_order'),
            $this->boolean($row, 'is_active'),
            $this->boolean($row, 'is_published'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
            $this->boolean($row, 'is_homepage_featured'),
            $this->nonNegativeInteger($row, 'homepage_sort_order'),
        );
    }

    /** @param array<string, mixed> $row */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            throw new RuntimeException('The database record could not be mapped.');
        }

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The database record could not be mapped.');
        }

        return $integer;
    }

    private function assertItemInput(?int $categoryId, string $filePath, int $sortOrder): void
    {
        if ($categoryId !== null && $categoryId <= 0) {
            throw GalleryException::invalidInput('Gallery category identifier is invalid.');
        }

        if ($sortOrder < 0) {
            throw GalleryException::invalidInput('Gallery sort order must be non-negative.');
        }

        if (!$this->isSafeRelativePath($filePath)) {
            throw GalleryException::invalidInput('Gallery file path is invalid.');
        }
    }

    private function isSafeRelativePath(string $path): bool
    {
        return $path !== ''
            && mb_check_encoding($path, 'UTF-8')
            && mb_strlen($path, 'UTF-8') <= 500
            && preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._-]*(?:\/[A-Za-z0-9][A-Za-z0-9._-]*)*$/D',
                $path,
            ) === 1;
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated gallery identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated gallery identifier is invalid.');
        }

        return $integer;
    }

    private function throwCategoryWriteFailure(PDOException $exception): never
    {
        if (
            (string) $exception->getCode() === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062
        ) {
            $detail = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

            if (str_contains($detail, 'uq_gallery_categories_name')) {
                throw GalleryException::duplicateCategoryName($exception);
            }

            if (str_contains($detail, 'uq_gallery_categories_slug')) {
                throw GalleryException::duplicateCategorySlug($exception);
            }
        }

        throw new DatabaseException('The gallery category write failed.');
    }
}
