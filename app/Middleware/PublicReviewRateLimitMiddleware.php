<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ReviewException;
use App\Services\PublicReviewRateLimiter;

/** Limits public review submissions by the direct REMOTE_ADDR peer. */
final readonly class PublicReviewRateLimitMiddleware implements Middleware
{
    public function __construct(
        private PublicReviewRateLimiter $limiter,
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
        } catch (ReviewException) {
            return $this->failure();
        }

        if ($state->isBlocked()) {
            $headers = $this->responseHeaders([
                'Retry-After' => (string) $state->retryAfterSeconds(),
                'X-RateLimit-Remaining' => '0',
            ]);

            return $this->jsonResponse
                ? Response::json(
                    [
                        'success' => false,
                        'message' => 'Too many review attempts.',
                    ],
                    429,
                    $headers,
                )
                : Response::text(
                    'Too many review attempts.',
                    429,
                    $headers,
                );
        }

        $response = $next($request)->withHeader(
            'X-RateLimit-Remaining',
            (string) $state->remainingAttempts(),
        );

        foreach ($this->responseHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
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
                $this->responseHeaders(),
            )
            : Response::text(
                'Internal Server Error',
                500,
                $this->responseHeaders(),
            );
    }

    /** @param array<string, string> $additional @return array<string, string> */
    private function responseHeaders(array $additional = []): array
    {
        $headers = ['Cache-Control' => 'no-store'];

        if (!$this->jsonResponse) {
            $headers += [
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ];
        }

        return $headers + $additional;
    }
}
