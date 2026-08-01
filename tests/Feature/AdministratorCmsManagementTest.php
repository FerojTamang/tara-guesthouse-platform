<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorCmsController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Models\AuditLogEntry;
use App\Models\CmsPage;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PageRepository;
use App\Repositories\SettingRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorCmsService;
use App\Services\AdministratorLogoutService;
use App\Services\CmsManagementServiceProvider;
use App\Services\CmsPageService;
use App\Services\CmsSettingsService;
use App\Services\CmsTransaction;
use App\Services\PlainTextCmsPageContentSanitizer;
use App\Services\SettingDefinitionRegistry;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorCmsManagementTest extends TestCase
{
    private string $savePath;
    /** @var array<string, mixed> */
    private array $sessionConfiguration;
    private Session $session;
    private AdministratorSession $administratorSession;
    private CsrfTokenManager $csrfTokens;
    private Application $application;
    private AdministratorCmsSettingRepositoryFeatureFake $settings;
    private AdministratorCmsPageRepositoryFeatureFake $pages;
    private AdministratorCmsAuditFeatureFake $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-admin-cms-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->settings = new AdministratorCmsSettingRepositoryFeatureFake();
        $this->pages = new AdministratorCmsPageRepositoryFeatureFake();
        $this->audit = new AdministratorCmsAuditFeatureFake();
        $renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $logger = new NullLogger();
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new AdministratorCmsAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $registry = new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
            new SettingDefinition('homepage.rooms.limit', 'integer', false, 3),
        ]);
        $cmsController = new AdministratorCmsController(
            $renderer,
            new CmsManagementServiceProvider(fn (): AdministratorCmsService => new AdministratorCmsService(
                new CmsSettingsService($this->settings, $registry),
                new CmsPageService($this->pages),
                new PlainTextCmsPageContentSanitizer(5000),
                $this->audit,
                new AdministratorCmsTransactionFeatureFake(),
                static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-31 05:00:00 UTC'),
            )),
            $this->administratorSession,
            $this->csrfTokens,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $guestOnly = new GuestOnlyMiddleware($this->administratorSession);
        $requireAdministrator = new RequireAdministratorMiddleware($this->administratorSession);
        $csrf = new CsrfMiddleware($this->csrfTokens);
        $router = new Router();
        $adminRegistrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $adminRegistrar(
            router: $router,
            viewRenderer: $renderer,
            controller: $authenticationController,
            guestOnly: $guestOnly,
            requireAdministrator: $requireAdministrator,
            csrf: $csrf,
            cmsController: $cmsController,
        );
        $apiRegistrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $apiRegistrar($router, $renderer, $authenticationController, $guestOnly, $requireAdministrator, $csrf, $cmsController);
        $this->application = new Application($router, new ErrorHandler($logger, false), $this->session);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }
        foreach (glob($this->savePath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) unlink($file);
        }
        if (is_dir($this->savePath)) rmdir($this->savePath);
        parent::tearDown();
    }

    public function testGuestsCannotReadCmsOrMutateBeforeCsrf(): void
    {
        foreach (['/admin/settings', '/admin/pages', '/admin/pages/create'] as $path) {
            $response = $this->handle(Request::create('GET', $path));
            self::assertSame(302, $response->statusCode());
            self::assertSame('/admin/login', $response->headers()['Location']);
        }

        $read = $this->handle(Request::create('GET', '/api/v1/admin/pages', headers: ['Accept' => 'application/json']));
        self::assertSame(401, $read->statusCode());
        $mutation = $this->handle(Request::create(
            'POST',
            '/api/v1/admin/pages',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($this->pagePayload(), JSON_THROW_ON_ERROR),
        ));
        self::assertSame(401, $mutation->statusCode());
    }

    public function testProtectedPagesEscapeContentAndExposeOnlyApprovedControls(): void
    {
        $this->pages->pages[7] = $this->page(7, '<script>About</script>', 'about', CmsPage::DRAFT);
        $this->authenticate();

        $index = $this->handle(Request::create('GET', '/admin/pages'));
        self::assertSame(200, $index->statusCode());
        self::assertStringContainsString('&lt;script&gt;About&lt;/script&gt;', $index->body());
        self::assertStringNotContainsString('<script>About</script>', $index->body());
        self::assertStringContainsString('/api/v1/admin/pages/7/status', $index->body());
        self::assertStringContainsString('href="/admin/settings"', $index->body());

        $form = $this->handle(Request::create('GET', '/admin/pages/7/edit'));
        self::assertSame(200, $form->statusCode());
        self::assertStringContainsString('data-api-method="PUT"', $form->body());
        self::assertStringContainsString('Content format: plain_text', $form->body());
        self::assertStringNotContainsString('name="status"', $form->body());

        $settings = $this->handle(Request::create('GET', '/admin/settings'));
        self::assertSame(200, $settings->statusCode());
        self::assertStringContainsString('data-setting-key="contact.email"', $settings->body());
        self::assertStringContainsString('Public', $settings->body());
        self::assertStringContainsString('Private', $settings->body());
    }

    public function testExactSettingsAndPageApisPersistActorAndAuditEvidence(): void
    {
        $this->authenticate();

        $settings = $this->jsonRequest('PUT', '/api/v1/admin/settings', [
            'settings' => [
                'contact.email' => 'Stay@Example.Test',
                'homepage.rooms.limit' => '4',
            ],
        ]);
        self::assertSame(200, $settings->statusCode());
        self::assertSame('stay@example.test', $this->settings->settings['contact.email']->value());

        $created = $this->jsonRequest('POST', '/api/v1/admin/pages', $this->pagePayload());
        self::assertSame(201, $created->statusCode());
        $body = $this->decoded($created);
        $id = $body['data']['id'];
        self::assertIsInt($id);
        self::assertSame(CmsPage::DRAFT, $body['data']['status']);

        $published = $this->jsonRequest('PATCH', '/api/v1/admin/pages/' . $id . '/status', ['status' => 'published']);
        self::assertSame(200, $published->statusCode());
        self::assertSame(CmsPage::PUBLISHED, $this->pages->pages[$id]->status());
        self::assertSame(91, $this->pages->pages[$id]->updatedBy());
        self::assertSame([
            'cms_setting_updated',
            'cms_setting_updated',
            'cms_page_created',
            'cms_page_status_changed',
        ], array_map(static fn (AuditLogEntry $entry): string => $entry->eventType(), $this->audit->entries));
    }

    public function testUnknownFieldsHtmlUnknownKeysAndMissingPagesFailSafely(): void
    {
        $this->authenticate();

        $extra = $this->pagePayload();
        $extra['status'] = 'published';
        self::assertSame(422, $this->jsonRequest('POST', '/api/v1/admin/pages', $extra)->statusCode());

        $html = $this->pagePayload();
        $html['content'] = '<p>Unsafe</p>';
        self::assertSame(422, $this->jsonRequest('POST', '/api/v1/admin/pages', $html)->statusCode());

        self::assertSame(422, $this->jsonRequest('PUT', '/api/v1/admin/settings', [
            'settings' => ['unknown.key' => 'value'],
        ])->statusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/v1/admin/pages/999', [])->statusCode());
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    private function handle(Request $request): Response
    {
        $response = $this->application->handle($request);
        $this->reopenSession();
        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function jsonRequest(string $method, string $path, array $payload): Response
    {
        $request = Request::create(
            $method,
            $path,
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfTokens->token(),
                'User-Agent' => 'CMS Feature Test',
            ],
            body: json_encode($payload === [] ? (object) [] : $payload, JSON_THROW_ON_ERROR),
            remoteAddress: '127.0.0.1',
        );
        return $this->handle($request);
    }

    /** @return array<string, mixed> */
    private function decoded(Response $response): array
    {
        $decoded = json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        return $decoded;
    }

    /** @return array<string, mixed> */
    private function pagePayload(): array
    {
        return [
            'title' => 'About Tara',
            'slug' => 'about-tara',
            'content' => 'Welcome to Tara Guest House.',
            'meta_title' => 'About Tara',
            'meta_description' => 'About Tara Guest House.',
        ];
    }

    private function page(int $id, string $title, string $slug, string $status): CmsPage
    {
        $published = $status === CmsPage::PUBLISHED ? new DateTimeImmutable('2026-07-31 05:00:00 UTC') : null;
        return new CmsPage($id, $title, $slug, 'Safe content', null, null, $status, $published, 91, 91, new DateTimeImmutable('2026-07-31 04:00:00 UTC'), new DateTimeImmutable('2026-07-31 05:00:00 UTC'));
    }

    private function reopenSession(): void
    {
        $this->session = Session::start($this->sessionConfiguration);
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
            'cookie' => ['lifetime' => 0, 'secure' => false, 'http_only' => true, 'same_site' => 'Lax', 'path' => '/', 'domain' => null],
            'strict_mode' => true,
            'use_cookies' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'save_handler' => 'files',
            'save_path' => realpath($this->savePath),
            'gc_maxlifetime' => 43200,
        ];
    }
}

final class AdministratorCmsAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(AdministratorLoginRequest $request, string $remoteAddress, string $context = 'http'): AdministratorIdentity
    {
        return new AdministratorIdentity(91);
    }
}

final class AdministratorCmsSettingRepositoryFeatureFake implements SettingRepository
{
    /** @var array<string, SiteSetting> */ public array $settings = [];
    private int $nextId = 1;
    public function findByKey(string $key): ?SiteSetting { return $this->settings[$key] ?? null; }
    public function findAll(): array { return array_values($this->settings); }
    public function findAllPublic(): array { return array_values(array_filter($this->settings, static fn (SiteSetting $s): bool => $s->isPublic())); }
    public function upsert(string $key, string $value, string $valueType, bool $public, int $actorId): SiteSetting
    {
        $current = $this->settings[$key] ?? null;
        $now = new DateTimeImmutable('2026-07-31 05:00:00 UTC');
        return $this->settings[$key] = new SiteSetting($current?->id() ?? $this->nextId++, $key, $value, $valueType, $public, $actorId, $current?->createdAt() ?? $now, $now);
    }
}

final class AdministratorCmsPageRepositoryFeatureFake implements PageRepository
{
    /** @var array<int, CmsPage> */ public array $pages = [];
    private int $nextId = 1;
    public function findById(int $id): ?CmsPage { return $this->pages[$id] ?? null; }
    public function findBySlug(string $slug): ?CmsPage { foreach ($this->pages as $page) if ($page->slug() === $slug) return $page; return null; }
    public function findPublishedBySlug(string $slug): ?CmsPage { $page = $this->findBySlug($slug); return $page?->isPublished() ? $page : null; }
    public function findAll(): array { return array_values($this->pages); }
    public function findAllPublished(): array { return array_values(array_filter($this->pages, static fn (CmsPage $page): bool => $page->isPublished())); }
    public function createDraft(string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): CmsPage
    {
        $id = $this->nextId++;
        while (isset($this->pages[$id])) $id = $this->nextId++;
        $now = new DateTimeImmutable('2026-07-31 05:00:00 UTC');
        return $this->pages[$id] = new CmsPage($id, $title, $slug, $content, $metaTitle, $metaDescription, CmsPage::DRAFT, null, $actorId, $actorId, $now, $now);
    }
    public function updateContent(int $id, string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): ?CmsPage
    {
        $current = $this->pages[$id] ?? null;
        if (!$current instanceof CmsPage) return null;
        return $this->pages[$id] = new CmsPage($id, $title, $slug, $content, $metaTitle, $metaDescription, $current->status(), $current->publishedAt(), $current->createdBy(), $actorId, $current->createdAt(), new DateTimeImmutable('2026-07-31 05:00:00 UTC'));
    }
    public function transitionStatus(int $id, string $expectedStatus, string $newStatus, ?DateTimeImmutable $publishedAt, int $actorId): ?CmsPage
    {
        $current = $this->pages[$id] ?? null;
        if (!$current instanceof CmsPage || $current->status() !== $expectedStatus) return null;
        return $this->pages[$id] = new CmsPage($id, $current->title(), $current->slug(), $current->content(), $current->metaTitle(), $current->metaDescription(), $newStatus, $publishedAt, $current->createdBy(), $actorId, $current->createdAt(), new DateTimeImmutable('2026-07-31 05:00:00 UTC'));
    }
}

final class AdministratorCmsAuditFeatureFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */ public array $entries = [];
    public function append(AuditLogEntry $entry): void { $this->entries[] = $entry; }
}

final class AdministratorCmsTransactionFeatureFake implements CmsTransaction
{
    private bool $active = false;
    public function begin(): void { $this->active = true; }
    public function commit(): void { $this->active = false; }
    public function rollBack(): void { $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}
