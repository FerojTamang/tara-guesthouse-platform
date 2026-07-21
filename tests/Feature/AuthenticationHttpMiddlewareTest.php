<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AuthenticationHttpMiddlewareTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private AdministratorSession $administratorSession;

    private CsrfTokenManager $csrfTokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-auth-http-feature-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession(
            $this->session,
            $this->csrfTokens,
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

    public function testGuestRequestToProtectedAdminPageRedirectsToLogin(): void
    {
        $router = new Router();
        $router->get(
            '/admin/dashboard',
            static fn (Request $request, array $parameters): Response => Response::html('Dashboard'),
            [new RequireAdministratorMiddleware($this->administratorSession)],
        );

        $response = $this->application($router)->handle(Request::create('GET', '/admin/dashboard'));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testGuestRequestToProtectedApiReceivesUnauthorizedJson(): void
    {
        $router = new Router();
        $router->get(
            '/api/v1/admin/profile',
            static fn (Request $request, array $parameters): Response => Response::json(['id' => 1]),
            [new RequireAdministratorMiddleware($this->administratorSession, true)],
        );

        $response = $this->application($router)->handle(Request::create('GET', '/api/v1/admin/profile'));

        self::assertSame(401, $response->statusCode());
        self::assertSame('{"message":"Authentication required."}', $response->body());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testAuthenticatedAdministratorReachesProtectedRoute(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(5));
        $router = new Router();
        $router->get(
            '/admin/dashboard',
            static fn (Request $request, array $parameters): Response => Response::html('Dashboard'),
            [new RequireAdministratorMiddleware($this->administratorSession)],
        );

        $response = $this->application($router)->handle(Request::create('GET', '/admin/dashboard'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('Dashboard', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testAnonymousGuestReachesGuestOnlyLoginPageWithoutCachingIt(): void
    {
        $router = new Router();
        $router->get(
            '/admin/login',
            static fn (Request $request, array $parameters): Response => Response::html('Login'),
            [new GuestOnlyMiddleware($this->administratorSession)],
        );

        $response = $this->application($router)->handle(Request::create('GET', '/admin/login'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('Login', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testAuthenticatedAdministratorIsRedirectedFromGuestOnlyLoginPage(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(5));
        $router = new Router();
        $router->get(
            '/admin/login',
            static fn (Request $request, array $parameters): Response => Response::html('Login'),
            [new GuestOnlyMiddleware($this->administratorSession)],
        );

        $response = $this->application($router)->handle(Request::create('GET', '/admin/login'));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/dashboard', $response->headers()['Location']);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testStateChangingFormRouteAcceptsValidCsrfToken(): void
    {
        $token = $this->csrfTokens->token();
        $router = new Router();
        $router->post(
            '/admin/resource',
            static fn (Request $request, array $parameters): Response => Response::text('Changed'),
            [new CsrfMiddleware($this->csrfTokens)],
        );

        $response = $this->application($router)->handle(
            Request::create('POST', '/admin/resource', input: ['_csrf' => $token]),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Changed', $response->body());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testStateChangingJsonRouteRejectsMissingCsrfToken(): void
    {
        $router = new Router();
        $router->patch(
            '/api/v1/admin/resource',
            static fn (Request $request, array $parameters): Response => Response::json(['updated' => true]),
            [new CsrfMiddleware($this->csrfTokens, true)],
        );

        $response = $this->application($router)->handle(
            Request::create('PATCH', '/api/v1/admin/resource'),
        );

        self::assertSame(403, $response->statusCode());
        self::assertSame('{"message":"Forbidden."}', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testProtectedStateChangingRouteChecksAuthenticationBeforeCsrf(): void
    {
        $router = new Router();
        $router->post(
            '/admin/resource',
            static fn (Request $request, array $parameters): Response => Response::text('Changed'),
            [
                new RequireAdministratorMiddleware($this->administratorSession),
                new CsrfMiddleware($this->csrfTokens),
            ],
        );

        $response = $this->application($router)->handle(
            Request::create('POST', '/admin/resource'),
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testAuthenticatedAdministratorWithValidCsrfReachesProtectedStateChange(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(5));
        $token = $this->csrfTokens->token();
        $router = new Router();
        $router->delete(
            '/api/v1/admin/resource',
            static fn (Request $request, array $parameters): Response => Response::json(['deleted' => true]),
            [
                new RequireAdministratorMiddleware($this->administratorSession, true),
                new CsrfMiddleware($this->csrfTokens, true),
            ],
        );

        $response = $this->application($router)->handle(
            Request::create(
                'DELETE',
                '/api/v1/admin/resource',
                headers: ['X-CSRF-Token' => $token],
            ),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('{"deleted":true}', $response->body());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    private function application(Router $router): Application
    {
        return new Application(
            $router,
            new ErrorHandler(new NullLogger(), false),
            $this->session,
        );
    }

    /**
     * @return array<string, mixed>
     */
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
