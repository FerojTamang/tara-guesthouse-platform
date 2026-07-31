<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\CmsPage;
use App\Repositories\PageRepository;
use App\Services\CmsPageService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CmsPageServiceTest extends TestCase
{
    public function testItCreatesDraftUpdatesContentAndPublishesWithActorAttribution(): void
    {
        $repository = new InMemoryPageRepository();
        $service = new CmsPageService($repository);

        $page = $service->createDraft('About', 'about', 'Original content.', null, null, 3);
        self::assertSame(CmsPage::DRAFT, $page->status());
        self::assertNull($page->publishedAt());
        self::assertSame(3, $page->createdBy());

        $page = $service->updateContent($page->id(), 'About Us', 'about-us', 'Updated content.', 'About Us', null, 4);
        self::assertSame(CmsPage::DRAFT, $page->status());
        self::assertSame(4, $page->updatedBy());

        $page = $service->transitionStatus($page->id(), CmsPage::PUBLISHED, 5);
        self::assertTrue($page->isPublished());
        self::assertInstanceOf(DateTimeImmutable::class, $page->publishedAt());
        self::assertSame(0, $page->publishedAt()?->getOffset());
        self::assertSame(5, $page->updatedBy());
        self::assertSame($page->id(), $service->getPublishedBySlug('about-us')->id());
    }

    public function testTransitionRulesRetainArchivedPagesAndRequireDraftBeforeRepublish(): void
    {
        $repository = new InMemoryPageRepository();
        $service = new CmsPageService($repository);
        $page = $service->createDraft('Policy', 'policy', 'Policy content.', null, null, 1);
        $page = $service->transitionStatus($page->id(), CmsPage::PUBLISHED, 1);
        $publishedAt = $page->publishedAt();
        $page = $service->transitionStatus($page->id(), CmsPage::ARCHIVED, 2);

        self::assertSame(CmsPage::ARCHIVED, $page->status());
        self::assertSame($publishedAt, $page->publishedAt());
        self::assertNull($repository->findPublishedBySlug('policy'));

        try {
            $service->transitionStatus($page->id(), CmsPage::PUBLISHED, 2);
            self::fail('Archived pages must return to draft before publication.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isValidationFailure());
        }

        $draft = $service->transitionStatus($page->id(), CmsPage::DRAFT, 2);
        self::assertNull($draft->publishedAt());
    }

    public function testInvalidContentActorsAndSameStatusTransitionsAreRejected(): void
    {
        $service = new CmsPageService(new InMemoryPageRepository());
        $page = $service->createDraft('About', 'about', 'Body.', null, null, 1);

        foreach ([
            fn () => $service->createDraft('', 'about-two', 'Body.', null, null, 1),
            fn () => $service->createDraft('About', 'Bad Slug', 'Body.', null, null, 1),
            fn () => $service->createDraft('About', 'about-two', 'Body.', null, null, 0),
            fn () => $service->transitionStatus($page->id(), CmsPage::DRAFT, 1),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected CMS page validation to fail.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }
}

final class InMemoryPageRepository implements PageRepository
{
    /** @var array<int, CmsPage> */
    private array $pages = [];
    private int $nextId = 1;

    public function findById(int $id): ?CmsPage { return $this->pages[$id] ?? null; }
    public function findBySlug(string $slug): ?CmsPage { foreach ($this->pages as $page) { if ($page->slug() === $slug) return $page; } return null; }
    public function findPublishedBySlug(string $slug): ?CmsPage { $page = $this->findBySlug($slug); return $page?->isPublished() ? $page : null; }
    public function findAll(): array { return array_values($this->pages); }
    public function findAllPublished(): array { return array_values(array_filter($this->pages, static fn (CmsPage $page): bool => $page->isPublished())); }

    public function createDraft(string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): CmsPage
    {
        $time = $this->time();
        $page = new CmsPage($this->nextId++, $title, $slug, $content, $metaTitle, $metaDescription, CmsPage::DRAFT, null, $actorId, $actorId, $time, $time);
        return $this->pages[$page->id()] = $page;
    }

    public function updateContent(int $id, string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): ?CmsPage
    {
        $current = $this->pages[$id] ?? null;
        if (!$current instanceof CmsPage) return null;
        return $this->pages[$id] = new CmsPage($id, $title, $slug, $content, $metaTitle, $metaDescription, $current->status(), $current->publishedAt(), $current->createdBy(), $actorId, $current->createdAt(), $this->time());
    }

    public function transitionStatus(int $id, string $expectedStatus, string $newStatus, ?DateTimeImmutable $publishedAt, int $actorId): ?CmsPage
    {
        $current = $this->pages[$id] ?? null;
        if (!$current instanceof CmsPage || $current->status() !== $expectedStatus) return null;
        return $this->pages[$id] = new CmsPage($id, $current->title(), $current->slug(), $current->content(), $current->metaTitle(), $current->metaDescription(), $newStatus, $publishedAt, $current->createdBy(), $actorId, $current->createdAt(), $this->time());
    }

    private function time(): DateTimeImmutable { return new DateTimeImmutable('2026-07-31 00:00:00', new DateTimeZone('UTC')); }
}
