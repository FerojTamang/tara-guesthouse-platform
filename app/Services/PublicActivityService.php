<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Repositories\ActivityCatalogRepository;
use Throwable;

/**
 * Exposes the approved public activity catalogue projection.
 */
final readonly class PublicActivityService
{
    public function __construct(private ActivityCatalogRepository $repository)
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

                $result[] = [
                    'activity_id' => $activity->id(),
                    'title' => $activity->title(),
                    'slug' => $activity->slug(),
                    'description' => $activity->description(),
                    'price' => $activity->price(),
                ];
            }

            return $result;
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ActivityException::failure($throwable);
        }
    }
}
