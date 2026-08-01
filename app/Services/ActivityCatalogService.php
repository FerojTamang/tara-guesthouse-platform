<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Repositories\ActivityCatalogRepository;
use Throwable;

/**
 * Provides the shared read foundation for Administrator and public activity flows.
 */
final readonly class ActivityCatalogService
{
    public function __construct(private ActivityCatalogRepository $repository)
    {
    }

    /** @return list<Activity> */
    public function listAll(): array
    {
        return $this->execute(fn (): array => $this->repository->findAll());
    }

    /** @return list<Activity> */
    public function listPublic(): array
    {
        return $this->execute(function (): array {
            $activities = [];

            foreach ($this->repository->findAllPublic() as $activity) {
                if ($activity instanceof Activity && $activity->isPubliclyVisible()) {
                    $activities[] = $activity;
                }
            }

            return $activities;
        });
    }

    public function get(int $id): Activity
    {
        return $this->execute(function () use ($id): Activity {
            $activity = $this->repository->findById($id);

            if (!$activity instanceof Activity) {
                throw ActivityException::notFound();
            }

            return $activity;
        });
    }

    public function getBySlug(string $slug): Activity
    {
        return $this->execute(function () use ($slug): Activity {
            $activity = $this->repository->findBySlug($slug);

            if (!$activity instanceof Activity) {
                throw ActivityException::notFound();
            }

            return $activity;
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
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw ActivityException::failure($throwable);
        }
    }
}
