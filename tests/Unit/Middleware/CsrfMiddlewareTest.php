<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CsrfMiddlewareTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private CsrfTokenManager $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-csrf-middleware-unit-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->tokens = new CsrfTokenManager($this->session);
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'HEAD' => ['HEAD'];
        yield 'OPTIONS' => ['OPTIONS'];
    }

    #[DataProvider('safeMethods')]
    public function testSafeMethodsBypassCsrfValidation(string $method): void
    {
        $called = false;
        $middleware = new CsrfMiddleware($this->tokens);

        $response = $middleware->process(
            Request::create($method, '/resource'),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return Response::text('Allowed');
            },
        );

        self::assertTrue($called);
        self::assertSame(200, $response->statusCode());
        self::assertSame('Allowed', $response->body());
        self::assertArrayNotHasKey('csrf_token', $_SESSION['__tgh']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
        yield 'DELETE' => ['DELETE'];
    }

    #[DataProvider('protectedMethods')]
    public function testProtectedMethodsAcceptAValidFormToken(string $method): void
    {
        $token = $this->tokens->token();
        $middleware = new CsrfMiddleware($this->tokens);

        $response = $middleware->process(
            Request::create($method, '/resource', input: ['_csrf' => $token]),
            static fn (Request $request): Response => Response::text('Changed'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Changed', $response->body());
        self::assertSame($token, $this->tokens->token());
    }

    public function testValidHeaderTokenIsAccepted(): void
    {
        $token = $this->tokens->token();
        $middleware = new CsrfMiddleware($this->tokens, true);

        $response = $middleware->process(
            Request::create('PATCH', '/api/v1/admin/resource', headers: ['X-CSRF-Token' => $token]),
            static fn (Request $request): Response => Response::json(['updated' => true]),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('{"updated":true}', $response->body());
    }

    public function testMatchingFormAndHeaderTokensAreAccepted(): void
    {
        $token = $this->tokens->token();
        $middleware = new CsrfMiddleware($this->tokens);

        $response = $middleware->process(
            Request::create(
                'POST',
                '/resource',
                headers: ['x-csrf-token' => $token],
                input: ['_csrf' => $token],
            ),
            static fn (Request $request): Response => Response::text('Changed'),
        );

        self::assertSame(200, $response->statusCode());
    }

    public function testMissingTokenReturnsGenericHtmlForbiddenWithoutCallingHandler(): void
    {
        $called = false;
        $middleware = new CsrfMiddleware($this->tokens);

        $response = $middleware->process(
            Request::create('POST', '/resource'),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return Response::text('Changed');
            },
        );

        self::assertSame(403, $response->statusCode());
        self::assertSame('Forbidden', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertFalse($called);
        self::assertArrayNotHasKey('csrf_token', $_SESSION['__tgh']);
    }

    public function testInvalidTokenReturnsGenericJsonForbidden(): void
    {
        $this->tokens->token();
        $middleware = new CsrfMiddleware($this->tokens, true);

        $response = $middleware->process(
            Request::create(
                'DELETE',
                '/api/v1/admin/resource',
                headers: ['X-CSRF-Token' => str_repeat('a', 64)],
            ),
            static fn (Request $request): Response => Response::json(['deleted' => true]),
        );

        self::assertSame(403, $response->statusCode());
        self::assertSame('{"message":"Forbidden."}', $response->body());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testConflictingFormAndHeaderTokensAreRejected(): void
    {
        $validToken = $this->tokens->token();
        $called = false;
        $middleware = new CsrfMiddleware($this->tokens);

        $response = $middleware->process(
            Request::create(
                'POST',
                '/resource',
                headers: ['X-CSRF-Token' => $validToken],
                input: ['_csrf' => str_repeat('b', 64)],
            ),
            static function (Request $request) use (&$called): Response {
                $called = true;

                return Response::text('Changed');
            },
        );

        self::assertSame(403, $response->statusCode());
        self::assertFalse($called);
    }

    public function testNonStringFormTokenIsRejectedEvenWhenHeaderIsValid(): void
    {
        $validToken = $this->tokens->token();
        $middleware = new CsrfMiddleware($this->tokens);

        $response = $middleware->process(
            Request::create(
                'POST',
                '/resource',
                headers: ['X-CSRF-Token' => $validToken],
                input: ['_csrf' => [$validToken]],
            ),
            static fn (Request $request): Response => Response::text('Changed'),
        );

        self::assertSame(403, $response->statusCode());
    }

    public function testInvalidValidationDoesNotRotateOrReplaceTheCurrentToken(): void
    {
        $current = $this->tokens->token();
        $middleware = new CsrfMiddleware($this->tokens);

        $middleware->process(
            Request::create('POST', '/resource', input: ['_csrf' => str_repeat('c', 64)]),
            static fn (Request $request): Response => Response::text('Changed'),
        );

        self::assertSame($current, $this->tokens->token());
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
