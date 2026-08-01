<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalleryException;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Repositories\GalleryCatalogRepository;
use Throwable;

/**
 * Provides shared reads for Administrator and public gallery workflows.
 */
final readonly class GalleryCatalogService
{
    public function __construct(private GalleryCatalogRepository $repository)
    {
    }

    /** @return list<GalleryCategory> */
    public function listCategories(): array
    {
        return $this->execute(fn (): array => $this->repository->findAllCategories());
    }

    /** @return list<GalleryCategory> */
    public function listActiveCategories(): array
    {
        return $this->execute(function (): array {
            return array_values(array_filter(
                $this->repository->findAllActiveCategories(),
                static fn (mixed $category): bool => $category instanceof GalleryCategory
                    && $category->isActive(),
            ));
        });
    }

    public function getCategory(int $id): GalleryCategory
    {
        return $this->execute(function () use ($id): GalleryCategory {
            $category = $this->repository->findCategoryById($id);

            if (!$category instanceof GalleryCategory) {
                throw GalleryException::categoryNotFound();
            }

            return $category;
        });
    }

    public function getCategoryBySlug(string $slug): GalleryCategory
    {
        return $this->execute(function () use ($slug): GalleryCategory {
            $category = $this->repository->findCategoryBySlug($slug);

            if (!$category instanceof GalleryCategory) {
                throw GalleryException::categoryNotFound();
            }

            return $category;
        });
    }

    /** @return list<GalleryItem> */
    public function listItems(): array
    {
        return $this->execute(fn (): array => $this->repository->findAllItems());
    }

    /** @return list<GalleryItem> */
    public function listPublicItems(): array
    {
        return $this->execute(function (): array {
            return array_values(array_filter(
                $this->repository->findAllPublicItems(),
                static fn (mixed $item): bool => $item instanceof GalleryItem
                    && $item->isPubliclyVisible(),
            ));
        });
    }

    public function getItem(int $id): GalleryItem
    {
        return $this->execute(function () use ($id): GalleryItem {
            $item = $this->repository->findItemById($id);

            if (!$item instanceof GalleryItem) {
                throw GalleryException::itemNotFound();
            }

            return $item;
        });
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw GalleryException::failure($throwable);
        }
    }
}
