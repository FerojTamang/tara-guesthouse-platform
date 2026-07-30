<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicGalleryController;
use App\Core\Request;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Models\GalleryItem;
use App\Repositories\GalleryCatalogRepository;
use App\Services\PublicGalleryService;
use App\Services\PublicGalleryServiceProvider;
use App\Storage\GalleryImageReader;
use App\Storage\RetrievedGalleryImage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicGalleryExperienceTest extends TestCase
{
    private GalleryCatalogRepository $repository;
    private GalleryImageReader $reader;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createStub(GalleryCatalogRepository::class);
        $this->reader = $this->createStub(GalleryImageReader::class);
        $controller = new PublicGalleryController(
            new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
            new PublicGalleryServiceProvider(
                fn (): PublicGalleryService => new PublicGalleryService($this->repository, $this->reader),
            ),
        );
        $this->router = new Router();
        $views = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');

        foreach (['web.php', 'api.php'] as $routeFile) {
            $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $routeFile;
            $registrar($this->router, $views, $controller);
        }
    }

    public function testPublicPageRendersEligibleEscapedResponsiveGalleryInApprovedOrder(): void
    {
        $this->repository->method('findAllPublicItems')->willReturn([
            self::item(2, '<script>Courtyard</script>', "Quiet <b>space</b>.\nMorning light."),
            self::item(5, 'Guest House Exterior', null),
        ]);

        $response = $this->router->dispatch(Request::create('GET', '/gallery'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringContainsString('Gallery | Tara Guest House', $response->body());
        self::assertStringContainsString('row-cols-1 row-cols-sm-2 row-cols-lg-3', $response->body());
        self::assertStringContainsString('loading="lazy"', $response->body());
        self::assertStringContainsString('decoding="async"', $response->body());
        self::assertStringContainsString('src="/gallery?image=2"', $response->body());
        self::assertStringContainsString('alt="Accessible gallery image 2"', $response->body());
        self::assertStringContainsString('&lt;script&gt;Courtyard&lt;/script&gt;', $response->body());
        self::assertStringContainsString('Quiet &lt;b&gt;space&lt;/b&gt;.<br', $response->body());
        self::assertStringNotContainsString('<script>Courtyard</script>', $response->body());
        self::assertStringNotContainsString('gallery/' . str_repeat('2', 64) . '.png', $response->body());
        self::assertStringNotContainsString('is_active', $response->body());
        self::assertStringContainsString('href="/gallery"', $response->body());
        self::assertLessThan(
            strpos($response->body(), 'Guest House Exterior'),
            strpos($response->body(), '&lt;script&gt;Courtyard&lt;/script&gt;'),
        );
    }

    public function testPublicJsonReturnsOnlyApprovedFieldsAndSafeImageUrl(): void
    {
        $this->repository->method('findAllPublicItems')->willReturn([
            self::item(7, 'Courtyard', 'Morning light'),
        ]);

        $response = $this->router->dispatch(Request::create('GET', '/api/v1/gallery'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'data' => [[
                'gallery_id' => 7,
                'title' => 'Courtyard',
                'caption' => 'Morning light',
                'alt_text' => 'Accessible gallery image 7',
                'image_url' => '/gallery?image=7',
            ]],
        ], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('file_path', $response->body());
        self::assertStringNotContainsString('sort_order', $response->body());
        self::assertStringNotContainsString('created_at', $response->body());
        self::assertStringNotContainsString('is_published', $response->body());
    }

    public function testEligiblePrivateImageIsDeliveredWithSafeHeaders(): void
    {
        $item = self::item(9, 'Garden', null);
        $bytes = 'validated-png-bytes';
        $this->repository->method('findPublicItemById')->willReturn($item);
        $this->reader = $this->createMock(GalleryImageReader::class);
        $this->reader
            ->expects($this->exactly(2))
            ->method('read')
            ->with($item->filePath())
            ->willReturn(new RetrievedGalleryImage($bytes, 'png', 'image/png', 1, 1));

        $response = $this->router->dispatch(Request::create('GET', '/gallery?image=9'));
        $head = $this->router->dispatch(Request::create('HEAD', '/gallery?image=9'));

        self::assertSame(200, $response->statusCode());
        self::assertSame($bytes, $response->body());
        self::assertSame('image/png', $response->headers()['Content-Type']);
        self::assertSame((string) strlen($bytes), $response->headers()['Content-Length']);
        self::assertSame('inline; filename="gallery-image-9.png"', $response->headers()['Content-Disposition']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(200, $head->statusCode());
        self::assertSame('', $head->body());
        self::assertSame((string) strlen($bytes), $head->headers()['Content-Length']);
    }

    public function testInvalidHiddenAndUnsupportedImageRequestsFailSafely(): void
    {
        $this->repository->method('findPublicItemById')->willReturn(null);

        foreach ([
            '/gallery?image=0',
            '/gallery?image=-1',
            '/gallery?image=abc',
            '/gallery?image[]=1',
            '/gallery?image=1&extra=1',
            '/gallery?unknown=1',
            '/gallery?image=999',
        ] as $uri) {
            $response = $this->router->dispatch(Request::create('GET', $uri));
            self::assertSame(404, $response->statusCode(), $uri);
            self::assertSame('no-store', $response->headers()['Cache-Control']);
            self::assertSame('Not Found', $response->body());
        }

        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/gallery'))->statusCode());
        self::assertSame(405, $this->router->dispatch(Request::create('POST', '/api/v1/gallery'))->statusCode());
        self::assertSame(404, $this->router->dispatch(Request::create('GET', '/gallery/not-approved'))->statusCode());
    }

    public function testEmptyAndFailureResponsesAreSafeAndNoStore(): void
    {
        $this->repository->method('findAllPublicItems')->willReturn([]);
        $empty = $this->router->dispatch(Request::create('GET', '/gallery'));
        self::assertSame(200, $empty->statusCode());
        self::assertStringContainsString('No gallery images are currently published.', $empty->body());

        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findAllPublicItems')->willThrowException(
            new RuntimeException('SQLSTATE private gallery connection'),
        );
        $controller = new PublicGalleryController(
            new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
            new PublicGalleryServiceProvider(fn (): PublicGalleryService => new PublicGalleryService($repository, $this->reader)),
        );
        $router = new Router();
        $views = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        foreach (['web.php', 'api.php'] as $routeFile) {
            $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $routeFile;
            $registrar($router, $views, $controller);
        }

        $page = $router->dispatch(Request::create('GET', '/gallery'));
        $api = $router->dispatch(Request::create('GET', '/api/v1/gallery'));
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

    private static function item(int $id, string $title, ?string $caption): GalleryItem
    {
        $timestamp = new DateTimeImmutable('2026-07-31 00:00:00 UTC');

        return new GalleryItem(
            $id,
            null,
            $title,
            $caption,
            'gallery/' . str_repeat(dechex($id % 16), 64) . '.png',
            'Accessible gallery image ' . $id,
            $id,
            true,
            true,
            $timestamp,
            $timestamp,
        );
    }
}
