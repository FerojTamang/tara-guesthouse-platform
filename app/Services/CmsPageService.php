<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use App\Models\CmsPage;
use App\Repositories\PageRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Owns structured-page validation and publication transitions. */
final readonly class CmsPageService
{
    private const TRANSITIONS = [
        CmsPage::DRAFT => [CmsPage::PUBLISHED, CmsPage::ARCHIVED],
        CmsPage::PUBLISHED => [CmsPage::DRAFT, CmsPage::ARCHIVED],
        CmsPage::ARCHIVED => [CmsPage::DRAFT],
    ];

    public function __construct(private PageRepository $repository)
    {
    }

    /** @return list<CmsPage> */
    public function listAll(): array
    {
        return $this->execute(fn (): array => $this->repository->findAll());
    }

    public function get(int $id): CmsPage
    {
        return $this->execute(function () use ($id): CmsPage {
            $page = $this->repository->findById($id);
            if (!$page instanceof CmsPage) {
                throw CmsException::pageNotFound();
            }
            return $page;
        });
    }

    public function getPublishedBySlug(string $slug): CmsPage
    {
        return $this->execute(function () use ($slug): CmsPage {
            $page = $this->repository->findPublishedBySlug($slug);
            if (!$page instanceof CmsPage || !$page->isPublished()) {
                throw CmsException::pageNotFound();
            }
            return $page;
        });
    }

    public function createDraft(
        string $title,
        string $slug,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        int $actorId,
    ): CmsPage {
        return $this->execute(function () use ($title, $slug, $content, $metaTitle, $metaDescription, $actorId): CmsPage {
            [$title, $slug, $content, $metaTitle, $metaDescription] = $this->validateContent(
                $title,
                $slug,
                $content,
                $metaTitle,
                $metaDescription,
                $actorId,
            );

            return $this->repository->createDraft(
                $title,
                $slug,
                $content,
                $metaTitle,
                $metaDescription,
                $actorId,
            );
        });
    }

    public function updateContent(
        int $id,
        string $title,
        string $slug,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        int $actorId,
    ): CmsPage {
        return $this->execute(function () use ($id, $title, $slug, $content, $metaTitle, $metaDescription, $actorId): CmsPage {
            if (!$this->repository->findById($id) instanceof CmsPage) {
                throw CmsException::pageNotFound();
            }

            [$title, $slug, $content, $metaTitle, $metaDescription] = $this->validateContent(
                $title,
                $slug,
                $content,
                $metaTitle,
                $metaDescription,
                $actorId,
            );

            $page = $this->repository->updateContent(
                $id,
                $title,
                $slug,
                $content,
                $metaTitle,
                $metaDescription,
                $actorId,
            );

            if (!$page instanceof CmsPage) {
                throw CmsException::pageNotFound();
            }

            return $page;
        });
    }

    public function transitionStatus(int $id, string $newStatus, int $actorId): CmsPage
    {
        return $this->execute(function () use ($id, $newStatus, $actorId): CmsPage {
            if ($actorId <= 0 || !in_array($newStatus, CmsPage::STATUSES, true)) {
                throw CmsException::invalidInput('The page status transition is invalid.');
            }

            $current = $this->repository->findById($id);
            if (!$current instanceof CmsPage) {
                throw CmsException::pageNotFound();
            }

            if (!in_array($newStatus, self::TRANSITIONS[$current->status()] ?? [], true)) {
                throw CmsException::invalidInput('The page status transition is not allowed.');
            }

            $publishedAt = match ($newStatus) {
                CmsPage::PUBLISHED => new DateTimeImmutable('now', new DateTimeZone('UTC')),
                CmsPage::DRAFT => null,
                CmsPage::ARCHIVED => $current->publishedAt(),
                default => null,
            };

            $page = $this->repository->transitionStatus(
                $id,
                $current->status(),
                $newStatus,
                $publishedAt,
                $actorId,
            );

            if (!$page instanceof CmsPage) {
                if ($this->repository->findById($id) instanceof CmsPage) {
                    throw CmsException::transitionConflict();
                }
                throw CmsException::pageNotFound();
            }

            return $page;
        });
    }

    /** @return array{string, string, string, ?string, ?string} */
    private function validateContent(
        string $title,
        string $slug,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        int $actorId,
    ): array {
        if ($actorId <= 0) {
            throw CmsException::invalidInput('A valid Administrator actor is required.');
        }

        foreach ([$title, $slug, $content, $metaTitle, $metaDescription] as $value) {
            if ($value !== null && (!mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0"))) {
                throw CmsException::invalidInput('The page content is invalid.');
            }
        }

        if (
            $title === '' || $title !== trim($title) || mb_strlen($title, 'UTF-8') > 200
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
            || mb_strlen($slug, 'UTF-8') > 200
            || $content === '' || $content !== trim($content)
            || ($metaTitle !== null && ($metaTitle === '' || $metaTitle !== trim($metaTitle) || mb_strlen($metaTitle, 'UTF-8') > 255))
            || ($metaDescription !== null && ($metaDescription === '' || $metaDescription !== trim($metaDescription) || mb_strlen($metaDescription, 'UTF-8') > 500))
        ) {
            throw CmsException::invalidInput('The page content is invalid.');
        }

        return [$title, $slug, $content, $metaTitle, $metaDescription];
    }

    /** @template T @param callable(): T $operation @return T */
    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
