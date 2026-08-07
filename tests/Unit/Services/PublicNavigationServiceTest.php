<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\CmsPage;
use App\Models\NavigationItem;
use App\Models\PageContentProfile;
use App\Repositories\NavigationRepository;
use App\Repositories\PageContentProfileRepository;
use App\Repositories\PageRepository;
use App\Services\NavigationRouteCatalog;
use App\Services\PublicNavigationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicNavigationServiceTest extends TestCase
{
    public function testOnlyActiveResolvableAndPublishedItemsReachPublicLayout(): void
    {
        $navigation = $this->createStub(NavigationRepository::class);
        $navigation->method('findAllActive')->willReturn([
            self::item(1, 'main', 'Explore', 'group', null, null, null, 10),
            self::item(2, 'main', 'About', 'page', null, 20, 1, 10),
            self::item(3, 'main', 'Draft page', 'page', null, 21, null, 20),
            self::item(4, 'main', 'Rooms', 'route', 'rooms', null, null, 30),
            self::item(5, 'footer', 'Contact us', 'route', 'contact', null, null, 10),
            self::item(6, 'main', 'Reserved CMS address', 'page', null, 22, null, 40),
        ]);
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findAllPublished')->willReturn([
            self::page(20, 'about', CmsPage::PUBLISHED),
            self::page(22, 'rooms', CmsPage::PUBLISHED),
        ]);

        $layout = (new PublicNavigationService(
            $navigation,
            $pages,
            new NavigationRouteCatalog(),
        ))->layout();

        self::assertSame([
            [
                'label' => 'Explore',
                'href' => null,
                'children' => [['label' => 'About', 'href' => '/about']],
            ],
            ['label' => 'Rooms', 'href' => '/rooms', 'children' => []],
        ], $layout['main']);
        self::assertSame([
            ['label' => 'Contact us', 'href' => '/contact'],
        ], $layout['footer']);
    }

    public function testCmsNavigationUsesCanonicalNewsAndNoticePaths(): void
    {
        $navigation = $this->createStub(NavigationRepository::class);
        $navigation->method('findAllActive')->willReturn([
            self::item(1, 'main', 'Garden update', 'page', null, 20, null, 10),
            self::item(2, 'main', 'Arrival notice', 'page', null, 21, null, 20),
        ]);
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findAllPublished')->willReturn([
            self::page(20, 'garden-update', CmsPage::PUBLISHED),
            self::page(21, 'arrival-notice', CmsPage::PUBLISHED),
            self::page(23, 'unreferenced-page', CmsPage::PUBLISHED),
        ]);
        $profiles = $this->createMock(PageContentProfileRepository::class);
        $profiles->expects(self::once())
            ->method('findByPageIds')
            ->with([20, 21])
            ->willReturn([
                20 => self::profile(20, 'news'),
                21 => self::profile(21, 'notice'),
            ]);
        $profiles->expects(self::never())->method('findByPageId');

        $layout = (new PublicNavigationService(
            $navigation,
            $pages,
            new NavigationRouteCatalog(),
            $profiles,
        ))->layout();

        self::assertSame('/news/garden-update', $layout['main'][0]['href']);
        self::assertSame('/notices/arrival-notice', $layout['main'][1]['href']);
    }

    public function testIntentionallyDisabledNavigationDoesNotRestoreFallbackLinks(): void
    {
        $navigation = $this->createStub(NavigationRepository::class);
        $navigation->method('findAllActive')->willReturn([]);
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findAllPublished')->willReturn([]);

        $layout = (new PublicNavigationService(
            $navigation,
            $pages,
            new NavigationRouteCatalog(),
        ))->layout();

        self::assertSame([], $layout['main']);
        self::assertSame([], $layout['footer']);
    }

    public function testRepositoryFailureFallsBackToApprovedBuiltInNavigation(): void
    {
        $navigation = $this->createStub(NavigationRepository::class);
        $navigation->method('findAllActive')->willThrowException(new RuntimeException('database detail'));
        $pages = $this->createStub(PageRepository::class);

        $layout = (new PublicNavigationService(
            $navigation,
            $pages,
            new NavigationRouteCatalog(),
        ))->layout();

        self::assertSame(['Home', 'Rooms', 'Activities', 'Gallery', 'News', 'Notices', 'Reviews', 'Availability', 'Contact'], array_column($layout['main'], 'label'));
        self::assertSame([], $layout['footer']);
    }

    private static function item(
        int $id,
        string $location,
        string $label,
        string $type,
        ?string $routeKey,
        ?int $pageId,
        ?int $parentId,
        int $sortOrder,
    ): NavigationItem {
        $time = new DateTimeImmutable('2026-08-05 00:00:00 UTC');

        return new NavigationItem(
            $id,
            $location,
            $label,
            $type,
            $routeKey,
            $pageId,
            $parentId,
            $sortOrder,
            true,
            null,
            null,
            $time,
            $time,
        );
    }

    private static function profile(int $pageId, string $contentType): PageContentProfile
    {
        $time = new DateTimeImmutable('2026-08-05 00:00:00 UTC');

        return new PageContentProfile(
            $pageId,
            $contentType,
            null,
            $contentType === 'notice' ? 'information' : null,
            $time,
            $contentType === 'notice' ? new DateTimeImmutable('2026-08-10 00:00:00 UTC') : null,
            false,
            false,
            null,
            null,
            null,
            null,
            false,
            1,
            $time,
            $time,
        );
    }

    private static function page(int $id, string $slug, string $status): CmsPage
    {
        $time = new DateTimeImmutable('2026-08-05 00:00:00 UTC');

        return new CmsPage(
            $id,
            ucfirst(str_replace('-', ' ', $slug)),
            $slug,
            'Page content.',
            null,
            null,
            $status,
            $status === CmsPage::PUBLISHED ? $time : null,
            1,
            1,
            $time,
            $time,
        );
    }
}
