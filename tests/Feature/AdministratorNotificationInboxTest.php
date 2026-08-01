<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorNotificationController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Models\Notification;
use App\Repositories\NotificationRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\AdministratorNotificationService;
use App\Services\AdministratorNotificationServiceProvider;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorNotificationInboxTest extends TestCase
{
    private string $sessionPath;
    private Session $session;
    private CsrfTokenManager $tokens;
    private AdministratorSession $administratorSession;
    private Router $router;
    private FeatureNotificationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-admin-notifications-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->sessionPath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->tokens);
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $logger = new NullLogger();
        $authentication = new AdministratorAuthenticationController(
            $renderer,
            new FeatureNotificationAuthenticator(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->tokens,
            $logger,
            self::loginPolicy(),
        );
        $this->repository = new FeatureNotificationRepository();
        $this->repository->items[11] = self::notification(11, 91, '<script>Review waiting</script>', '<img src=x onerror=alert(1)>');
        $this->repository->items[12] = self::notification(12, 91, 'Payment waiting', 'A payment attempt needs review.', new DateTimeImmutable('2026-08-01 06:45:00 UTC'));
        $this->repository->items[13] = self::notification(13, 92, 'Private notification', 'Another Administrator owns this row.');
        $service = new AdministratorNotificationService(
            $this->repository,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-01 07:00:00 UTC'),
        );
        $controller = new AdministratorNotificationController(
            $renderer,
            new AdministratorNotificationServiceProvider(static fn (): AdministratorNotificationService => $service),
            $this->administratorSession,
            $this->tokens,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $guestOnly = new GuestOnlyMiddleware($this->administratorSession);
        $requireAdministrator = new RequireAdministratorMiddleware($this->administratorSession);
        $csrf = new CsrfMiddleware($this->tokens);
        $this->router = new Router();

        $admin = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $admin(
            $this->router,
            $renderer,
            $authentication,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            notificationController: $controller,
        );

        $api = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $api($this->router, $renderer, $authentication, $guestOnly, $requireAdministrator, $csrf, $controller);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }
        foreach (glob($this->sessionPath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($this->sessionPath);
        parent::tearDown();
    }

    public function testGuestHasNoNotificationInboxOrAdministratorApiAccess(): void
    {
        $page = $this->router->dispatch(Request::create('GET', '/admin/notifications'));
        self::assertSame(302, $page->statusCode());
        self::assertSame('/admin/login', $page->headers()['Location']);
        self::assertSame(401, $this->router->dispatch(
            Request::create('GET', '/api/v1/admin/notifications'),
        )->statusCode());
        self::assertSame(401, $this->router->dispatch(
            Request::create('PATCH', '/api/v1/admin/notifications/11/read'),
        )->statusCode());
        self::assertSame(404, $this->router->dispatch(
            Request::create('GET', '/api/v1/notifications'),
        )->statusCode());
    }

    public function testAuthenticatedInboxEscapesContentAndIntegratesHeaderAndSidebar(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $response = $this->router->dispatch(Request::create('GET', '/admin/notifications'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('&lt;script&gt;Review waiting&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Review waiting</script>', $response->body());
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $response->body());
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $response->body());
        self::assertStringContainsString('href="/admin/notifications"', $response->body());
        self::assertStringContainsString('data-notification-unread-count', $response->body());
        self::assertStringContainsString('data-notification-link', $response->body());
        self::assertStringContainsString('/assets/js/admin-notifications.js', $response->body());
        self::assertStringContainsString('/api/v1/admin/notifications/11/read', $response->body());
        self::assertStringNotContainsString('Private notification', $response->body());
        self::assertStringNotContainsString('/api/v1/admin/notifications/11/delete', $response->body());
        self::assertStringNotContainsString('/api/v1/admin/notifications/11/archive', $response->body());
    }

    public function testListApiReturnsOnlyCurrentAdministratorAndPaginationMetadata(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $response = $this->router->dispatch(Request::create('GET', '/api/v1/admin/notifications?page=1'));
        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        $body = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR);

        self::assertTrue($body['success']);
        self::assertCount(2, $body['data']);
        self::assertSame(1, $body['meta']['unread_count']);
        self::assertSame(20, $body['meta']['per_page']);
        self::assertArrayNotHasKey('user_id', $body['data'][0]);
        self::assertNotContains('Private notification', array_column($body['data'], 'title'));

        foreach (['0', '-1', '1.5', 'abc'] as $page) {
            self::assertSame(422, $this->router->dispatch(
                Request::create('GET', '/api/v1/admin/notifications?page=' . rawurlencode($page)),
            )->statusCode());
        }
        self::assertSame(422, $this->router->dispatch(
            Request::create('GET', '/api/v1/admin/notifications?status=unread'),
        )->statusCode());
    }

    public function testReadOneRequiresCsrfIsIdempotentAndCannotCrossOwnership(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));

        self::assertSame(403, $this->router->dispatch(
            Request::create('PATCH', '/api/v1/admin/notifications/11/read'),
        )->statusCode());
        self::assertFalse($this->repository->items[11]->isRead());

        $read = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/notifications/11/read',
            headers: ['X-CSRF-Token' => $this->tokens->token()],
        ));
        self::assertSame(200, $read->statusCode());
        self::assertSame('no-store', $read->headers()['Cache-Control']);
        $body = json_decode($read->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertTrue($body['data']['is_read']);
        self::assertSame(0, $body['unread_count']);
        self::assertTrue($this->repository->items[11]->isRead());

        self::assertSame(200, $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/notifications/11/read',
            headers: ['X-CSRF-Token' => $this->tokens->token()],
        ))->statusCode());
        self::assertSame(404, $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/notifications/13/read',
            headers: ['X-CSRF-Token' => $this->tokens->token()],
        ))->statusCode());
        self::assertFalse($this->repository->items[13]->isRead());
    }

    public function testReadAllChangesOnlyCurrentAdministratorAndNoDeleteArchiveRoutesExist(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        self::assertSame(403, $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/notifications/read-all',
        ))->statusCode());
        self::assertFalse($this->repository->items[11]->isRead());

        $response = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/notifications/read-all',
            headers: ['X-CSRF-Token' => $this->tokens->token()],
        ));
        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        $body = json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame(1, $body['data']['updated_count']);
        self::assertSame(0, $body['data']['unread_count']);
        self::assertTrue($this->repository->items[11]->isRead());
        self::assertFalse($this->repository->items[13]->isRead());

        self::assertSame(404, $this->router->dispatch(
            Request::create('DELETE', '/api/v1/admin/notifications/11'),
        )->statusCode());
        self::assertSame(404, $this->router->dispatch(
            Request::create('PATCH', '/api/v1/admin/notifications/11/archive'),
        )->statusCode());
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
    private function sessionConfiguration(): array
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
            'save_path' => realpath($this->sessionPath),
            'gc_maxlifetime' => 43200,
        ];
    }

    private static function notification(
        int $id,
        int $userId,
        string $title,
        string $message,
        ?DateTimeImmutable $readAt = null,
    ): Notification {
        return new Notification(
            $id,
            $userId,
            'review_submitted',
            $title,
            $message,
            'review',
            11,
            $readAt,
            new DateTimeImmutable('2026-08-01 06:30:00 UTC'),
        );
    }
}

final class FeatureNotificationRepository implements NotificationRepository
{
    /** @var array<int, Notification> */
    public array $items = [];

    public function create(int $userId, string $type, string $title, string $message, ?string $relatedType, ?int $relatedId, DateTimeImmutable $createdAt): Notification
    {
        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;
        return $this->items[$id] = new Notification($id, $userId, $type, $title, $message, $relatedType, $relatedId, null, $createdAt);
    }

    public function findPageForUser(int $userId, int $limit, int $offset): array
    {
        $items = array_values(array_filter($this->items, static fn (Notification $item): bool => $item->userId() === $userId));
        usort($items, static fn (Notification $left, Notification $right): int => $right->id() <=> $left->id());
        return array_slice($items, $offset, $limit);
    }

    public function countForUser(int $userId): int
    {
        return count(array_filter($this->items, static fn (Notification $item): bool => $item->userId() === $userId));
    }

    public function countUnreadForUser(int $userId): int
    {
        return count(array_filter($this->items, static fn (Notification $item): bool => $item->userId() === $userId && !$item->isRead()));
    }

    public function findByIdForUser(int $id, int $userId): ?Notification
    {
        $item = $this->items[$id] ?? null;
        return $item instanceof Notification && $item->userId() === $userId ? $item : null;
    }

    public function markReadForUser(int $id, int $userId, DateTimeImmutable $readAt): ?Notification
    {
        $item = $this->findByIdForUser($id, $userId);
        if (!$item instanceof Notification) return null;
        if ($item->isRead()) return $item;
        return $this->items[$id] = new Notification($item->id(), $item->userId(), $item->type(), $item->title(), $item->message(), $item->relatedType(), $item->relatedId(), $readAt, $item->createdAt());
    }

    public function markAllReadForUser(int $userId, DateTimeImmutable $readAt): int
    {
        $updated = 0;
        foreach ($this->items as $id => $item) {
            if ($item->userId() !== $userId || $item->isRead()) continue;
            $this->items[$id] = new Notification($item->id(), $item->userId(), $item->type(), $item->title(), $item->message(), $item->relatedType(), $item->relatedId(), $readAt, $item->createdAt());
            $updated++;
        }
        return $updated;
    }
}

final class FeatureNotificationAuthenticator implements AdministratorAuthenticator
{
    public function authenticate(AdministratorLoginRequest $request, string $remoteAddress, string $context = 'http'): AdministratorIdentity
    {
        return new AdministratorIdentity(91);
    }
}
