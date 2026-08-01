<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorActivityController;
use App\Controllers\AdministratorAuthenticationController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Models\Activity;
use App\Models\AuditLogEntry;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\ActivityManagementServiceProvider;
use App\Services\ActivityTransaction;
use App\Services\AdministratorActivityService;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorActivityManagementTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;
    private AdministratorSession $administratorSession;
    private CsrfTokenManager $csrfTokens;
    private Application $application;
    private AdministratorActivityRepositoryFeatureFake $repository;
    private AdministratorActivityAuditFeatureFake $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-activity-management-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession(
            $this->session,
            $this->csrfTokens,
        );
        $this->repository = new AdministratorActivityRepositoryFeatureFake();
        $this->audit = new AdministratorActivityAuditFeatureFake();
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
            new AdministratorActivityAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $activityController = new AdministratorActivityController(
            $renderer,
            new ActivityManagementServiceProvider(
                fn (): AdministratorActivityService => new AdministratorActivityService(
                    $this->repository,
                    $this->audit,
                    new AdministratorActivityTransactionFeatureFake(),
                    static fn (): DateTimeImmutable => new DateTimeImmutable(
                        '2026-07-30 09:00:00 UTC',
                    ),
                ),
            ),
            $this->administratorSession,
            $this->csrfTokens,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $guestOnly = new GuestOnlyMiddleware($this->administratorSession);
        $requireAdministrator = new RequireAdministratorMiddleware(
            $this->administratorSession,
        );
        $csrf = new CsrfMiddleware($this->csrfTokens);
        $router = new Router();
        $adminRegistrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'admin.php';
        $adminRegistrar(
            $router,
            $renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $activityController,
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
            $activityController,
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

        foreach (glob($this->savePath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->savePath)) {
            rmdir($this->savePath);
        }

        parent::tearDown();
    }

    public function testGuestCannotReadPagesOrJsonAndAuthenticationRunsBeforeCsrf(): void
    {
        foreach (['/admin/activities', '/admin/activities/create'] as $path) {
            $response = $this->application->handle(Request::create('GET', $path));
            self::assertSame(302, $response->statusCode());
            self::assertSame('/admin/login', $response->headers()['Location']);
            $this->reopenSession();
        }

        $read = $this->application->handle(Request::create(
            'GET',
            '/api/v1/admin/activities',
            headers: ['Accept' => 'application/json'],
        ));
        self::assertSame(401, $read->statusCode());
        $this->reopenSession();

        $mutation = $this->application->handle(Request::create(
            'POST',
            '/api/v1/admin/activities',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($this->createPayload(), JSON_THROW_ON_ERROR),
        ));
        self::assertSame(401, $mutation->statusCode());
        self::assertSame([], $this->repository->items);
    }

    public function testIndexEscapesContentAndRendersIndependentControls(): void
    {
        $this->repository->items[7] = $this->activity(
            title: '<script>Walk</script>',
            slug: 'safe-walk',
            active: true,
            published: false,
        );
        $this->authenticate();
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(
            Request::create('GET', '/admin/activities'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('&lt;script&gt;Walk&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Walk</script>', $response->body());
        self::assertStringContainsString('href="/admin/activities/7/edit"', $response->body());
        self::assertStringContainsString('action="/api/v1/admin/activities/7/active"', $response->body());
        self::assertStringContainsString('action="/api/v1/admin/activities/7/publication"', $response->body());
        self::assertStringContainsString('data-api-method="DELETE"', $response->body());
        self::assertStringContainsString('name="_csrf" value="' . $token . '"', $response->body());
        self::assertStringContainsString('/assets/js/admin-activities.js', $response->body());
        self::assertSame(1, substr_count($response->body(), 'aria-current="page"'));
    }

    public function testCreateAndEditFormsExposeOnlyApprovedFields(): void
    {
        $this->repository->items[7] = $this->activity(active: false, published: true);
        $this->authenticate();

        $create = $this->application->handle(
            Request::create('GET', '/admin/activities/create'),
        );
        self::assertSame(200, $create->statusCode());
        self::assertStringContainsString('action="/api/v1/admin/activities"', $create->body());
        self::assertStringContainsString('data-api-method="POST"', $create->body());
        self::assertStringContainsString('name="is_active"', $create->body());
        self::assertStringContainsString('name="is_published"', $create->body());
        $this->assertNoFutureFields($create->body());

        $this->reopenSession();
        $edit = $this->application->handle(
            Request::create('GET', '/admin/activities/7/edit'),
        );
        self::assertSame(200, $edit->statusCode());
        self::assertStringContainsString('action="/api/v1/admin/activities/7"', $edit->body());
        self::assertStringContainsString('data-api-method="PUT"', $edit->body());
        self::assertStringNotContainsString('name="is_active"', $edit->body());
        self::assertStringNotContainsString('name="is_published"', $edit->body());
        self::assertStringContainsString('Inactive', $edit->body());
        self::assertStringContainsString('Published', $edit->body());
        $this->assertNoFutureFields($edit->body());
    }

    public function testJsonCreateListAndDetailUseExactApprovedContract(): void
    {
        $this->authenticate();
        $create = $this->jsonRequest(
            'POST',
            '/api/v1/admin/activities',
            $this->createPayload(),
        );

        self::assertSame(201, $create->statusCode());
        $created = $this->decoded($create);
        self::assertTrue($created['success']);
        self::assertSame('NPR', $created['data']['currency']);
        self::assertSame('2500.00', $created['data']['price']);
        self::assertTrue($created['data']['is_active']);
        self::assertFalse($created['data']['is_published']);
        self::assertArrayNotHasKey('category_id', $created['data']);
        self::assertCount(1, $this->audit->entries);
        self::assertSame('activity_created', $this->audit->entries[0]->eventType());

        $id = $created['data']['id'];
        $this->reopenSession();
        $list = $this->application->handle(Request::create(
            'GET',
            '/api/v1/admin/activities',
            headers: ['Accept' => 'application/json'],
        ));
        self::assertSame(200, $list->statusCode());
        self::assertCount(1, $this->decoded($list)['data']);

        $this->reopenSession();
        $detail = $this->application->handle(Request::create(
            'GET',
            '/api/v1/admin/activities/' . $id,
            headers: ['Accept' => 'application/json'],
        ));
        self::assertSame(200, $detail->statusCode());
        self::assertSame('jungle-walk', $this->decoded($detail)['data']['slug']);
    }

    public function testJsonCreateRejectsExtraFieldsAndInvalidTypesWithoutWriting(): void
    {
        $this->authenticate();
        $payload = $this->createPayload();
        $payload['featured'] = true;

        $extra = $this->jsonRequest('POST', '/api/v1/admin/activities', $payload);
        self::assertSame(422, $extra->statusCode());
        self::assertSame([], $this->repository->items);
        self::assertSame([], $this->audit->entries);

        $this->reopenSession();
        $payload = $this->createPayload();
        $payload['is_active'] = 1;
        $invalidState = $this->jsonRequest('POST', '/api/v1/admin/activities', $payload);
        self::assertSame(422, $invalidState->statusCode());
        self::assertStringContainsString(
            'must be true or false',
            $this->decoded($invalidState)['errors']['activity'][0],
        );
        self::assertSame([], $this->repository->items);
    }

    public function testUpdateAndStateEndpointsPreserveIndependentControls(): void
    {
        $this->repository->items[7] = $this->activity(active: false, published: true);
        $this->authenticate();
        $updatePayload = $this->createPayload();
        unset($updatePayload['is_active'], $updatePayload['is_published']);
        $updatePayload['title'] = 'Updated Walk';
        $updatePayload['slug'] = 'updated-walk';

        $updated = $this->jsonRequest(
            'PUT',
            '/api/v1/admin/activities/7',
            $updatePayload,
        );
        self::assertSame(200, $updated->statusCode());
        self::assertFalse($this->decoded($updated)['data']['is_active']);
        self::assertTrue($this->decoded($updated)['data']['is_published']);

        $this->reopenSession();
        $active = $this->jsonRequest(
            'PATCH',
            '/api/v1/admin/activities/7/active',
            ['is_active' => true],
        );
        self::assertSame(200, $active->statusCode());
        self::assertTrue($this->repository->items[7]->isActive());
        self::assertTrue($this->repository->items[7]->isPublished());

        $this->reopenSession();
        $publication = $this->jsonRequest(
            'PATCH',
            '/api/v1/admin/activities/7/publication',
            ['is_published' => false],
        );
        self::assertSame(200, $publication->statusCode());
        self::assertTrue($this->repository->items[7]->isActive());
        self::assertFalse($this->repository->items[7]->isPublished());
        self::assertSame(
            ['activity_updated', 'activity_active_changed', 'activity_publication_changed'],
            array_map(
                static fn (AuditLogEntry $entry): string => $entry->eventType(),
                $this->audit->entries,
            ),
        );
    }

    public function testDeleteRetainsRecordAndDeactivatesAndUnpublishes(): void
    {
        $this->repository->items[7] = $this->activity(active: true, published: true);
        $this->authenticate();

        $response = $this->jsonRequest(
            'DELETE',
            '/api/v1/admin/activities/7',
            [],
        );

        self::assertSame(200, $response->statusCode());
        self::assertArrayHasKey(7, $this->repository->items);
        self::assertFalse($this->repository->items[7]->isActive());
        self::assertFalse($this->repository->items[7]->isPublished());
        self::assertSame('activity_removed', $this->audit->entries[0]->eventType());
    }

    public function testJsonMutationsRequireCsrfAndJsonContentType(): void
    {
        $this->authenticate();
        $missingCsrf = $this->application->handle(Request::create(
            'POST',
            '/api/v1/admin/activities',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($this->createPayload(), JSON_THROW_ON_ERROR),
        ));
        self::assertSame(403, $missingCsrf->statusCode());
        self::assertSame([], $this->repository->items);

        $this->reopenSession();
        $wrongType = $this->application->handle(Request::create(
            'POST',
            '/api/v1/admin/activities',
            headers: [
                'Content-Type' => 'text/plain',
                'X-CSRF-Token' => $this->csrfTokens->token(),
            ],
            body: json_encode($this->createPayload(), JSON_THROW_ON_ERROR),
        ));
        self::assertSame(415, $wrongType->statusCode());
        self::assertSame([], $this->repository->items);
    }

    public function testMissingAndMalformedRequestsReturnSafeStatuses(): void
    {
        $this->authenticate();
        $missing = $this->application->handle(Request::create(
            'GET',
            '/api/v1/admin/activities/999',
            headers: ['Accept' => 'application/json'],
        ));
        self::assertSame(404, $missing->statusCode());

        $this->reopenSession();
        $malformed = $this->application->handle(Request::create(
            'POST',
            '/api/v1/admin/activities',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfTokens->token(),
            ],
            body: '{invalid',
        ));
        self::assertSame(400, $malformed->statusCode());
        self::assertStringNotContainsString('JsonException', $malformed->body());
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    private function reopenSession(): void
    {
        $this->session = Session::start($this->sessionConfiguration);
    }

    /** @param array<string, mixed> $payload */
    private function jsonRequest(string $method, string $path, array $payload): \App\Core\Response
    {
        return $this->application->handle(Request::create(
            $method,
            $path,
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->csrfTokens->token(),
                'User-Agent' => 'Feature Test',
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

    /** @return array<string, mixed> */
    private function createPayload(): array
    {
        return [
            'title' => 'Jungle Walk',
            'slug' => 'jungle-walk',
            'description' => 'Explore the surrounding forest.',
            'price' => '2500.00',
            'is_active' => true,
            'is_published' => false,
            'sort_order' => 10,
        ];
    }

    private function activity(
        int $id = 7,
        string $title = 'Jungle Walk',
        string $slug = 'jungle-walk',
        bool $active = true,
        bool $published = true,
    ): Activity {
        return new Activity(
            $id,
            $title,
            $slug,
            'Explore the surrounding forest.',
            '2500.00',
            $active,
            $published,
            10,
            new DateTimeImmutable('2026-07-30 07:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
        );
    }

    private function assertNoFutureFields(string $body): void
    {
        foreach ([
            'category_id',
            'image',
            'featured',
            'duration',
            'meeting_point',
            'maximum_participants',
        ] as $field) {
            self::assertStringNotContainsString('name="' . $field . '"', $body);
        }
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
}

final class AdministratorActivityRepositoryFeatureFake implements ActivityCatalogRepository
{
    /** @var array<int, Activity> */
    public array $items = [];

    public function findById(int $id): ?Activity
    {
        return $this->items[$id] ?? null;
    }

    public function lockById(int $id): ?Activity
    {
        return $this->items[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Activity
    {
        foreach ($this->items as $item) {
            if ($item->slug() === $slug) {
                return $item;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findAllPublic(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Activity $activity): bool => $activity->isPubliclyVisible(),
        ));
    }

    public function create(
        string $title,
        string $slug,
        string $description,
        string $price,
        bool $active,
        bool $published,
        int $sortOrder,
    ): Activity {
        foreach ($this->items as $item) {
            if ($item->slug() === $slug) {
                throw \App\Exceptions\ActivityException::duplicateSlug();
            }
        }

        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;

        return $this->items[$id] = $this->newActivity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $active,
            $published,
            $sortOrder,
        );
    }

    public function update(
        int $id,
        string $title,
        string $slug,
        string $description,
        string $price,
        int $sortOrder,
    ): ?Activity {
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $current->isActive(),
            $current->isPublished(),
            $sortOrder,
        );
    }

    public function setActive(int $id, bool $active): ?Activity
    {
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $current->title(),
            $current->slug(),
            $current->description(),
            $current->price(),
            $active,
            $current->isPublished(),
            $current->sortOrder(),
        );
    }

    public function setPublished(int $id, bool $published): ?Activity
    {
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $current->title(),
            $current->slug(),
            $current->description(),
            $current->price(),
            $current->isActive(),
            $published,
            $current->sortOrder(),
        );
    }

    public function deactivateAndUnpublish(int $id): ?Activity
    {
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $current->title(),
            $current->slug(),
            $current->description(),
            $current->price(),
            false,
            false,
            $current->sortOrder(),
        );
    }

    private function newActivity(
        int $id,
        string $title,
        string $slug,
        string $description,
        string $price,
        bool $active,
        bool $published,
        int $sortOrder,
    ): Activity {
        return new Activity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $active,
            $published,
            $sortOrder,
            new DateTimeImmutable('2026-07-30 07:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 09:00:00 UTC'),
        );
    }
}

final class AdministratorActivityAuditFeatureFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];

    public function append(AuditLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class AdministratorActivityTransactionFeatureFake implements ActivityTransaction
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

final class AdministratorActivityAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
