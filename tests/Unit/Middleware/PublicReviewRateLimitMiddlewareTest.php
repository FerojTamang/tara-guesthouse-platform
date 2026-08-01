<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\PublicReviewRateLimitMiddleware;
use App\Services\PublicReviewRateLimiter;
use PHPUnit\Framework\TestCase;

final class PublicReviewRateLimitMiddlewareTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-public-review-middleware-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->directory);
    }

    public function testAllowedAttemptsReachHandlerWithRemainingHeader(): void
    {
        $called = false;
        $middleware = new PublicReviewRateLimitMiddleware($this->limiter());

        $response = $middleware->process(
            Request::create('POST', '/api/v1/reviews', remoteAddress: '203.0.113.10'),
            static function (Request $request) use (&$called): Response {
                $called = true;
                return Response::json(['success' => true], 201);
            },
        );

        self::assertTrue($called);
        self::assertSame(201, $response->statusCode());
        self::assertSame('4', $response->headers()['X-RateLimit-Remaining']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testSixthAttemptReturns429WithoutCallingHandler(): void
    {
        $called = 0;
        $middleware = new PublicReviewRateLimitMiddleware($this->limiter());
        $request = Request::create('POST', '/api/v1/reviews', remoteAddress: '203.0.113.11');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $middleware->process($request, static function () use (&$called): Response {
                $called++;
                return Response::json(['success' => true], 201);
            });
        }

        $blocked = $middleware->process($request, static function () use (&$called): Response {
            $called++;
            return Response::json(['success' => true], 201);
        });

        self::assertSame(5, $called);
        self::assertSame(429, $blocked->statusCode());
        self::assertSame('0', $blocked->headers()['X-RateLimit-Remaining']);
        self::assertSame('900', $blocked->headers()['Retry-After']);
        self::assertStringContainsString('Too many review attempts.', $blocked->body());

        $web = new PublicReviewRateLimitMiddleware($this->limiter(), false);
        $webRequest = Request::create('POST', '/review/' . str_repeat('ab', 32), remoteAddress: '203.0.113.12');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $web->process($webRequest, static fn (): Response => Response::text('ok'));
        }
        $webBlocked = $web->process($webRequest, static fn (): Response => Response::text('called'));
        self::assertSame('no-referrer', $webBlocked->headers()['Referrer-Policy']);
        self::assertSame('noindex, nofollow, noarchive', $webBlocked->headers()['X-Robots-Tag']);
    }

    public function testMissingPeerFailsClosedForJsonAndWeb(): void
    {
        $request = Request::create('POST', '/review/' . str_repeat('ab', 32));
        $json = new PublicReviewRateLimitMiddleware($this->limiter());

        self::assertSame(500, $json->process($request, static fn (): Response => Response::text('called'))->statusCode());
        $webFailure = $json->forWeb()->process($request, static fn (): Response => Response::text('called'));
        self::assertSame('Internal Server Error', $webFailure->body());
        self::assertSame('no-referrer', $webFailure->headers()['Referrer-Policy']);
        self::assertSame('noindex, nofollow, noarchive', $webFailure->headers()['X-Robots-Tag']);
    }

    private function limiter(): PublicReviewRateLimiter
    {
        return new PublicReviewRateLimiter($this->directory, [
            'max_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ]);
    }
}
