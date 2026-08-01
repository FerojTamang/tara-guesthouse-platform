<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorContactController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Models\AuditLogEntry;
use App\Models\ContactMessage;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\ContactMessageService;
use App\Services\ContactMessageServiceProvider;
use App\Services\ContactTransaction;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorContactReadTest extends TestCase
{
    private string $sessionPath;
    private Session $session;
    private AdministratorSession $administratorSession;
    private Router $router;
    private AdministratorContactRepositoryFake $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-admin-contact-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->sessionPath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
        $tokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $tokens);
        $renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $logger = new NullLogger();
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new AdministratorContactAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $tokens,
            $logger,
            self::loginPolicy(),
        );
        $this->repository = new AdministratorContactRepositoryFake();
        $service = new ContactMessageService(
            $this->repository,
            new AdministratorContactAuditFake(),
            new AdministratorContactTransactionFake(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
        );
        $contactController = new AdministratorContactController(
            $renderer,
            new ContactMessageServiceProvider(static fn (): ContactMessageService => $service),
            $tokens,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $guestOnly = new GuestOnlyMiddleware($this->administratorSession);
        $requireAdministrator = new RequireAdministratorMiddleware($this->administratorSession);
        $csrf = new CsrfMiddleware($tokens);
        $this->router = new Router();
        $admin = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $admin(
            $this->router,
            $renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            contactController: $contactController,
        );
        $api = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $api(
            $this->router,
            $renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            $contactController,
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
        foreach (glob($this->sessionPath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->sessionPath);
        parent::tearDown();
    }

    public function testGuestCannotReadContactMessages(): void
    {
        $page = $this->router->dispatch(Request::create('GET', '/admin/contact-messages'));
        self::assertSame(302, $page->statusCode());
        self::assertSame('/admin/login', $page->headers()['Location']);

        $api = $this->router->dispatch(Request::create('GET', '/api/v1/admin/contact-messages'));
        self::assertSame(401, $api->statusCode());
        self::assertStringContainsString('Authentication required.', $api->body());
    }

    public function testAuthenticatedListAndDetailEscapeGuestContentAndStayReadOnly(): void
    {
        $this->repository->messages[7] = self::contact();
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $list = $this->router->dispatch(Request::create('GET', '/admin/contact-messages?q=airport'));
        self::assertSame(200, $list->statusCode());
        self::assertSame('airport', $this->repository->lastSearch);
        self::assertStringContainsString('&lt;script&gt;Asha&lt;/script&gt;', $list->body());
        self::assertStringNotContainsString('<script>Asha</script>', $list->body());
        self::assertStringContainsString('Policy pending', $list->body());
        self::assertStringContainsString('href="/admin/contact-messages/7"', $list->body());

        $detail = $this->router->dispatch(Request::create('GET', '/admin/contact-messages/7'));
        self::assertSame(200, $detail->statusCode());
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $detail->body());
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $detail->body());
        self::assertStringContainsString('Status and assignment actions remain unavailable', $list->body());
        self::assertStringNotContainsString('action="/admin/contact-messages/7"', $detail->body());
        self::assertStringNotContainsString('/api/v1/admin/contact-messages/7/status', $detail->body());
        self::assertStringNotContainsString('name="status"', $detail->body());
    }

    public function testAdministratorApisExposeReadProjectionAndNoMutationRoute(): void
    {
        $this->repository->messages[7] = self::contact();
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(Request::create('GET', '/api/v1/admin/contact-messages/7'));
        self::assertSame(200, $response->statusCode());
        $decoded = json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame([
            'contact_message_id', 'name', 'email', 'phone', 'subject', 'message', 'status', 'created_at', 'updated_at',
        ], array_keys($decoded['data']));
        self::assertNull($decoded['data']['status']);

        $mutation = $this->router->dispatch(Request::create('PATCH', '/api/v1/admin/contact-messages/7/status'));
        self::assertSame(404, $mutation->statusCode());
    }

    public function testInvalidSearchAndIdentifierFailSafely(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $invalidSearch = $this->router->dispatch(Request::create(
            'GET',
            '/admin/contact-messages?q=' . str_repeat('x', 101),
        ));
        self::assertSame(422, $invalidSearch->statusCode());
        self::assertStringContainsString('Contact message search is invalid.', $invalidSearch->body());
        self::assertSame(404, $this->router->dispatch(
            Request::create('GET', '/admin/contact-messages/not-a-number'),
        )->statusCode());
    }

    private static function contact(): ContactMessage
    {
        return new ContactMessage(
            7,
            '<script>Asha</script>',
            'asha@example.test',
            null,
            'Airport transfer',
            '<img src=x onerror=alert(1)>\nPlease reply.',
            null,
            new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
            new DateTimeImmutable('2026-07-31 12:00:00 UTC'),
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
}

final class AdministratorContactRepositoryFake implements ContactMessageRepository
{
    /** @var array<int, ContactMessage> */
    public array $messages = [];
    public ?string $lastSearch = null;
    public function create(string $name, string $email, ?string $phone, string $subject, string $message, DateTimeImmutable $createdAt): ContactMessage
    {
        throw new \LogicException('Administrator contact workflow is read-only.');
    }
    public function findAll(?string $search = null): array
    {
        $this->lastSearch = $search;
        return array_values($this->messages);
    }
    public function findById(int $id): ?ContactMessage
    {
        return $this->messages[$id] ?? null;
    }
}

final class AdministratorContactAuditFake implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new \LogicException('Administrator contact reads do not audit mutations.');
    }
}

final class AdministratorContactTransactionFake implements ContactTransaction
{
    public function begin(): void { throw new \LogicException('Read-only.'); }
    public function commit(): void { throw new \LogicException('Read-only.'); }
    public function rollBack(): void { throw new \LogicException('Read-only.'); }
    public function inProgress(): bool { return false; }
}

final class AdministratorContactAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(AdministratorLoginRequest $request, string $remoteAddress, string $context = 'http'): AdministratorIdentity
    {
        return new AdministratorIdentity(91);
    }
}
