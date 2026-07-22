<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Authentication\AdministratorSession;
use App\Core\Request;
use App\Core\Response;

/**
 * Prevents an authenticated Administrator from reopening guest-only endpoints.
 */
final class GuestOnlyMiddleware implements Middleware
{
    public function __construct(
        private readonly AdministratorSession $administratorSession,
        private readonly bool $jsonResponse = false,
    ) {
    }

    public function forJson(): self
    {
        return new self($this->administratorSession, true);
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->administratorSession->isAuthenticated()) {
            return $next($request)->withHeader('Cache-Control', 'no-store');
        }

        if ($this->jsonResponse) {
            return Response::json(
                ['message' => 'Already authenticated.'],
                409,
                ['Cache-Control' => 'no-store'],
            );
        }

        return Response::redirect(
            '/admin/dashboard',
            302,
            ['Cache-Control' => 'no-store'],
        );
    }
}
