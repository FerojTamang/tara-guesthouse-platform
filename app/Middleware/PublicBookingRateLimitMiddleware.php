<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\BookingException;
use App\Services\PublicBookingRateLimiter;

/**
 * Limits public booking submissions by the direct REMOTE_ADDR peer.
 */
final readonly class PublicBookingRateLimitMiddleware implements Middleware
{
    public function __construct(
        private PublicBookingRateLimiter $limiter,
        private bool $jsonResponse = true,
    ) {
    }

    public function forWeb(): self
    {
        return new self($this->limiter, false);
    }

    public function process(Request $request, callable $next): Response
    {
        $remoteAddress = $request->remoteAddress();

        if ($remoteAddress === null) {
            return $this->failure();
        }

        try {
            $state = $this->limiter->consume($remoteAddress);
        } catch (BookingException) {
            return $this->failure();
        }

        if ($state->isBlocked()) {
            $headers = [
                'Cache-Control' => 'no-store',
                'Retry-After' => (string) $state->retryAfterSeconds(),
                'X-RateLimit-Remaining' => '0',
            ];

            return $this->jsonResponse
                ? Response::json(
                    [
                        'success' => false,
                        'message' => 'Too many booking attempts.',
                    ],
                    429,
                    $headers,
                )
                : Response::text(
                    'Too many booking attempts.',
                    429,
                    $headers,
                );
        }

        return $next($request)
            ->withHeader(
                'X-RateLimit-Remaining',
                (string) $state->remainingAttempts(),
            )
            ->withHeader('Cache-Control', 'no-store');
    }

    private function failure(): Response
    {
        return $this->jsonResponse
            ? Response::json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error.',
                ],
                500,
                ['Cache-Control' => 'no-store'],
            )
            : Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
    }
}
