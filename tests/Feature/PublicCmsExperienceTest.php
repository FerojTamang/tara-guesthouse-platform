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
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Presentation\RoomImageUrl;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\GalleryCatalogRepository;
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
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pages = new PublicCmsPageRepositoryFake();
        $this->settings = new PublicCmsSettingRepositoryFake();
        $this->pages->pages = [
            1 => self::page(1, 'about', CmsPage::PUBLISHED, "Welcome <script>alert(1)</script>.\nSecond line."),
            2 => self::page(2, 'private-draft', CmsPage::DRAFT, 'Draft content.'),
            3 => self::page(3, 'rooms', CmsPage::PUBLISHED, 'Reserved route content.'),
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
        self::assertStringContainsString('row-cols-1 row-cols-md-2 row-cols-xl-3', $response->body());
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
        $roomRepository->method('findAllPublic')->willReturn([]);
        $activityRepository = $this->createStub(ActivityCatalogRepository::class);
        $activityRepository->method('findAllPublic')->willReturn([self::activity()]);
        $galleryRepository = $this->createStub(GalleryCatalogRepository::class);
        $galleryRepository->method('findAllPublicItems')->willReturn([self::gallery()]);
        $service = new PublicCmsService(
            $this->pages,
            $this->settings,
            $definitions,
            new PublicRoomCatalogService(
                $roomRepository,
                $this->createStub(RoomImageCatalogRepository::class),
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

    private static function setting(int $id, string $key, string $value, string $type, bool $public): SiteSetting
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        return new SiteSetting($id, $key, $value, $type, $public, 1, $timestamp, $timestamp);
    }

    private static function activity(): Activity
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        return new Activity(1, 'Village Walk', 'village-walk', 'Explore nearby paths.', '500.00', true, true, 1, $timestamp, $timestamp);
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

final class PublicCmsSettingRepositoryFake implements SettingRepository
{
    /** @var array<string, SiteSetting> */ public array $settings = [];
    public function findByKey(string $key): ?SiteSetting { return $this->settings[$key] ?? null; }
    public function findAll(): array { return array_values($this->settings); }
    public function findAllPublic(): array { return array_values(array_filter($this->settings, static fn (SiteSetting $setting): bool => $setting->isPublic())); }
    public function upsert(string $key, string $value, string $valueType, bool $public, int $actorId): SiteSetting { throw new RuntimeException('Not supported.'); }
}
