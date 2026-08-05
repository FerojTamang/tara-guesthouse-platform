<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Models\NavigationItem;
use App\Repositories\PdoNavigationRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CmsFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class NavigationRepositoryTest extends DatabaseTestCase
{
    private PdoNavigationRepository $navigation;
    private int $administratorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->administratorId = CmsFixture::insertAdministrator($this->connection, 'navigation-repo');
        $this->navigation = new PdoNavigationRepository($this->connection);
    }

    public function testSeededRoutesAndAdministratorMutationsPersistOrderedTargets(): void
    {
        self::assertCount(7, $this->navigation->findAll());

        $pageId = CmsFixture::insertPage($this->connection, $this->administratorId, [
            'title' => 'About Tara Guest House',
            'slug' => 'about-tara',
            'status' => 'published',
            'published_at' => '2026-08-05 00:00:00',
        ]);
        $group = $this->navigation->create(
            NavigationItem::MAIN,
            'Explore',
            NavigationItem::GROUP,
            null,
            null,
            null,
            80,
            $this->administratorId,
        );
        $page = $this->navigation->create(
            NavigationItem::MAIN,
            'About',
            NavigationItem::PAGE,
            null,
            $pageId,
            $group->id(),
            10,
            $this->administratorId,
        );

        self::assertSame($group->id(), $page->parentId());
        self::assertSame($pageId, $page->pageId());
        self::assertTrue($page->isActive());

        $news = $this->navigation->create(
            NavigationItem::MAIN,
            'News',
            NavigationItem::ROUTE,
            'news',
            null,
            null,
            90,
            $this->administratorId,
        );
        self::assertSame('news', $news->routeKey());

        $updated = $this->navigation->update(
            $page->id(),
            NavigationItem::MAIN,
            'About us',
            NavigationItem::PAGE,
            null,
            $pageId,
            $group->id(),
            20,
            $this->administratorId,
        );
        self::assertNotNull($updated);
        self::assertSame('About us', $updated->label());
        self::assertSame(20, $updated->sortOrder());

        $disabled = $this->navigation->setActive($page->id(), false, $this->administratorId);
        self::assertNotNull($disabled);
        self::assertFalse($disabled->isActive());
        self::assertNotContains($page->id(), array_map(
            static fn (NavigationItem $item): int => $item->id(),
            $this->navigation->findAllActive(),
        ));
    }

    public function testInvalidIdentifiersReturnNoMutation(): void
    {
        self::assertNull($this->navigation->findById(0));
        self::assertNull($this->navigation->setActive(0, true, $this->administratorId));
        self::assertFalse($this->navigation->setSortOrder(0, 10, $this->administratorId));
    }
}
