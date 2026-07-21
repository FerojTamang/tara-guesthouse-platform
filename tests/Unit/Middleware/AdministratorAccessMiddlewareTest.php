<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorAccessMiddlewareTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private AdministratorSession $administratorSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-admin-middleware-unit-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->administratorSession = new AdministratorSession(
            $this->session,
            new CsrfTokenManager($this->session),
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

    public function testGuestIsRedirectedFromProtectedPageWithoutCallingHandler(): void
    {
        $called = false;
        $middleware = new RequireAdministratorMiddleware($this->administratorSession);

        $response = $middleware->process(
            Request::create('GET', '/admin/dashboard'),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return Response::text('Dashboard');
            },
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertFalse($called);
    }

    public function testGuestReceivesGenericUnauthorizedJsonFromProtectedApi(): void
    {
        $called = false;
        $middleware = new RequireAdministratorMiddleware($this->administratorSession, true);

        $response = $middleware->process(
            Request::create('GET', '/api/v1/admin/profile'),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return Response::json(['id' => 1]);
            },
        );

        self::assertSame(401, $response->statusCode());
        self::assertSame('{"message":"Authentication required."}', $response->body());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertFalse($called);
    }

    public function testAuthenticatedAdministratorReachesProtectedHandler(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(3));
        $middleware = new RequireAdministratorMiddleware($this->administratorSession);

        $response = $middleware->process(
            Request::create('GET', '/admin/dashboard'),
            static fn (Request $request): Response => Response::text('Dashboard'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Dashboard', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testAnonymousGuestReachesGuestOnlyHandler(): void
    {
        $middleware = new GuestOnlyMiddleware($this->administratorSession);

        $response = $middleware->process(
            Request::create('GET', '/admin/login'),
            static fn (Request $request): Response => Response::text('Login'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Login', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testAuthenticatedAdministratorIsRedirectedFromGuestOnlyPage(): void
    {
        $called = false;
        $this->administratorSession->establish(new AdministratorIdentity(3));
        $middleware = new GuestOnlyMiddleware($this->administratorSession);

        $response = $middleware->process(
            Request::create('GET', '/admin/login'),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return Response::text('Login');
            },
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/dashboard', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertFalse($called);
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
