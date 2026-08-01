<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Exceptions\AdministratorAuthenticationException;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\AdministratorDashboardMetricsService;
use App\Services\AdministratorDashboardMetricsServiceProvider;
use App\Repositories\AdministratorDashboardMetricsRepository;
use App\Models\AdministratorDashboardMetrics;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorHtmlAuthenticationTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;

    private CsrfTokenManager $csrfTokens;

    private AdministratorSession $administratorSession;

    private HtmlAuthenticatorFake $authenticator;

    private DashboardMetricsRepositoryFake $dashboardMetricsRepository;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-html-feature-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->authenticator = new HtmlAuthenticatorFake();
        $this->dashboardMetricsRepository = new DashboardMetricsRepositoryFake();
        $logger = new NullLogger();
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $controller = new AdministratorAuthenticationController(
            $renderer,
            $this->authenticator,
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
            new AdministratorDashboardMetricsServiceProvider(
                fn (): AdministratorDashboardMetricsService => new AdministratorDashboardMetricsService($this->dashboardMetricsRepository),
            ),
        );
        $router = new Router();
        $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $registrar(
            $router,
            $renderer,
            $controller,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($this->csrfTokens),
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

    public function testLoginPageRendersAProtectedFormWithoutCachingOrPasswordDisclosure(): void
    {
        $response = $this->application->handle(Request::create('GET', '/admin/login'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('action="/admin/login"', $response->body());
        self::assertMatchesRegularExpression('/name="_csrf" value="[a-f0-9]{64}"/', $response->body());
        self::assertStringContainsString('name="identifier"', $response->body());
        self::assertStringContainsString('name="password"', $response->body());
        self::assertStringNotContainsString('aria-label="Primary navigation"', $response->body());
        self::assertStringNotContainsString('name="password" value=', $response->body());
        self::assertSame(0, $this->authenticator->calls);
    }

    public function testLoginRejectsMissingCsrfBeforeAuthentication(): void
    {
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/admin/login',
                input: [
                    'identifier' => 'administrator',
                    'password' => 'test-only-password',
                ],
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(403, $response->statusCode());
        self::assertSame(0, $this->authenticator->calls);
    }

    public function testInvalidCredentialsReturnOneGenericFailureWithoutEchoingThePassword(): void
    {
        $token = $this->csrfTokens->token();
        $this->authenticator->failure = AdministratorAuthenticationException::invalidCredentials();
        $password = 'test-only-secret-value';

        $response = $this->application->handle(
            Request::create(
                'POST',
                '/admin/login',
                input: [
                    '_csrf' => $token,
                    'identifier' => 'ADMINISTRATOR',
                    'password' => $password,
                ],
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('The supplied credentials are invalid.', $response->body());
        self::assertStringContainsString('value="administrator"', $response->body());
        self::assertStringNotContainsString($password, $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testRateLimitedLoginReturnsRetryAfterWithoutDisclosure(): void
    {
        $token = $this->csrfTokens->token();
        $this->authenticator->failure = AdministratorAuthenticationException::rateLimited(321);
        $password = 'test-only-rate-limited-password';

        $response = $this->application->handle(
            Request::create(
                'POST',
                '/admin/login',
                input: [
                    '_csrf' => $token,
                    'identifier' => 'administrator',
                    'password' => $password,
                ],
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(429, $response->statusCode());
        self::assertSame('321', $response->headers()['Retry-After']);
        self::assertStringContainsString('Too many login attempts.', $response->body());
        self::assertStringNotContainsString($password, $response->body());
    }

    public function testSuccessfulLoginRegeneratesTheSessionRotatesCsrfAndRedirects(): void
    {
        $originalSessionId = session_id();
        $originalToken = $this->csrfTokens->token();

        $response = $this->application->handle(
            Request::create(
                'POST',
                '/admin/login',
                input: [
                    '_csrf' => $originalToken,
                    'identifier' => 'ADMINISTRATOR',
                    'password' => 'test-only-password',
                ],
                remoteAddress: '2001:db8::20',
            ),
        );
        $authenticatedSessionId = session_id();

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/dashboard', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertNotSame($originalSessionId, $authenticatedSessionId);
        self::assertSame('administrator', $this->authenticator->lastRequest?->identifier());
        self::assertSame('2001:db8::20', $this->authenticator->lastRemoteAddress);
        self::assertSame('http', $this->authenticator->lastContext);

        $this->session = Session::start($this->sessionConfiguration);
        self::assertSame(91, $this->administratorSession->identity()?->userId());
        self::assertNotSame($originalToken, $this->csrfTokens->token());
    }

    public function testGuestCannotOpenTheDashboard(): void
    {
        $response = $this->application->handle(Request::create('GET', '/admin/dashboard'));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    public function testAuthenticatedDashboardRendersAPostOnlyLogoutForm(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('GET', '/admin/dashboard'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Dashboard | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('class="admin-shell"', $response->body());
        self::assertStringContainsString('aria-label="Administrator navigation"', $response->body());
        self::assertStringContainsString('href="/admin/dashboard"', $response->body());
        self::assertStringContainsString('aria-current="page"', $response->body());
        self::assertStringContainsString('<h1 id="administrator-dashboard-heading" class="h2 mb-2">Dashboard</h1>', $response->body());
        foreach (['Active rooms', 'Occupied rooms', 'Available rooms', 'Arrivals today', 'Departures today', 'Pending bookings', 'Confirmed bookings', 'Pending payment reviews', 'Reviews awaiting moderation', 'Unread notifications'] as $label) {
            self::assertStringContainsString($label, $response->body());
        }
        self::assertStringContainsString('method="post" action="/admin/logout"', $response->body());
        self::assertStringContainsString('value="' . $token . '"', $response->body());
        self::assertStringContainsString('/assets/css/admin.css', $response->body());
        self::assertStringContainsString('bootstrap@5.3.8/dist/css/bootstrap.min.css', $response->body());
        self::assertStringContainsString('bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js', $response->body());
        self::assertStringContainsString('integrity="sha384-', $response->body());
        self::assertStringNotContainsString('Revenue', $response->body());
        self::assertSame(1, substr_count($response->body(), 'action="/admin/logout"'));
    }

    public function testDashboardMetricsFailureUsesOnlyTheGenericNoStoreFailureResponse(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $this->dashboardMetricsRepository->failure = new \RuntimeException(
            'SQLSTATE[HY000] PDO SELECT passwords FROM users at C:\\secret\\database.php',
        );

        $response = $this->application->handle(Request::create('GET', '/admin/dashboard'));

        self::assertSame(500, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('Internal Server Error', $response->body());
        self::assertSame(1, $this->dashboardMetricsRepository->calls);
        self::assertSame(91, $this->dashboardMetricsRepository->administratorId);
        foreach (['SQLSTATE', 'PDO', 'SELECT', 'users', 'passwords', 'C:\\secret', 'RuntimeException'] as $sensitive) {
            self::assertStringNotContainsString($sensitive, $response->body());
        }
        $this->session = Session::start($this->sessionConfiguration);
        self::assertSame(91, $this->administratorSession->identity()?->userId());
    }

    public function testDashboardUsesAuthenticatedIdentityInsteadOfConflictingRequestInput(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $response = $this->application->handle(Request::create(
            'GET',
            '/admin/dashboard?administrator_id=92&user_id=92&id=92',
            input: ['administrator_id' => '92', 'user_id' => '92', 'id' => '92'],
        ));

        self::assertSame(200, $response->statusCode());
        self::assertSame(1, $this->dashboardMetricsRepository->calls);
        self::assertSame(91, $this->dashboardMetricsRepository->administratorId);
        self::assertStringContainsString('>9<', $response->body());
        self::assertStringNotContainsString('>92<', $response->body());
    }

    public function testLogoutRequiresPostAndValidCsrfThenDestroysTheSession(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $token = $this->csrfTokens->token();

        $getResponse = $this->application->handle(Request::create('GET', '/admin/logout'));

        self::assertSame(405, $getResponse->statusCode());
        self::assertSame('POST', $getResponse->headers()['Allow']);

        $this->session = Session::start($this->sessionConfiguration);
        $postResponse = $this->application->handle(
            Request::create('POST', '/admin/logout', input: ['_csrf' => $token]),
        );

        self::assertSame(303, $postResponse->statusCode());
        self::assertSame('/admin/login', $postResponse->headers()['Location']);
        self::assertSame('no-store', $postResponse->headers()['Cache-Control']);
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
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

final class DashboardMetricsRepositoryFake implements AdministratorDashboardMetricsRepository
{
    public int $calls = 0;
    public ?int $administratorId = null;
    public ?\Throwable $failure = null;

    public function metrics(int $administratorId, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): AdministratorDashboardMetrics
    {
        $this->calls++;
        $this->administratorId = $administratorId;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new AdministratorDashboardMetrics($rangeStart, $rangeEnd, 5, 2, 3, 4, 5, 6, 7, 8, 9);
    }
}

final class HtmlAuthenticatorFake implements AdministratorAuthenticator
{
    public int $calls = 0;

    public ?AdministratorAuthenticationException $failure = null;

    public ?AdministratorLoginRequest $lastRequest = null;

    public ?string $lastRemoteAddress = null;

    public ?string $lastContext = null;

    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        $this->calls++;
        $this->lastRequest = $request;
        $this->lastRemoteAddress = $remoteAddress;
        $this->lastContext = $context;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new AdministratorIdentity(91);
    }
}
