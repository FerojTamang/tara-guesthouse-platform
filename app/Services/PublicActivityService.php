<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Repositories\ActivityCatalogRepository;
use App\Storage\GalleryImageReader;
use App\Storage\RetrievedGalleryImage;
use Throwable;

/**
 * Exposes the approved public activity catalogue projection.
 */
final readonly class PublicActivityService
{
    public function __construct(
        private ActivityCatalogRepository $repository,
        private ?GalleryImageReader $images = null,
    )
    {
    }

    /**
     * @return list<array{
     *     activity_id: int,
     *     title: string,
     *     slug: string,
     *     description: string,
     *     price: string
     * }>
     */
    public function list(): array
    {
        try {
            $result = [];

            foreach ($this->repository->findAllPublic() as $activity) {
                if (!$activity instanceof Activity || !$activity->isPubliclyVisible()) {
                    continue;
                }

                $result[] = $this->projection($activity);
            }

            return $result;
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ActivityException::failure($throwable);
        }
    }

    /** @return list<array<string, mixed>> */
    public function homepage(int $limit = 4): array
    {
        try {
            return array_map(
                $this->projection(...),
                $this->repository->findHomepageActivities($limit),
            );
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ActivityException::failure($throwable);
        }
    }

    public function image(int $id): RetrievedGalleryImage
    {
        if ($id <= 0 || !$this->images instanceof GalleryImageReader) {
            throw ActivityException::notFound();
        }

        try {
            $activity = $this->repository->findPublicById($id);

            if (!$activity instanceof Activity || $activity->featuredImagePath() === null) {
                throw ActivityException::notFound();
            }

            $image = $this->images->read($activity->featuredImagePath());

            if (!$image instanceof RetrievedGalleryImage) {
                throw ActivityException::notFound();
            }

            return $image;
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ActivityException::failure($throwable);
        }
    }

    /** @return array<string, mixed> */
    private function projection(Activity $activity): array
    {
        return [
            'activity_id' => $activity->id(),
            'title' => $activity->title(),
            'slug' => $activity->slug(),
            'summary' => $activity->summary(),
            'description' => $activity->description(),
            'image_url' => $activity->featuredImagePath() === null
                ? null
                : '/activities?image=' . (string) $activity->id(),
            'price' => $activity->price(),
            'price_unit' => $activity->priceUnit(),
            'duration' => $activity->durationLabel(),
            'location' => $activity->location(),
        ];
    }
}
