<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalleryException;
use App\Models\GalleryItem;
use App\Repositories\GalleryCatalogRepository;
use App\Storage\GalleryImageReader;
use App\Storage\RetrievedGalleryImage;
use Throwable;

/**
 * Exposes the approved public gallery projection and eligible image bytes.
 */
final readonly class PublicGalleryService
{
    public function __construct(
        private GalleryCatalogRepository $repository,
        private GalleryImageReader $images,
    ) {
    }

    /**
     * @return list<array{
     *     gallery_id: int,
     *     title: string,
     *     caption: ?string,
     *     alt_text: string,
     *     image_url: string
     * }>
     */
    public function list(): array
    {
        try {
            $result = [];

            foreach ($this->repository->findAllPublicItems() as $item) {
                if (!$item instanceof GalleryItem || !$item->isPubliclyVisible()) {
                    continue;
                }

                $result[] = $this->projection($item);
            }

            return $result;
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw GalleryException::failure($throwable);
        }
    }

    /** @return list<array<string, mixed>> */
    public function homepage(int $limit = 6): array
    {
        try {
            return array_map(
                $this->projection(...),
                $this->repository->findHomepageGalleryItems($limit),
            );
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw GalleryException::failure($throwable);
        }
    }

    public function image(int $id): RetrievedGalleryImage
    {
        if ($id <= 0) {
            throw GalleryException::itemNotFound();
        }

        try {
            $item = $this->repository->findPublicItemById($id);

            if (!$item instanceof GalleryItem || !$item->isPubliclyVisible()) {
                throw GalleryException::itemNotFound();
            }

            $image = $this->images->read($item->filePath());

            if (!$image instanceof RetrievedGalleryImage) {
                throw GalleryException::itemNotFound();
            }

            return $image;
        } catch (GalleryException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw GalleryException::failure($throwable);
        }
    }

    /**
     * @return array{
     *     gallery_id: int,
     *     title: string,
     *     caption: ?string,
     *     alt_text: string,
     *     image_url: string
     * }
     */
    private function projection(GalleryItem $item): array
    {
        return [
            'gallery_id' => $item->id(),
            'title' => $item->title(),
            'caption' => $item->caption(),
            'alt_text' => $item->altText(),
            'image_url' => '/gallery?image=' . (string) $item->id(),
        ];
    }
}
