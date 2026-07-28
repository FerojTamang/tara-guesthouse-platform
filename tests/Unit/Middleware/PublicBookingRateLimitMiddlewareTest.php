<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Services\PublicBookingRateLimiter;
use PHPUnit\Framework\TestCase;

final class PublicBookingRateLimitMiddlewareTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-public-booking-middleware-'
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

    public function testAllowedAttemptsReachTheHandlerWithRemainingHeader(): void
    {
        $called = false;
        $middleware = new PublicBookingRateLimitMiddleware($this->limiter());

        $response = $middleware->process(
            Request::create(
                'POST',
                '/api/v1/bookings',
                remoteAddress: '203.0.113.10',
            ),
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

    public function testSixthAttemptReturns429WithoutCallingTheHandler(): void
    {
        $called = 0;
        $middleware = new PublicBookingRateLimitMiddleware($this->limiter());
        $request = Request::create(
            'POST',
            '/api/v1/bookings',
            remoteAddress: '203.0.113.11',
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $middleware->process(
                $request,
                static function (Request $request) use (&$called): Response {
                    $called++;

                    return Response::json(['success' => true], 201);
                },
            );
            self::assertSame(201, $response->statusCode());
        }

        $blocked = $middleware->process(
            $request,
            static function (Request $request) use (&$called): Response {
                $called++;

                return Response::json(['success' => true], 201);
            },
        );

        self::assertSame(5, $called);
        self::assertSame(429, $blocked->statusCode());
        self::assertSame('0', $blocked->headers()['X-RateLimit-Remaining']);
        self::assertGreaterThan(0, (int) $blocked->headers()['Retry-After']);
        self::assertSame([
            'success' => false,
            'message' => 'Too many booking attempts.',
        ], json_decode($blocked->body(), true, 16, JSON_THROW_ON_ERROR));
    }

    public function testMissingRemoteAddressFailsClosed(): void
    {
        $called = false;
        $response = (new PublicBookingRateLimitMiddleware($this->limiter()))
            ->process(
                Request::create('POST', '/api/v1/bookings'),
                static function (Request $request) use (&$called): Response {
                    $called = true;

                    return Response::json(['success' => true]);
                },
            );

        self::assertFalse($called);
        self::assertSame(500, $response->statusCode());
        self::assertSame(
            '{"success":false,"message":"Internal Server Error."}',
            $response->body(),
        );
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    private function limiter(): PublicBookingRateLimiter
    {
        return new PublicBookingRateLimiter($this->directory, [
            'max_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ]);
    }
}
