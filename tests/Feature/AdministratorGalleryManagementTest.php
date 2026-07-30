<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorGalleryController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\Environment;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Models\AuditLogEntry;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Requests\AdministratorLoginRequest;
use App\Requests\GalleryImageUploadRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorGalleryService;
use App\Services\AdministratorLogoutService;
use App\Services\GalleryImageStorageProvider;
use App\Services\GalleryManagementServiceProvider;
use App\Services\GalleryTransaction;
use App\Storage\GalleryImageStorage;
use App\Storage\StoredGalleryImage;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorGalleryManagementTest extends TestCase
{
    private string $savePath;
    private string $providerBasePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;
    private AdministratorSession $administratorSession;
    private CsrfTokenManager $csrfTokens;
    private Application $application;
    private AdministratorGalleryRepositoryFeatureFake $repository;
    private AdministratorGalleryAuditFeatureFake $audit;
    private AdministratorGalleryStorageFeatureFake $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-gallery-session-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->providerBasePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-gallery-provider-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->providerBasePath, 0700));
        self::assertTrue(mkdir($this->providerBasePath . DIRECTORY_SEPARATOR . 'config', 0700));
        self::assertTrue(mkdir($this->providerBasePath . DIRECTORY_SEPARATOR . 'public', 0700));
        self::assertTrue(mkdir($this->providerBasePath . DIRECTORY_SEPARATOR . 'storage', 0700));
        self::assertTrue(mkdir(
            $this->providerBasePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private',
            0700,
        ));
        self::assertTrue(mkdir(
            $this->providerBasePath
                . DIRECTORY_SEPARATOR
                . 'storage'
                . DIRECTORY_SEPARATOR
                . 'private'
                . DIRECTORY_SEPARATOR
                . 'gallery',
            0700,
        ));
        self::assertTrue(copy(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php',
            $this->providerBasePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php',
        ));

        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->repository = new AdministratorGalleryRepositoryFeatureFake();
        $this->audit = new AdministratorGalleryAuditFeatureFake();
        $this->storage = new AdministratorGalleryStorageFeatureFake();
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR
                . 'resources'
                . DIRECTORY_SEPARATOR
                . 'views',
        );
        $logger = new NullLogger();
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new AdministratorGalleryAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $imageProvider = new GalleryImageStorageProvider(
            $this->providerBasePath,
            Environment::fromArray([
                'GALLERY_IMAGE_FORMATS' => 'png',
                'GALLERY_IMAGE_MAX_BYTES' => '100000',
                'GALLERY_IMAGE_MIN_WIDTH' => '1',
                'GALLERY_IMAGE_MIN_HEIGHT' => '1',
                'GALLERY_IMAGE_MAX_WIDTH' => '100',
                'GALLERY_IMAGE_MAX_HEIGHT' => '100',
                'GALLERY_IMAGE_REQUIRE_DECODABILITY' => 'true',
            ]),
        );
        $galleryController = new AdministratorGalleryController(
            $renderer,
            new GalleryManagementServiceProvider(
                fn (): AdministratorGalleryService => new AdministratorGalleryService(
                    $this->repository,
                    $this->storage,
                    $this->audit,
                    new AdministratorGalleryTransactionFeatureFake(),
                    static fn (): DateTimeImmutable => new DateTimeImmutable(
                        '2026-07-30 10:30:00 UTC',
                    ),
                ),
            ),
            $imageProvider,
            $this->administratorSession,
            $this->csrfTokens,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $guestOnly = new GuestOnlyMiddleware($this->administratorSession);
        $requireAdministrator = new RequireAdministratorMiddleware($this->administratorSession);
        $csrf = new CsrfMiddleware($this->csrfTokens);
        $router = new Router();
        $adminRegistrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'admin.php';
        $adminRegistrar(
            router: $router,
            viewRenderer: $renderer,
            controller: $authenticationController,
            guestOnly: $guestOnly,
            requireAdministrator: $requireAdministrator,
            csrf: $csrf,
            galleryController: $galleryController,
        );
        $apiRegistrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'api.php';
        $apiRegistrar(
            $router,
            $renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            $galleryController,
        );
        $this->application = new Application(
            $router,
            new ErrorHandler($logger, false),
            $this->session,
        );
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }

        $this->removeTree($this->savePath);
        $this->removeTree($this->providerBasePath);

        parent::tearDown();
    }

    public function testGuestCannotReadPagesOrJsonAndAuthenticationRunsBeforeCsrf(): void
    {
        foreach (['/admin/gallery', '/admin/gallery/categories', '/admin/gallery/create'] as $path) {
            $response = $this->handle(Request::create('GET', $path));
            self::assertSame(302, $response->statusCode());
            self::assertSame('/admin/login', $response->headers()['Location']);
            $this->reopenSession();
        }

        $read = $this->handle(Request::create(
            'GET',
            '/api/v1/admin/gallery',
            headers: ['Accept' => 'application/json'],
        ));
        self::assertSame(401, $read->statusCode());
        $this->reopenSession();

        $mutation = $this->handle(Request::create(
            'PATCH',
            '/api/v1/admin/gallery/11/active',
            headers: ['Content-Type' => 'application/json'],
            body: '{"is_active":false}',
        ));
        self::assertSame(401, $mutation->statusCode());
    }

    public function testPagesEscapeContentAndExposeOnlyApprovedManagementControls(): void
    {
        $this->repository->categories[7] = $this->category(name: '<script>Nature</script>', slug: 'nature');
        $this->repository->items[11] = $this->item(
            title: '<script>Sunrise</script>',
            categoryId: 7,
            active: true,
            published: false,
        );
        $this->authenticate();

        $index = $this->handle(Request::create('GET', '/admin/gallery'));
        self::assertSame(200, $index->statusCode());
        self::assertStringContainsString('&lt;script&gt;Sunrise&lt;/script&gt;', $index->body());
        self::assertStringNotContainsString('<script>Sunrise</script>', $index->body());
        self::assertStringContainsString('/api/v1/admin/gallery/11/active', $index->body());
        self::assertStringContainsString('/api/v1/admin/gallery/11/publication', $index->body());
        self::assertStringContainsString('/api/v1/admin/gallery/reorder', $index->body());
        self::assertStringContainsString('The record and private image will be retained.', $index->body());
        self::assertStringNotContainsString('name="featured"', $index->body());

        $categories = $this->handle(Request::create('GET', '/admin/gallery/categories'));
        self::assertSame(200, $categories->statusCode());
        self::assertStringContainsString('&lt;script&gt;Nature&lt;/script&gt;', $categories->body());
        self::assertStringContainsString('/api/v1/admin/gallery-categories/7', $categories->body());

        $create = $this->handle(Request::create('GET', '/admin/gallery/create'));
        self::assertSame(200, $create->statusCode());
        self::assertStringContainsString('enctype="multipart/form-data"', $create->body());
        self::assertStringContainsString('name="alt_text"', $create->body());
        self::assertStringContainsString('name="image"', $create->body());
        self::assertStringContainsString('accept="image/png"', $create->body());
        self::assertStringContainsString(
            'Maximum 100,000 bytes; dimensions 1x1 to 100x100 pixels.',
            $create->body(),
        );
        self::assertStringNotContainsString('name="file_path"', $create->body());
    }

    public function testCategoryCreateAndUpdateUseExactProtectedJsonContracts(): void
    {
        $this->authenticate();

        $created = $this->jsonRequest('POST', '/api/v1/admin/gallery-categories', [
            'name' => 'Nature',
            'slug' => 'nature',
            'is_active' => true,
        ]);
        self::assertSame(201, $created->statusCode());
        self::assertSame('nature', $this->decoded($created)['data']['slug']);
        self::assertCount(1, $this->repository->categories);

        $updated = $this->jsonRequest('PUT', '/api/v1/admin/gallery-categories/1', [
            'name' => 'Landscape',
            'slug' => 'landscape',
            'is_active' => false,
        ]);
        self::assertSame(200, $updated->statusCode());
        self::assertFalse($this->decoded($updated)['data']['is_active']);
        self::assertSame('landscape', $this->repository->categories[1]->slug());
        self::assertSame([
            'gallery_category_created',
            'gallery_category_updated',
        ], array_map(
            static fn (AuditLogEntry $entry): string => $entry->eventType(),
            $this->audit->entries,
        ));

        $extra = $this->jsonRequest('POST', '/api/v1/admin/gallery-categories', [
            'name' => 'Extra',
            'slug' => 'extra',
            'is_active' => true,
            'featured' => true,
        ]);
        self::assertSame(422, $extra->statusCode());
    }

    public function testMetadataStateReorderAndRemovalRetainTheGalleryRecord(): void
    {
        $this->repository->categories[7] = $this->category();
        $this->repository->items[11] = $this->item(active: true, published: false, sortOrder: 10);
        $this->repository->items[12] = $this->item(id: 12, active: true, published: true, sortOrder: 20);
        $this->authenticate();

        $updated = $this->jsonRequest('PUT', '/api/v1/admin/gallery/11', [
            'gallery_category_id' => 7,
            'title' => 'Updated sunrise',
            'caption' => null,
            'alt_text' => 'Updated accessible sunrise description',
            'sort_order' => 15,
        ]);
        self::assertSame(200, $updated->statusCode());
        self::assertSame('Updated sunrise', $this->decoded($updated)['data']['title']);
        self::assertSame(
            'gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.png',
            $this->repository->items[11]->filePath(),
        );

        $active = $this->jsonRequest('PATCH', '/api/v1/admin/gallery/11/active', [
            'is_active' => false,
        ]);
        self::assertSame(200, $active->statusCode());
        self::assertFalse($this->repository->items[11]->isActive());
        self::assertFalse($this->repository->items[11]->isPublished());

        $published = $this->jsonRequest('PATCH', '/api/v1/admin/gallery/11/publication', [
            'is_published' => true,
        ]);
        self::assertSame(200, $published->statusCode());
        self::assertFalse($this->repository->items[11]->isActive());
        self::assertTrue($this->repository->items[11]->isPublished());

        $reordered = $this->jsonRequest('PATCH', '/api/v1/admin/gallery/reorder', [
            'items' => [
                ['id' => 11, 'sort_order' => 30],
                ['id' => 12, 'sort_order' => 5],
            ],
        ]);
        self::assertSame(200, $reordered->statusCode());
        self::assertSame(30, $this->repository->items[11]->sortOrder());
        self::assertSame(5, $this->repository->items[12]->sortOrder());

        $deleted = $this->jsonRequest('DELETE', '/api/v1/admin/gallery/11', []);
        self::assertSame(200, $deleted->statusCode());
        self::assertArrayHasKey(11, $this->repository->items);
        self::assertFalse($this->repository->items[11]->isActive());
        self::assertFalse($this->repository->items[11]->isPublished());
        self::assertSame([], $this->storage->removed);
    }

    public function testUploadCreateValidatesMultipartAndDoesNotAcceptClientFilePaths(): void
    {
        $this->authenticate();

        $wrongType = $this->jsonRequest('POST', '/api/v1/admin/gallery', [
            'file_path' => 'gallery/attacker.png',
        ]);
        self::assertSame(415, $wrongType->statusCode());

        $missingFile = $this->handle(Request::create(
            'POST',
            '/api/v1/admin/gallery',
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'multipart/form-data; boundary=test',
                'X-CSRF-Token' => $this->csrfToken(),
            ],
            input: $this->multipartPayload(),
            remoteAddress: '127.0.0.1',
        ));
        self::assertSame(422, $missingFile->statusCode());

        if (
            !function_exists('imagecreatefromstring')
            || !function_exists('imagetypes')
            || (imagetypes() & IMG_PNG) !== IMG_PNG
        ) {
            self::markTestSkipped('PNG decoding is unavailable for the successful upload feature path.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'tgh-gallery-upload-');
        self::assertIsString($temporary);
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);
        self::assertSame(strlen($bytes), file_put_contents($temporary, $bytes));

        try {
            $created = $this->handle(Request::create(
                'POST',
                '/api/v1/admin/gallery',
                headers: [
                    'Accept' => 'application/json',
                    'Content-Type' => 'multipart/form-data; boundary=test',
                    'X-CSRF-Token' => $this->csrfToken(),
                    'User-Agent' => 'Gallery Feature Test',
                ],
                input: $this->multipartPayload(),
                remoteAddress: '127.0.0.1',
                files: [
                    'image' => [
                        'name' => 'sunrise.png',
                        'type' => 'application/octet-stream',
                        'tmp_name' => $temporary,
                        'error' => UPLOAD_ERR_OK,
                        'size' => strlen($bytes),
                    ],
                ],
            ));
        } finally {
            @unlink($temporary);
        }

        self::assertSame(201, $created->statusCode());
        self::assertSame(1, $this->storage->stores);
        self::assertSame('gallery/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png', $this->decoded($created)['data']['file_path']);
        self::assertCount(1, $this->repository->items);
    }

    public function testMalformedJsonUnknownFieldsAndMissingTargetsFailSafely(): void
    {
        $this->authenticate();

        $malformed = $this->handle(Request::create(
            'PUT',
            '/api/v1/admin/gallery/11',
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfToken(),
            ],
            body: '{',
        ));
        self::assertSame(400, $malformed->statusCode());

        $arrayBody = $this->handle(Request::create(
            'DELETE',
            '/api/v1/admin/gallery/11',
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfToken(),
            ],
            body: '[]',
        ));
        self::assertSame(400, $arrayBody->statusCode());

        $missing = $this->jsonRequest('DELETE', '/api/v1/admin/gallery/999', []);
        self::assertSame(404, $missing->statusCode());
        self::assertStringNotContainsString('SQLSTATE', $missing->body());
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    private function handle(Request $request): \App\Core\Response
    {
        $this->ensureSessionActive();

        return $this->application->handle($request);
    }

    private function csrfToken(): string
    {
        $this->ensureSessionActive();

        return $this->csrfTokens->token();
    }

    private function ensureSessionActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->reopenSession();
        }
    }

    private function reopenSession(): void
    {
        $this->session = Session::start($this->sessionConfiguration);
    }

    /** @param array<string, mixed> $payload */
    private function jsonRequest(string $method, string $path, array $payload): \App\Core\Response
    {
        return $this->handle(Request::create(
            $method,
            $path,
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfToken(),
                'User-Agent' => 'Gallery Feature Test',
            ],
            body: json_encode($payload === [] ? (object) [] : $payload, JSON_THROW_ON_ERROR),
            remoteAddress: '127.0.0.1',
        ));
    }

    /** @return array<string, mixed> */
    private function decoded(\App\Core\Response $response): array
    {
        $decoded = json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @return array<string, string> */
    private function multipartPayload(): array
    {
        return [
            'gallery_category_id' => '',
            'title' => 'Riverside Sunrise',
            'caption' => 'Morning light beside the river.',
            'alt_text' => 'Sunrise over the river beside Tara Guest House',
            'sort_order' => '10',
            'is_active' => '1',
            'is_published' => '0',
        ];
    }

    private function category(
        int $id = 7,
        string $name = 'Nature',
        string $slug = 'nature',
        bool $active = true,
    ): GalleryCategory {
        return new GalleryCategory(
            $id,
            $name,
            $slug,
            $active,
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 09:00:00 UTC'),
        );
    }

    private function item(
        int $id = 11,
        string $title = 'Riverside Sunrise',
        ?int $categoryId = null,
        bool $active = true,
        bool $published = true,
        int $sortOrder = 10,
    ): GalleryItem {
        return new GalleryItem(
            $id,
            $categoryId,
            $title,
            'Morning light beside the river.',
            'gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.png',
            'Sunrise over the river beside Tara Guest House',
            $sortOrder,
            $active,
            $published,
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 09:00:00 UTC'),
        );
    }

    /** @return array<string, int> */
    private static function loginPolicy(): array
    {
        return [
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'identifier_max_bytes' => 150,
            'password_max_bytes' => 72,
            'max_failed_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ];
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'name' => 'tgh_' . bin2hex(random_bytes(8)),
            'inactivity_timeout' => 1800,
            'absolute_lifetime' => 43200,
            'cookie' => [
                'lifetime' => 0,
                'secure' => false,
                'http_only' => true,
                'same_site' => 'Lax',
                'path' => '/',
                'domain' => null,
            ],
            'strict_mode' => true,
            'use_cookies' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'save_handler' => 'files',
            'save_path' => realpath($this->savePath),
            'gc_maxlifetime' => 43200,
        ];
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($path);
    }
}

final class AdministratorGalleryRepositoryFeatureFake implements GalleryCatalogRepository
{
    /** @var array<int, GalleryCategory> */
    public array $categories = [];

    /** @var array<int, GalleryItem> */
    public array $items = [];

    public function findCategoryById(int $id): ?GalleryCategory
    {
        return $this->categories[$id] ?? null;
    }

    public function lockCategoryById(int $id): ?GalleryCategory
    {
        return $this->findCategoryById($id);
    }

    public function findCategoryBySlug(string $slug): ?GalleryCategory
    {
        foreach ($this->categories as $category) {
            if ($category->slug() === $slug) {
                return $category;
            }
        }

        return null;
    }

    public function findAllCategories(): array
    {
        return array_values($this->categories);
    }

    public function findAllActiveCategories(): array
    {
        return array_values(array_filter(
            $this->categories,
            static fn (GalleryCategory $category): bool => $category->isActive(),
        ));
    }

    public function createCategory(string $name, string $slug, bool $active): GalleryCategory
    {
        $id = $this->categories === [] ? 1 : max(array_keys($this->categories)) + 1;

        return $this->categories[$id] = $this->newCategory($id, $name, $slug, $active);
    }

    public function updateCategory(int $id, string $name, string $slug): ?GalleryCategory
    {
        $current = $this->categories[$id] ?? null;

        if (!$current instanceof GalleryCategory) {
            return null;
        }

        return $this->categories[$id] = $this->newCategory($id, $name, $slug, $current->isActive());
    }

    public function setCategoryActive(int $id, bool $active): ?GalleryCategory
    {
        $current = $this->categories[$id] ?? null;

        if (!$current instanceof GalleryCategory) {
            return null;
        }

        return $this->categories[$id] = $this->newCategory(
            $id,
            $current->name(),
            $current->slug(),
            $active,
        );
    }

    public function findItemById(int $id): ?GalleryItem
    {
        return $this->items[$id] ?? null;
    }

    public function lockItemById(int $id): ?GalleryItem
    {
        return $this->findItemById($id);
    }

    public function findAllItems(): array
    {
        $items = array_values($this->items);
        usort($items, static fn (GalleryItem $left, GalleryItem $right): int => [
            $left->sortOrder(),
            $left->id(),
        ] <=> [
            $right->sortOrder(),
            $right->id(),
        ]);

        return $items;
    }

    public function findAllPublicItems(): array
    {
        return array_values(array_filter(
            $this->findAllItems(),
            fn (GalleryItem $item): bool => $item->isPubliclyVisible()
                && ($item->categoryId() === null || ($this->categories[$item->categoryId()] ?? null)?->isActive() === true),
        ));
    }

    public function createItem(
        ?int $categoryId,
        string $title,
        ?string $caption,
        string $filePath,
        string $altText,
        int $sortOrder,
        bool $active,
        bool $published,
    ): GalleryItem {
        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;

        return $this->items[$id] = $this->newItem(
            $id,
            $categoryId,
            $title,
            $caption,
            $filePath,
            $altText,
            $sortOrder,
            $active,
            $published,
        );
    }

    public function updateItem(
        int $id,
        ?int $categoryId,
        string $title,
        ?string $caption,
        string $filePath,
        string $altText,
        int $sortOrder,
    ): ?GalleryItem {
        $current = $this->items[$id] ?? null;

        if (!$current instanceof GalleryItem) {
            return null;
        }

        return $this->items[$id] = $this->newItem(
            $id,
            $categoryId,
            $title,
            $caption,
            $filePath,
            $altText,
            $sortOrder,
            $current->isActive(),
            $current->isPublished(),
        );
    }

    public function setItemActive(int $id, bool $active): ?GalleryItem
    {
        return $this->replaceItemState($id, $active, null);
    }

    public function setItemPublished(int $id, bool $published): ?GalleryItem
    {
        return $this->replaceItemState($id, null, $published);
    }

    public function setItemSortOrder(int $id, int $sortOrder): ?GalleryItem
    {
        $current = $this->items[$id] ?? null;

        if (!$current instanceof GalleryItem) {
            return null;
        }

        return $this->items[$id] = $this->newItem(
            $id,
            $current->categoryId(),
            $current->title(),
            $current->caption(),
            $current->filePath(),
            $current->altText(),
            $sortOrder,
            $current->isActive(),
            $current->isPublished(),
        );
    }

    public function deactivateAndUnpublishItem(int $id): ?GalleryItem
    {
        return $this->replaceItemState($id, false, false);
    }

    private function replaceItemState(int $id, ?bool $active, ?bool $published): ?GalleryItem
    {
        $current = $this->items[$id] ?? null;

        if (!$current instanceof GalleryItem) {
            return null;
        }

        return $this->items[$id] = $this->newItem(
            $id,
            $current->categoryId(),
            $current->title(),
            $current->caption(),
            $current->filePath(),
            $current->altText(),
            $current->sortOrder(),
            $active ?? $current->isActive(),
            $published ?? $current->isPublished(),
        );
    }

    private function newCategory(int $id, string $name, string $slug, bool $active): GalleryCategory
    {
        return new GalleryCategory(
            $id,
            $name,
            $slug,
            $active,
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 10:30:00 UTC'),
        );
    }

    private function newItem(
        int $id,
        ?int $categoryId,
        string $title,
        ?string $caption,
        string $filePath,
        string $altText,
        int $sortOrder,
        bool $active,
        bool $published,
    ): GalleryItem {
        return new GalleryItem(
            $id,
            $categoryId,
            $title,
            $caption,
            $filePath,
            $altText,
            $sortOrder,
            $active,
            $published,
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 10:30:00 UTC'),
        );
    }
}

final class AdministratorGalleryAuditFeatureFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];

    public function append(AuditLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class AdministratorGalleryTransactionFeatureFake implements GalleryTransaction
{
    private bool $active = false;

    public function begin(): void
    {
        $this->active = true;
    }

    public function commit(): void
    {
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}

final class AdministratorGalleryStorageFeatureFake implements GalleryImageStorage
{
    public int $stores = 0;

    /** @var list<string> */
    public array $removed = [];

    public function store(GalleryImageUploadRequest $request): StoredGalleryImage
    {
        $this->stores++;

        return new StoredGalleryImage(
            'gallery/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png',
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png',
            'png',
            'image/png',
            68,
            1,
            1,
        );
    }

    public function remove(string $relativePath): void
    {
        $this->removed[] = $relativePath;
    }
}

final class AdministratorGalleryAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
