<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicCmsController;
use App\Core\Request;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Models\Activity;
use App\Models\CmsPage;
use App\Models\GalleryItem;
use App\Models\PageContentProfile;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Presentation\RoomImageUrl;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Repositories\PageContentProfileRepository;
use App\Repositories\PageRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Repositories\SettingRepository;
use App\Services\PublicActivityService;
use App\Services\PublicCmsService;
use App\Services\PublicCmsServiceProvider;
use App\Services\PublicGalleryService;
use App\Services\PublicRoomCatalogService;
use App\Services\SettingDefinitionRegistry;
use App\Storage\GalleryImageReader;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicCmsExperienceTest extends TestCase
{
    private PublicCmsPageRepositoryFake $pages;
    private PublicCmsSettingRepositoryFake $settings;
    private PublicCmsProfileRepositoryFake $profiles;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pages = new PublicCmsPageRepositoryFake();
        $this->settings = new PublicCmsSettingRepositoryFake();
        $this->profiles = new PublicCmsProfileRepositoryFake();
        $this->pages->pages = [
            1 => self::page(1, 'about', CmsPage::PUBLISHED, "Welcome <script>alert(1)</script>.\nSecond line."),
            2 => self::page(2, 'private-draft', CmsPage::DRAFT, 'Draft content.'),
            3 => self::page(3, 'rooms', CmsPage::PUBLISHED, 'Reserved route content.'),
            4 => self::page(4, 'garden-news', CmsPage::PUBLISHED, json_encode([
                'format' => 'tara-blocks-v1',
                'version' => 1,
                'type' => 'news',
                'summary' => 'A fresh update from the Tara Guest House garden.',
                'blocks' => [[
                    'type' => 'image',
                    'media_id' => 12,
                    'media_title' => 'Garden view',
                    'url' => '/uploads/content/' . str_repeat('a', 64) . '.webp',
                    'width' => 1600,
                    'height' => 900,
                    'alt' => 'Garden terrace <script>alert(1)</script>',
                    'caption' => 'Morning in the garden.',
                ]],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            5 => self::page(5, 'arrival-notice', CmsPage::PUBLISHED, 'Please use the east entrance after 8 PM.'),
        ];
        $this->profiles->profiles = [
            1 => self::profile(1, 'page', 'About Tara Guest House.'),
            3 => self::profile(3, 'page', null),
            4 => self::profile(4, 'news', 'A fresh update from the Tara Guest House garden.', featured: true),
            5 => self::profile(5, 'notice', 'Please use the east entrance after 8 PM.', 'important', pinned: true),
        ];
        $this->settings->settings = [
            'contact.email' => self::setting(1, 'contact.email', 'hello@example.test', 'email', true),
            'homepage.enabled' => self::setting(2, 'homepage.enabled', 'true', 'boolean', true),
            'private.note' => self::setting(3, 'private.note', 'secret', 'string', false),
        ];
        $this->router = $this->router($this->controller());
    }

    public function testHomepageComposesEligibleModulesNavigationFooterAndSeoSafely(): void
    {
        $response = $this->router->dispatch(Request::create('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Tara Guest House</title>', $response->body());
        self::assertStringContainsString('<link rel="canonical" href="https://example.test/">', $response->body());
        self::assertStringContainsString('property="og:title" content="Tara Guest House"', $response->body());
        self::assertStringContainsString('href="/about"', $response->body());
        self::assertStringNotContainsString('href="/private-draft"', $response->body());
        self::assertGreaterThanOrEqual(2, substr_count($response->body(), 'href="/rooms"'));
        self::assertStringNotContainsString('Reserved route content.', $response->body());
        self::assertStringContainsString('Village Walk', $response->body());
        self::assertStringContainsString('Courtyard', $response->body());
        self::assertStringContainsString('Verified Stay Guest', $response->body());
        self::assertStringContainsString('&lt;script&gt;Excellent&lt;/script&gt;', $response->body());
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $response->body());
        self::assertStringNotContainsString('<script>Excellent</script>', $response->body());
        self::assertStringContainsString('mailto:hello@example.test', $response->body());
        self::assertStringNotContainsString('private.note', $response->body());
        self::assertStringNotContainsString('secret', $response->body());
        self::assertStringContainsString('Nature&rsquo;s Luxury.', $response->body());
        self::assertStringContainsString('class="tg-room-grid"', $response->body());
        self::assertStringContainsString('Garden Room', $response->body());
        self::assertSame(3, substr_count($response->body(), 'class="tg-card tg-room-card"'));
        self::assertStringNotContainsString('Fourth Room', $response->body());
        self::assertStringContainsString('<span>NPR</span> 2500.00', $response->body());
        self::assertStringContainsString('src="/uploads/rooms/', $response->body());
        self::assertStringNotContainsString('R-PRIVATE-01', $response->body());
        self::assertStringContainsString('src="/activities?image=1"', $response->body());
        self::assertStringContainsString('src="/gallery?image=1"', $response->body());
        self::assertStringContainsString('method="get" action="/availability"', $response->body());
        self::assertStringContainsString('name="check_in"', $response->body());
        self::assertStringContainsString('name="check_out"', $response->body());
        self::assertStringContainsString('name="adults"', $response->body());
        self::assertStringContainsString('name="children"', $response->body());
        self::assertStringContainsString('/assets/css/pages/home.css', $response->body());
        self::assertStringContainsString('/assets/js/pages/home.js', $response->body());
        self::assertStringContainsString('/assets/images/home/about-tara-guest-house.webp', $response->body());
        self::assertStringContainsString('aria-expanded="false"', $response->body());
        self::assertStringContainsString('News &amp; stories', $response->body());
        self::assertStringContainsString('Garden News', $response->body());
        self::assertStringContainsString('Arrival Notice', $response->body());
        self::assertStringContainsString('href="/news/garden-news"', $response->body());
        self::assertStringContainsString('href="/notices/arrival-notice"', $response->body());
    }

    public function testHomepageDesignAssetsAreLocalAndAvailable(): void
    {
        $public = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets';

        foreach ([
            'css/tokens.css',
            'css/base.css',
            'css/components.css',
            'css/public.css',
            'css/pages/home.css',
            'css/pages/publishing.css',
            'js/public.js',
            'js/pages/home.js',
            'images/home/home-hero-sauraha-jungle-lodge.png',
            'images/home/home-hero-sauraha-jungle-lodge.webp',
            'images/home/about-tara-guest-house.png',
            'images/home/about-tara-guest-house.webp',
            'images/home/home-booking-cta-jungle.png',
            'images/home/home-booking-cta-jungle.webp',
        ] as $asset) {
            self::assertFileExists($public . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset));
        }
    }

    public function testPublishedPageRendersEscapedContentAndPageSeo(): void
    {
        $response = $this->router->dispatch(Request::create('GET', '/about'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('<title>About SEO</title>', $response->body());
        self::assertStringContainsString('name="description" content="About description"', $response->body());
        self::assertStringContainsString('<link rel="canonical" href="https://example.test/about">', $response->body());
        self::assertStringContainsString('Welcome &lt;script&gt;alert(1)&lt;/script&gt;.<br', $response->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        self::assertStringContainsString('mailto:hello@example.test', $response->body());
        self::assertStringContainsString('/assets/css/pages/publishing.css', $response->body());
    }

    public function testStructuredPageKeepsSharedShellAndRendersManagedMediaSafely(): void
    {
        $response = $this->router->dispatch(Request::create('GET', '/news/garden-news'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('class="tg-public-header', $response->body());
        self::assertStringContainsString('class="tg-public-footer"', $response->body());
        self::assertStringContainsString('A fresh update from the Tara Guest House garden.', $response->body());
        self::assertStringContainsString(
            'src="/uploads/content/' . str_repeat('a', 64) . '.webp"',
            $response->body(),
        );
        self::assertStringContainsString('width="1600" height="900"', $response->body());
        self::assertStringContainsString(
            'alt="Garden terrace &lt;script&gt;alert(1)&lt;/script&gt;"',
            $response->body(),
        );
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        self::assertStringContainsString('/assets/css/public.css', $response->body());
        self::assertStringContainsString('/assets/css/pages/publishing.css', $response->body());
        self::assertStringContainsString('href="/news"', $response->body());
    }

    public function testNewsAndNoticeIndexesExposeOnlyTypedPublishedContentAndLegacyPathsRedirect(): void
    {
        $news = $this->router->dispatch(Request::create('GET', '/news'));
        $notices = $this->router->dispatch(Request::create('GET', '/notices'));
        $notice = $this->router->dispatch(Request::create('GET', '/notices/arrival-notice'));
        $wrongCollection = $this->router->dispatch(Request::create('GET', '/news/arrival-notice'));
        $legacyNews = $this->router->dispatch(Request::create('GET', '/garden-news'));
        $legacyNotice = $this->router->dispatch(Request::create('GET', '/arrival-notice'));

        self::assertSame(200, $news->statusCode());
        self::assertStringContainsString('News &amp; stories', $news->body());
        self::assertStringContainsString('Garden News', $news->body());
        self::assertStringNotContainsString('Arrival Notice', $news->body());
        self::assertSame(200, $notices->statusCode());
        self::assertStringContainsString('Current notices', $notices->body());
        self::assertStringContainsString('Arrival Notice', $notices->body());
        self::assertStringNotContainsString('Garden News', $notices->body());
        self::assertSame(200, $notice->statusCode());
        self::assertStringContainsString('Important notice', $notice->body());
        self::assertSame(404, $wrongCollection->statusCode());
        self::assertSame(301, $legacyNews->statusCode());
        self::assertSame('/news/garden-news', $legacyNews->headers()['Location']);
        self::assertSame(301, $legacyNotice->statusCode());
        self::assertSame('/notices/arrival-notice', $legacyNotice->headers()['Location']);
    }

    public function testPageAndPublicSettingsApisExposeOnlyApprovedFields(): void
    {
        $page = $this->router->dispatch(Request::create('GET', '/api/v1/pages/about'));
        $settings = $this->router->dispatch(Request::create('GET', '/api/v1/settings/public'));

        self::assertSame(200, $page->statusCode());
        self::assertSame([
            'success' => true,
            'data' => [
                'page_id' => 1,
                'title' => 'About',
                'slug' => 'about',
                'content' => "Welcome <script>alert(1)</script>.\nSecond line.",
                'meta_title' => 'About SEO',
                'meta_description' => 'About description',
                'published_at' => '2026-07-31T04:00:00+00:00',
            ],
        ], json_decode($page->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('status', $page->body());
        self::assertStringNotContainsString('created_by', $page->body());
        self::assertStringNotContainsString('updated_by', $page->body());

        self::assertSame(200, $settings->statusCode());
        self::assertSame([
            'success' => true,
            'data' => [
                ['setting_key' => 'contact.email', 'value' => 'hello@example.test', 'value_type' => 'email'],
                ['setting_key' => 'homepage.enabled', 'value' => true, 'value_type' => 'boolean'],
            ],
        ], json_decode($settings->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('is_public', $settings->body());
        self::assertStringNotContainsString('updated_by', $settings->body());
        self::assertStringNotContainsString('private.note', $settings->body());
    }

    public function testDraftUnknownMalformedAndUnsupportedRoutesFailSafely(): void
    {
        foreach (['/private-draft', '/unknown', '/Bad%20Slug'] as $uri) {
            $response = $this->router->dispatch(Request::create('GET', $uri));
            self::assertSame(404, $response->statusCode(), $uri);
            self::assertSame('Not Found', $response->body());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
        }

        self::assertSame(404, $this->router->dispatch(Request::create('GET', '/api/v1/pages/private-draft'))->statusCode());
        self::assertSame(404, $this->router->dispatch(Request::create('GET', '/api/v1/pages/unknown'))->statusCode());
        self::assertSame(404, $this->router->dispatch(Request::create('GET', '/api/v1/pages/rooms'))->statusCode());
        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/about'))->statusCode());
        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/api/v1/settings/public'))->statusCode());
    }

    public function testMissingSettingDefinitionsFailClosedWithoutDisablingPublishedPages(): void
    {
        $router = $this->router($this->controller(
            static fn (): never => throw \App\Exceptions\CmsException::failure(),
        ));

        $settings = $router->dispatch(Request::create('GET', '/api/v1/settings/public'));
        $page = $router->dispatch(Request::create('GET', '/about'));

        self::assertSame(200, $settings->statusCode());
        self::assertSame(['success' => true, 'data' => []], json_decode($settings->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(200, $page->statusCode());
        self::assertStringNotContainsString('mailto:', $page->body());
    }

    public function testRepositoryFailureProducesSanitizedNoStoreResponses(): void
    {
        $this->pages->failure = true;

        $page = $this->router->dispatch(Request::create('GET', '/about'));
        $api = $this->router->dispatch(Request::create('GET', '/api/v1/pages/about'));

        self::assertSame(500, $page->statusCode());
        self::assertSame('Internal Server Error', $page->body());
        self::assertSame('no-store', $page->headers()['Cache-Control']);
        self::assertSame(500, $api->statusCode());
        self::assertSame([
            'success' => false,
            'message' => 'Internal Server Error.',
        ], json_decode($api->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('SQLSTATE', $api->body());
    }

    private function controller(?callable $definitions = null, ?callable $reviews = null): PublicCmsController
    {
        $definitions ??= fn (): SettingDefinitionRegistry => new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
            new SettingDefinition('homepage.enabled', 'boolean', true, 5),
            new SettingDefinition('private.note', 'string', false, 100),
        ]);
        $reviews ??= static fn (): array => [[
            'guest_name' => 'Verified Stay Guest',
            'rating' => 5,
            'title' => '<script>Excellent</script>',
            'comment' => '<img src=x onerror=alert(1)> Helpful team.',
            'created_at' => '2026-08-01T00:30:00+00:00',
        ]];
        $roomRepository = $this->createStub(RoomCatalogRepository::class);
        $roomRepository->method('findAllPublic')->willReturn([
            self::room(),
            self::room(2, 'River Room', 'river-room', 'R-PRIVATE-02'),
            self::room(3, 'Courtyard Room', 'courtyard-room', 'R-PRIVATE-03'),
            self::room(4, 'Fourth Room', 'fourth-room', 'R-PRIVATE-04'),
        ]);
        $roomImages = $this->createStub(RoomImageCatalogRepository::class);
        $roomImages->method('findPrimaryByRoomId')->willReturn(self::roomImage());
        $activityRepository = $this->createMock(ActivityCatalogRepository::class);
        $activityRepository->method('findHomepageActivities')->with(4)->willReturn([self::activity()]);
        $galleryRepository = $this->createMock(GalleryCatalogRepository::class);
        $galleryRepository->method('findHomepageGalleryItems')->with(6)->willReturn([self::gallery()]);
        $service = new PublicCmsService(
            $this->pages,
            $this->settings,
            $definitions,
            new PublicRoomCatalogService(
                $roomRepository,
                $roomImages,
                new RoomImageUrl(),
            ),
            new PublicActivityService($activityRepository),
            new PublicGalleryService(
                $galleryRepository,
                $this->createStub(GalleryImageReader::class),
            ),
            'Tara Guest House',
            'https://example.test',
            $reviews,
            $this->profiles,
            clock: static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-05 00:00:00 UTC'),
        );

        return new PublicCmsController(
            new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
            new PublicCmsServiceProvider(static fn (): PublicCmsService => $service),
        );
    }

    private function router(PublicCmsController $controller): Router
    {
        $router = new Router();
        $views = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');

        foreach (['web.php', 'api.php'] as $routeFile) {
            $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $routeFile;
            $registrar($router, $views, $controller);
        }

        return $router;
    }

    private static function page(int $id, string $slug, string $status, string $content): CmsPage
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        $title = $slug === 'about' ? 'About' : ucwords(str_replace('-', ' ', $slug));

        return new CmsPage(
            $id,
            $title,
            $slug,
            $content,
            $slug === 'about' ? 'About SEO' : null,
            $slug === 'about' ? 'About description' : null,
            $status,
            $status === CmsPage::PUBLISHED ? $timestamp : null,
            1,
            1,
            $timestamp,
            $timestamp,
        );
    }

    private static function profile(
        int $pageId,
        string $contentType,
        ?string $summary,
        ?string $noticeStyle = null,
        bool $featured = false,
        bool $pinned = false,
    ): PageContentProfile {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');

        return new PageContentProfile(
            $pageId,
            $contentType,
            $summary,
            $noticeStyle,
            $timestamp,
            $contentType === 'notice' ? new DateTimeImmutable('2026-08-31 04:00:00 UTC') : null,
            $featured,
            $pinned,
            null,
            null,
            null,
            null,
            false,
            1,
            $timestamp,
            $timestamp,
        );
    }

    private static function setting(int $id, string $key, string $value, string $type, bool $public): SiteSetting
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        return new SiteSetting($id, $key, $value, $type, $public, 1, $timestamp, $timestamp);
    }

    private static function activity(): Activity
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        return new Activity(
            1,
            'Village Walk',
            'village-walk',
            'Explore nearby paths.',
            '500.00',
            true,
            true,
            1,
            $timestamp,
            $timestamp,
            'Explore nearby paths.',
            'gallery/' . str_repeat('a', 64) . '.webp',
            'per person',
            '2 hours',
            'Sauraha',
            true,
            1,
        );
    }

    private static function room(
        int $id = 1,
        string $name = 'Garden Room',
        string $slug = 'garden-room',
        string $number = 'R-PRIVATE-01',
    ): Room
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        $type = new RoomType(
            $id,
            'Garden Suite ' . (string) $id,
            'garden-suite-' . (string) $id,
            null,
            '2500.00',
            2,
            1,
            true,
            $timestamp,
            $timestamp,
        );

        return new Room(
            $id,
            $type,
            $number,
            $name,
            $slug,
            'A quiet room beside the garden.',
            '2500.00',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
            $timestamp,
            $timestamp,
            null,
        );
    }

    private static function roomImage(): RoomImage
    {
        return new RoomImage(
            1,
            1,
            'uploads/rooms/' . str_repeat('b', 64) . '.webp',
            'Garden room',
            0,
            true,
            new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
        );
    }

    private static function gallery(): GalleryItem
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        return new GalleryItem(1, null, 'Courtyard', null, 'gallery/' . str_repeat('1', 64) . '.png', 'Courtyard image', 1, true, true, $timestamp, $timestamp);
    }
}

final class PublicCmsPageRepositoryFake implements PageRepository
{
    /** @var array<int, CmsPage> */ public array $pages = [];
    public bool $failure = false;
    public function findById(int $id): ?CmsPage { return $this->pages[$id] ?? null; }
    public function findBySlug(string $slug): ?CmsPage { foreach ($this->pages as $page) if ($page->slug() === $slug) return $page; return null; }
    public function findPublishedBySlug(string $slug): ?CmsPage
    {
        if ($this->failure) throw new RuntimeException('SQLSTATE private pages connection');
        $page = $this->findBySlug($slug); return $page?->isPublished() ? $page : null;
    }
    public function findAll(): array { return array_values($this->pages); }
    public function findAllPublished(): array { return array_values(array_filter($this->pages, static fn (CmsPage $page): bool => $page->isPublished())); }
    public function createDraft(string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): CmsPage { throw new RuntimeException('Not supported.'); }
    public function updateContent(int $id, string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): ?CmsPage { throw new RuntimeException('Not supported.'); }
    public function transitionStatus(int $id, string $expectedStatus, string $newStatus, ?DateTimeImmutable $publishedAt, int $actorId): ?CmsPage { throw new RuntimeException('Not supported.'); }
}

final class PublicCmsProfileRepositoryFake implements PageContentProfileRepository
{
    /** @var array<int, PageContentProfile> */ public array $profiles = [];
    public function findByPageId(int $pageId): ?PageContentProfile { return $this->profiles[$pageId] ?? null; }
    public function upsertCore(int $pageId, string $contentType, ?string $summary, int $updatedBy): PageContentProfile { throw new RuntimeException('Not supported.'); }
    public function updatePublishing(int $pageId, ?string $noticeStyle, ?DateTimeImmutable $publishAt, ?DateTimeImmutable $expiresAt, bool $featured, bool $pinned, ?int $featuredMediaId, ?string $socialTitle, ?string $socialDescription, ?int $socialMediaId, bool $seoNoindex, int $updatedBy): PageContentProfile { throw new RuntimeException('Not supported.'); }
    public function updatePublication(int $pageId, DateTimeImmutable $publishAt, int $updatedBy): void { throw new RuntimeException('Not supported.'); }
    public function replaceBlockMedia(int $pageId, array $usages): void { throw new RuntimeException('Not supported.'); }
}

final class PublicCmsSettingRepositoryFake implements SettingRepository
{
    /** @var array<string, SiteSetting> */ public array $settings = [];
    public function findByKey(string $key): ?SiteSetting { return $this->settings[$key] ?? null; }
    public function findAll(): array { return array_values($this->settings); }
    public function findAllPublic(): array { return array_values(array_filter($this->settings, static fn (SiteSetting $setting): bool => $setting->isPublic())); }
    public function upsert(string $key, string $value, string $valueType, bool $public, int $actorId): SiteSetting { throw new RuntimeException('Not supported.'); }
}
