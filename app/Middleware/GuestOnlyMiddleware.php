<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Authentication\AdministratorSession;
use App\Core\Request;
use App\Core\Response;

/**
 * Prevents an authenticated Administrator from reopening guest-only pages.
 */
final class GuestOnlyMiddleware implements Middleware
{
    public function __construct(private readonly AdministratorSession $administratorSession)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->administratorSession->isAuthenticated()) {
            return $next($request)->withHeader('Cache-Control', 'no-store');
        }

        return Response::redirect(
            '/admin/dashboard',
            302,
            ['Cache-Control' => 'no-store'],
        );
    }
}
