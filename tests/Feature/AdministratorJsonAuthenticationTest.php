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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorJsonAuthenticationTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;

    private CsrfTokenManager $csrfTokens;

    private AdministratorSession $administratorSession;

    private JsonAuthenticatorFake $authenticator;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-json-feature-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->authenticator = new JsonAuthenticatorFake();
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
        );
        $router = new Router();
        $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
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

    public function testLoginEndpointIsPostOnly(): void
    {
        $response = $this->application->handle(
            Request::create('GET', '/api/v1/admin/auth/login'),
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('POST', $response->headers()['Allow']);
    }

    public function testLoginRequiresCsrfBeforeAuthentication(): void
    {
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: ['Content-Type' => 'application/json'],
                body: self::loginJson(),
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(403, $response->statusCode());
        self::assertSame('{"message":"Forbidden."}', $response->body());
        self::assertSame(0, $this->authenticator->calls);
    }

    public function testLoginRejectsUnsupportedMediaType(): void
    {
        $token = $this->csrfTokens->token();
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'text/plain',
                    'X-CSRF-Token' => $token,
                ],
                body: self::loginJson(),
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(415, $response->statusCode());
        self::assertSame('{"message":"Content-Type must be application/json."}', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(0, $this->authenticator->calls);
    }

    public function testLoginRejectsMalformedJsonAndNonObjectPayloads(): void
    {
        $token = $this->csrfTokens->token();
        $malformed = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'X-CSRF-Token' => $token,
                ],
                body: '{',
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(400, $malformed->statusCode());
        self::assertSame('{"message":"Request body must contain valid JSON."}', $malformed->body());

        $this->session = Session::start($this->sessionConfiguration);
        $token = $this->csrfTokens->token();
        $listPayload = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => $token,
                ],
                body: '[]',
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(400, $listPayload->statusCode());
        self::assertSame('{"message":"Request body must contain a JSON object."}', $listPayload->body());
        self::assertSame(0, $this->authenticator->calls);
    }

    public function testLoginRejectsUnexpectedPayloadFields(): void
    {
        $token = $this->csrfTokens->token();
        $password = 'json-test-only-password';
        $body = json_encode([
            'identifier' => 'administrator',
            'password' => $password,
            'remember_me' => true,
        ], JSON_THROW_ON_ERROR);
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => $token,
                ],
                body: $body,
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(422, $response->statusCode());
        self::assertSame('{"message":"Request payload is invalid."}', $response->body());
        self::assertStringNotContainsString($password, $response->body());
        self::assertSame(0, $this->authenticator->calls);
    }

    public function testInvalidCredentialsReturnOneGenericJsonFailure(): void
    {
        $token = $this->csrfTokens->token();
        $password = 'json-test-only-secret';
        $this->authenticator->failure = AdministratorAuthenticationException::invalidCredentials();
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => $token,
                ],
                body: self::loginJson('ADMINISTRATOR', $password),
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(401, $response->statusCode());
        self::assertSame('{"message":"The supplied credentials are invalid."}', $response->body());
        self::assertStringNotContainsString($password, $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testRateLimitedLoginReturnsRetryAfter(): void
    {
        $token = $this->csrfTokens->token();
        $this->authenticator->failure = AdministratorAuthenticationException::rateLimited(222);
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => $token,
                ],
                body: self::loginJson(),
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(429, $response->statusCode());
        self::assertSame('222', $response->headers()['Retry-After']);
        self::assertSame(
            '{"message":"Too many login attempts. Please try again later."}',
            $response->body(),
        );
    }

    public function testSuccessfulLoginCreatesTheSessionAndReturnsProfileAndRotatedCsrfHeader(): void
    {
        $originalSessionId = session_id();
        $originalToken = $this->csrfTokens->token();
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'X-CSRF-Token' => $originalToken,
                ],
                body: self::loginJson('ADMINISTRATOR'),
                remoteAddress: '2001:db8::44',
            ),
        );
        $authenticatedSessionId = session_id();

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            '{"administrator":{"id":91,"role":"Administrator"}}',
            $response->body(),
        );
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertArrayHasKey('X-CSRF-Token', $response->headers());
        self::assertNotSame($originalToken, $response->headers()['X-CSRF-Token']);
        self::assertArrayNotHasKey('Authorization', $response->headers());
        self::assertNotSame($originalSessionId, $authenticatedSessionId);
        self::assertSame('administrator', $this->authenticator->lastRequest?->identifier());
        self::assertSame('2001:db8::44', $this->authenticator->lastRemoteAddress);
        self::assertSame('api', $this->authenticator->lastContext);

        $this->session = Session::start($this->sessionConfiguration);
        self::assertSame(91, $this->administratorSession->identity()?->userId());
        self::assertSame($response->headers()['X-CSRF-Token'], $this->csrfTokens->token());
    }

    public function testAuthenticatedAdministratorCannotSubmitLoginAgain(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $token = $this->csrfTokens->token();
        $response = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/login',
                headers: [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => $token,
                ],
                body: self::loginJson(),
                remoteAddress: '127.0.0.1',
            ),
        );

        self::assertSame(409, $response->statusCode());
        self::assertSame('{"message":"Already authenticated."}', $response->body());
        self::assertSame(0, $this->authenticator->calls);
    }

    public function testLogoutChecksAuthenticationBeforeCsrf(): void
    {
        $response = $this->application->handle(
            Request::create('POST', '/api/v1/admin/auth/logout'),
        );

        self::assertSame(401, $response->statusCode());
        self::assertSame('{"message":"Authentication required."}', $response->body());
    }

    public function testAuthenticatedLogoutRejectsMissingCsrfWithoutDestroyingIdentity(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $response = $this->application->handle(
            Request::create('POST', '/api/v1/admin/auth/logout'),
        );

        self::assertSame(403, $response->statusCode());
        self::assertSame('{"message":"Forbidden."}', $response->body());

        $this->session = Session::start($this->sessionConfiguration);
        self::assertSame(91, $this->administratorSession->identity()?->userId());
    }

    public function testLogoutIsPostOnlyAndValidCsrfDestroysTheSession(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $token = $this->csrfTokens->token();
        $getResponse = $this->application->handle(
            Request::create('GET', '/api/v1/admin/auth/logout'),
        );

        self::assertSame(405, $getResponse->statusCode());
        self::assertSame('POST', $getResponse->headers()['Allow']);

        $this->session = Session::start($this->sessionConfiguration);
        $postResponse = $this->application->handle(
            Request::create(
                'POST',
                '/api/v1/admin/auth/logout',
                headers: ['X-CSRF-Token' => $token],
            ),
        );

        self::assertSame(200, $postResponse->statusCode());
        self::assertSame('{"message":"Logged out."}', $postResponse->body());
        self::assertSame('no-store', $postResponse->headers()['Cache-Control']);
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
    }

    private static function loginJson(
        string $identifier = 'administrator',
        string $password = 'json-test-only-password',
    ): string {
        return json_encode([
            'identifier' => $identifier,
            'password' => $password,
        ], JSON_THROW_ON_ERROR);
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

final class JsonAuthenticatorFake implements AdministratorAuthenticator
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
