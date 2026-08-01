<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicActivityController;
use App\Core\Request;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Models\Activity;
use App\Repositories\ActivityCatalogRepository;
use App\Services\PublicActivityService;
use App\Services\PublicActivityServiceProvider;
use App\Storage\GalleryImageReader;
use App\Storage\RetrievedGalleryImage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicActivityExperienceTest extends TestCase
{
    private ActivityCatalogRepository $repository;

    private Router $router;

    private GalleryImageReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createStub(ActivityCatalogRepository::class);
        $this->reader = $this->createStub(GalleryImageReader::class);
        $controller = new PublicActivityController(
            new ViewRenderer(
                dirname(__DIR__, 2)
                    . DIRECTORY_SEPARATOR
                    . 'resources'
                    . DIRECTORY_SEPARATOR
                    . 'views',
            ),
            new PublicActivityServiceProvider(
                fn (): PublicActivityService => new PublicActivityService($this->repository, $this->reader),
            ),
        );
        $this->router = new Router();
        $views = new ViewRenderer(
            dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR
                . 'resources'
                . DIRECTORY_SEPARATOR
                . 'views',
        );

        foreach (['web.php', 'api.php'] as $routeFile) {
            $registrar = require dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR
                . 'routes'
                . DIRECTORY_SEPARATOR
                . $routeFile;
            $registrar($this->router, $views, $controller);
        }
    }

    public function testPublicPageRendersOnlyEligibleEscapedActivitiesInApprovedOrder(): void
    {
        $this->repository->method('findAllPublic')->willReturn([
            self::activity(
                2,
                '<script>Village Walk</script>',
                'village-walk',
                "Explore <b>nearby</b> paths.\nBring water.",
                '0.00',
                true,
                true,
                3,
            ),
            self::activity(
                3,
                'Draft Activity',
                'draft-activity',
                'This must remain hidden.',
                '900.00',
                true,
                false,
                4,
            ),
            self::activity(
                5,
                'Sunrise Hike',
                'sunrise-hike',
                'Start the day outdoors.',
                '1200.00',
                true,
                true,
                8,
            ),
        ]);

        $response = $this->router->dispatch(Request::create('GET', '/activities'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringContainsString('Activities | Tara Guest House', $response->body());
        self::assertStringContainsString('&lt;script&gt;Village Walk&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Village Walk</script>', $response->body());
        self::assertStringContainsString('Explore &lt;b&gt;nearby&lt;/b&gt; paths.<br', $response->body());
        self::assertStringContainsString('NPR 0.00', $response->body());
        self::assertStringContainsString('NPR 1200.00', $response->body());
        self::assertStringNotContainsString('Draft Activity', $response->body());
        self::assertStringNotContainsString('is_active', $response->body());
        self::assertStringNotContainsString('is_published', $response->body());
        self::assertStringNotContainsString('sort_order', $response->body());
        self::assertStringContainsString('href="/activities"', $response->body());
        self::assertLessThan(
            strpos($response->body(), 'Sunrise Hike'),
            strpos($response->body(), '&lt;script&gt;Village Walk&lt;/script&gt;'),
        );
    }

    public function testPublicJsonReturnsOnlyApprovedFieldsAndNoInternalState(): void
    {
        $this->repository->method('findAllPublic')->willReturn([
            self::activity(
                7,
                'Village Walk',
                'village-walk',
                'Explore nearby paths.',
                '500.00',
                true,
                true,
                1,
            ),
        ]);

        $response = $this->router->dispatch(Request::create('GET', '/api/v1/activities'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame([
            'success' => true,
            'data' => [[
                'activity_id' => 7,
                'title' => 'Village Walk',
                'slug' => 'village-walk',
                'summary' => 'Explore nearby paths.',
                'description' => 'Explore nearby paths.',
                'image_url' => null,
                'price' => '500.00',
                'price_unit' => null,
                'duration' => null,
                'location' => null,
            ]],
        ], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('is_active', $response->body());
        self::assertStringNotContainsString('is_published', $response->body());
        self::assertStringNotContainsString('sort_order', $response->body());
        self::assertStringNotContainsString('created_at', $response->body());
        self::assertStringNotContainsString('updated_at', $response->body());
    }

    public function testEmptyPageAndUnsupportedRoutesReturnSafeResponses(): void
    {
        $this->repository->method('findAllPublic')->willReturn([]);

        $page = $this->router->dispatch(Request::create('GET', '/activities'));

        self::assertSame(200, $page->statusCode());
        self::assertStringContainsString('No activities are currently published.', $page->body());
        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/activities'))->statusCode());
        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/api/v1/activities'))->statusCode());
        self::assertSame(404, $this->router->dispatch(Request::create('GET', '/activities/not-approved'))->statusCode());
    }

    public function testRepositoryFailureProducesSanitizedNoStoreResponses(): void
    {
        $this->repository->method('findAllPublic')->willThrowException(
            new RuntimeException('SQLSTATE private activity connection details'),
        );

        $page = $this->router->dispatch(Request::create('GET', '/activities'));
        $api = $this->router->dispatch(Request::create('GET', '/api/v1/activities'));

        self::assertSame(500, $page->statusCode());
        self::assertSame('Internal Server Error', $page->body());
        self::assertSame('no-store', $page->headers()['Cache-Control']);
        self::assertSame(500, $api->statusCode());
        self::assertSame('no-store', $api->headers()['Cache-Control']);
        self::assertSame([
            'success' => false,
            'message' => 'Internal Server Error.',
        ], json_decode($api->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('SQLSTATE', $api->body());
        self::assertStringNotContainsString('private', $api->body());
    }

    public function testEligibleActivityImageUsesControlledHeadersAndExactBytes(): void
    {
        $path = 'gallery/' . str_repeat('a', 64) . '.png';
        $this->repository->method('findPublicById')->willReturn(self::activity(
            7, 'Village Walk', 'village-walk', 'Explore nearby paths.', '500.00', true, true, 1, $path,
        ));
        $this->reader = $this->createMock(GalleryImageReader::class);
        $this->reader->expects(self::once())->method('read')->with($path)->willReturn(
            new RetrievedGalleryImage('safe image bytes', 'png', 'image/png', 1, 1),
        );

        $response = $this->router->dispatch(Request::create('GET', '/activities?image=7'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('safe image bytes', $response->body());
        self::assertSame('image/png', $response->headers()['Content-Type']);
        self::assertSame('16', $response->headers()['Content-Length']);
        self::assertSame('inline; filename="activity-image-7.png"', $response->headers()['Content-Disposition']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
    }

    public function testMalformedMissingAndIneligibleActivityImagesShareSafeNotFound(): void
    {
        $this->repository->method('findPublicById')->willReturn(null);

        foreach (['bad', '0', '7&extra=value', '7'] as $query) {
            $response = $this->router->dispatch(Request::create('GET', '/activities?image=' . $query));

            self::assertSame(404, $response->statusCode());
            self::assertSame('Not Found', $response->body());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
            self::assertStringNotContainsString('gallery/', $response->body());
            self::assertStringNotContainsString('C:\\', $response->body());
        }
    }

    private static function activity(
        int $id,
        string $title,
        string $slug,
        string $description,
        string $price,
        bool $active,
        bool $published,
        int $sortOrder,
        ?string $featuredImagePath = null,
    ): Activity {
        $timestamp = new DateTimeImmutable('2026-07-30 12:00:00 UTC');

        return new Activity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $active,
            $published,
            $sortOrder,
            $timestamp,
            $timestamp,
            null,
            $featuredImagePath,
        );
    }
}
