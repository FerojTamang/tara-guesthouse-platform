<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\PublishedContentItem;
use App\Repositories\PdoPublicContentCatalogRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CmsFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PublicContentCatalogRepositoryTest extends DatabaseTestCase
{
    private PdoPublicContentCatalogRepository $repository;
    private int $actorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->actorId = CmsFixture::insertAdministrator($this->connection, 'public-content');
        $this->repository = new PdoPublicContentCatalogRepository($this->connection);
    }

    public function testItReturnsOnlyCurrentlyPublishedTypedContentInStableEditorialOrder(): void
    {
        $regular = $this->insertPageWithProfile('Regular update', 'regular-update', 'news', false, false, '2026-08-04 00:00:00');
        $featured = $this->insertPageWithProfile('Featured update', 'featured-update', 'news', true, false, '2026-08-03 00:00:00');
        $pinned = $this->insertPageWithProfile('Pinned update', 'pinned-update', 'news', false, true, '2026-08-01 00:00:00');
        $this->insertPageWithProfile('Future update', 'future-update', 'news', true, true, '2099-01-01 00:00:00');
        $this->insertPageWithProfile(
            'Expired update',
            'expired-update',
            'news',
            true,
            true,
            '2026-08-01 00:00:00',
            '2026-08-02 00:00:00',
        );
        $this->insertPageWithProfile('Guest notice', 'guest-notice', 'notice', false, true, '2026-08-01 00:00:00', '2099-01-01 00:00:00');

        $items = $this->repository->findAllPublishedByType('news');

        self::assertContainsOnlyInstancesOf(PublishedContentItem::class, $items);
        self::assertSame(
            [$pinned, $featured, $regular],
            array_map(static fn (PublishedContentItem $item): int => $item->page()->id(), $items),
        );
        self::assertSame(['pinned-update', 'featured-update', 'regular-update'], array_map(
            static fn (PublishedContentItem $item): string => $item->page()->slug(),
            $items,
        ));
        self::assertSame([], $this->repository->findAllPublishedByType('page'));
    }

    public function testRepositoryFailuresAreSanitized(): void
    {
        $this->dropTables(['page_content_profiles']);

        try {
            $this->repository->findAllPublishedByType('news');
            self::fail('Expected a public content repository failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The public content listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function insertPageWithProfile(
        string $title,
        string $slug,
        string $contentType,
        bool $featured,
        bool $pinned,
        string $publishedAt,
        ?string $expiresAt = null,
    ): int {
        $pageId = CmsFixture::insertPage($this->connection, $this->actorId, [
            'title' => $title,
            'slug' => $slug,
            'content' => $title . ' body.',
            'status' => 'published',
            'published_at' => $publishedAt,
        ]);
        $statement = $this->connection->prepare(
            'INSERT INTO page_content_profiles '
            . '(page_id, content_type, summary, notice_style, publish_at, expires_at, is_featured, is_pinned, seo_noindex, updated_by) '
            . 'VALUES (:page_id, :content_type, :summary, :notice_style, :publish_at, :expires_at, :is_featured, :is_pinned, 0, :updated_by)',
        );
        $statement->execute([
            'page_id' => $pageId,
            'content_type' => $contentType,
            'summary' => $title . ' summary.',
            'notice_style' => $contentType === 'notice' ? 'information' : null,
            'publish_at' => $publishedAt,
            'expires_at' => $expiresAt,
            'is_featured' => $featured ? 1 : 0,
            'is_pinned' => $pinned ? 1 : 0,
            'updated_by' => $this->actorId,
        ]);

        return $pageId;
    }
}
