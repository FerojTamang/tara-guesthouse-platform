<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\AdministratorAuthenticationException;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Formats the HTML and same-origin JSON Administrator authentication flows.
 */
final class AdministratorAuthenticationController
{
    /**
     * @param array<string, mixed> $loginPolicy
     */
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly AdministratorAuthenticator $authenticator,
        private readonly AdministratorLogoutService $logoutService,
        private readonly AdministratorSession $administratorSession,
        private readonly CsrfTokenManager $csrfTokens,
        private readonly LoggerInterface $logger,
        private readonly array $loginPolicy,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function showLogin(Request $request, array $parameters): Response
    {
        return $this->loginResponse();
    }

    /** @param array<string, string> $parameters */
    public function login(Request $request, array $parameters): Response
    {
        try {
            $loginRequest = AdministratorLoginRequest::fromInput(
                $request->input('identifier'),
                $request->input('password'),
                $this->loginPolicy,
            );
        } catch (AdministratorAuthenticationException $exception) {
            if (!$exception->isValidationFailure()) {
                throw $exception;
            }

            $this->logInvalidInput('http');

            return $this->loginResponse($exception->getMessage(), '', 422);
        }

        $remoteAddress = $request->remoteAddress();

        if ($remoteAddress === null) {
            throw AdministratorAuthenticationException::failure();
        }

        try {
            $identity = $this->authenticator->authenticate(
                $loginRequest,
                $remoteAddress,
                'http',
            );
        } catch (AdministratorAuthenticationException $exception) {
            if ($exception->isCredentialFailure()) {
                return $this->loginResponse(
                    $exception->getMessage(),
                    $loginRequest->identifier(),
                    401,
                );
            }

            if ($exception->isRateLimited()) {
                return $this->loginResponse(
                    $exception->getMessage(),
                    $loginRequest->identifier(),
                    429,
                    ['Retry-After' => (string) $exception->retryAfterSeconds()],
                );
            }

            if ($exception->isValidationFailure()) {
                return $this->loginResponse($exception->getMessage(), '', 422);
            }

            throw $exception;
        }

        $this->administratorSession->establish($identity);

        return Response::redirect(
            '/admin/dashboard',
            303,
            ['Cache-Control' => 'no-store'],
        );
    }

    /** @param array<string, string> $parameters */
    public function apiLogin(Request $request, array $parameters): Response
    {
        if (!$this->usesJsonContentType($request)) {
            return $this->jsonError('Content-Type must be application/json.', 415);
        }

        try {
            $payload = json_decode(
                $request->body(),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            $this->logInvalidInput('api');

            return $this->jsonError('Request body must contain valid JSON.', 400);
        }

        if (!is_array($payload) || array_is_list($payload)) {
            $this->logInvalidInput('api');

            return $this->jsonError('Request body must contain a JSON object.', 400);
        }

        $keys = array_keys($payload);
        sort($keys, SORT_STRING);

        if ($keys !== ['identifier', 'password']) {
            $this->logInvalidInput('api');

            return $this->jsonError('Request payload is invalid.', 422);
        }

        try {
            $loginRequest = AdministratorLoginRequest::fromInput(
                $payload['identifier'],
                $payload['password'],
                $this->loginPolicy,
            );
        } catch (AdministratorAuthenticationException $exception) {
            if (!$exception->isValidationFailure()) {
                return $this->jsonError('Administrator authentication failed.', 500);
            }

            $this->logInvalidInput('api');

            return $this->jsonError($exception->getMessage(), 422);
        }

        $remoteAddress = $request->remoteAddress();

        if ($remoteAddress === null) {
            $this->safeLog('error', 'administrator_login_failed', [
                'event_code' => 'administrator_login_failed',
                'outcome' => 'failed',
                'context' => 'api',
            ]);

            return $this->jsonError('Administrator authentication failed.', 500);
        }

        try {
            $identity = $this->authenticator->authenticate(
                $loginRequest,
                $remoteAddress,
                'api',
            );
        } catch (AdministratorAuthenticationException $exception) {
            if ($exception->isCredentialFailure()) {
                return $this->jsonError($exception->getMessage(), 401);
            }

            if ($exception->isRateLimited()) {
                return $this->jsonError(
                    $exception->getMessage(),
                    429,
                    ['Retry-After' => (string) $exception->retryAfterSeconds()],
                );
            }

            if ($exception->isValidationFailure()) {
                return $this->jsonError($exception->getMessage(), 422);
            }

            return $this->jsonError('Administrator authentication failed.', 500);
        }

        $this->administratorSession->establish($identity);

        return Response::json(
            [
                'administrator' => [
                    'id' => $identity->userId(),
                    'role' => $identity->role(),
                ],
            ],
            200,
            [
                'Cache-Control' => 'no-store',
                'X-CSRF-Token' => $this->csrfTokens->token(),
            ],
        );
    }

    /** @param array<string, string> $parameters */
    public function dashboard(Request $request, array $parameters): Response
    {
        $html = $this->views->render(
            'admin.dashboard',
            [
                'title' => 'Administrator Dashboard',
                'csrfToken' => $this->csrfTokens->token(),
            ],
            'layouts.base',
        );

        return Response::html($html, 200, ['Cache-Control' => 'no-store']);
    }

    /** @param array<string, string> $parameters */
    public function logout(Request $request, array $parameters): Response
    {
        $this->logoutService->logout();

        return Response::redirect(
            '/admin/login',
            303,
            ['Cache-Control' => 'no-store'],
        );
    }

    /** @param array<string, string> $parameters */
    public function apiLogout(Request $request, array $parameters): Response
    {
        try {
            $this->logoutService->logout();
        } catch (AdministratorAuthenticationException) {
            return $this->jsonError('Administrator logout failed.', 500);
        }

        return Response::json(
            ['message' => 'Logged out.'],
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function loginResponse(
        ?string $error = null,
        string $identifier = '',
        int $statusCode = 200,
        array $headers = [],
    ): Response {
        $html = $this->views->render(
            'auth.login',
            [
                'title' => 'Administrator Login',
                'csrfToken' => $this->csrfTokens->token(),
                'error' => $error,
                'identifier' => $identifier,
            ],
            'layouts.base',
        );
        $headers['Cache-Control'] = 'no-store';

        return Response::html($html, $statusCode, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function jsonError(string $message, int $statusCode, array $headers = []): Response
    {
        $headers['Cache-Control'] = 'no-store';

        return Response::json(['message' => $message], $statusCode, $headers);
    }

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        if ($contentType === null) {
            return false;
        }

        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $mediaType === 'application/json';
    }

    private function logInvalidInput(string $context): void
    {
        $this->safeLog('warning', 'administrator_login_rejected', [
            'event_code' => 'administrator_login_rejected',
            'outcome' => 'invalid_input',
            'context' => $context,
        ]);
    }

    /** @param array<string, string> $context */
    private function safeLog(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            error_log('Administrator authentication security logging failed.');
        }
    }
}
