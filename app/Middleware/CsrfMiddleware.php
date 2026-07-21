<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;

/**
 * Enforces the session-bound CSRF synchronizer token on state-changing requests.
 */
final class CsrfMiddleware implements Middleware
{
    /** @var list<string> */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly CsrfTokenManager $tokens,
        private readonly bool $jsonResponse = false,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::PROTECTED_METHODS, true)) {
            return $next($request);
        }

        $input = $request->input();
        $formTokenPresent = array_key_exists('_csrf', $input);
        $formToken = $formTokenPresent && is_string($input['_csrf'])
            ? $input['_csrf']
            : null;
        $headerToken = $request->header('X-CSRF-Token');
        $headerTokenPresent = $headerToken !== null;

        if ($formTokenPresent && $formToken === null) {
            return $this->forbidden();
        }

        if (
            $formTokenPresent
            && $headerTokenPresent
            && !hash_equals($formToken, $headerToken)
        ) {
            return $this->forbidden();
        }

        $candidate = $headerTokenPresent ? $headerToken : $formToken;

        if (!$this->tokens->validate($candidate)) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function forbidden(): Response
    {
        if ($this->jsonResponse) {
            return Response::json(
                ['message' => 'Forbidden.'],
                403,
                ['Cache-Control' => 'no-store'],
            );
        }

        return Response::text(
            'Forbidden',
            403,
            ['Cache-Control' => 'no-store'],
        );
    }
}
