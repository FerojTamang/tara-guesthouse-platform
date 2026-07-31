<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\CmsException;
use App\Exceptions\DatabaseException;
use App\Models\CmsPage;
use App\Repositories\PdoPageRepository;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CmsFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PageRepositoryTest extends DatabaseTestCase
{
    private PdoPageRepository $repository;
    private int $actorId;
    private int $otherActorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->actorId = CmsFixture::insertAdministrator($this->connection, 'pages-one');
        $this->otherActorId = CmsFixture::insertAdministrator($this->connection, 'pages-two');
        $this->repository = new PdoPageRepository($this->connection);
    }

    public function testItCreatesUpdatesAndFindsDraftPagesWithActorAttribution(): void
    {
        $page = $this->repository->createDraft(
            'About',
            'about',
            'Initial body.',
            null,
            null,
            $this->actorId,
        );

        self::assertSame(CmsPage::DRAFT, $page->status());
        self::assertNull($page->publishedAt());
        self::assertSame($this->actorId, $page->createdBy());
        self::assertNull($this->repository->findPublishedBySlug('about'));

        $page = $this->repository->updateContent(
            $page->id(),
            'About Us',
            'about-us',
            'Updated body.',
            'About Tara Guest House',
            'About the guest house.',
            $this->otherActorId,
        );

        self::assertInstanceOf(CmsPage::class, $page);
        self::assertSame('about-us', $page->slug());
        self::assertSame($this->actorId, $page->createdBy());
        self::assertSame($this->otherActorId, $page->updatedBy());
        self::assertSame($page->id(), $this->repository->findBySlug('about-us')?->id());
    }

    public function testStatusTransitionIsConditionalAndPublicLookupRequiresPublished(): void
    {
        $page = $this->repository->createDraft('Policy', 'policy', 'Policy body.', null, null, $this->actorId);
        $publishedAt = new DateTimeImmutable('2026-07-31 01:00:00', new DateTimeZone('UTC'));

        $published = $this->repository->transitionStatus(
            $page->id(),
            CmsPage::DRAFT,
            CmsPage::PUBLISHED,
            $publishedAt,
            $this->otherActorId,
        );

        self::assertInstanceOf(CmsPage::class, $published);
        self::assertTrue($published->isPublished());
        self::assertEquals($publishedAt, $published->publishedAt());
        self::assertSame($published->id(), $this->repository->findPublishedBySlug('policy')?->id());
        self::assertNull($this->repository->transitionStatus(
            $page->id(),
            CmsPage::DRAFT,
            CmsPage::ARCHIVED,
            $publishedAt,
            $this->actorId,
        ));

        $archived = $this->repository->transitionStatus(
            $page->id(),
            CmsPage::PUBLISHED,
            CmsPage::ARCHIVED,
            $publishedAt,
            $this->actorId,
        );
        self::assertSame(CmsPage::ARCHIVED, $archived?->status());
        self::assertNull($this->repository->findPublishedBySlug('policy'));
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM pages')->fetchColumn());
    }

    public function testDuplicateSlugsReturnSafeConflict(): void
    {
        $this->repository->createDraft('About', 'about', 'Body.', null, null, $this->actorId);

        try {
            $this->repository->createDraft('Another', 'about', 'Other body.', null, null, $this->actorId);
            self::fail('Expected a duplicate page slug conflict.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertStringContainsString('slug', strtolower($exception->getMessage()));
            self::assertStringNotContainsString('uq_pages_slug', $exception->getMessage());
        }
    }

    public function testRepositoryFailuresAreSanitized(): void
    {
        $this->connection->exec('DROP TABLE pages');

        try {
            $this->repository->findAll();
            self::fail('Expected a page repository failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The page listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }
}
