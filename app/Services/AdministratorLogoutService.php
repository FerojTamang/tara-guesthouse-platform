<?php

declare(strict_types=1);

namespace App\Services;

use App\Authentication\AdministratorSession;
use App\Exceptions\AdministratorAuthenticationException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Terminates an authenticated Administrator session and records a safe event.
 */
final class AdministratorLogoutService
{
    public function __construct(
        private readonly AdministratorSession $administratorSession,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function logout(): void
    {
        try {
            $identity = $this->administratorSession->terminate();

            if ($identity === null) {
                throw AdministratorAuthenticationException::failure();
            }

            $this->safeLog('info', 'administrator_logout_succeeded', [
                'event_code' => 'administrator_logout_succeeded',
                'outcome' => 'logged_out',
                'user_id' => $identity->userId(),
                'context' => 'http',
            ]);
        } catch (AdministratorAuthenticationException $exception) {
            $this->safeFailureLog();

            throw $exception;
        } catch (Throwable $throwable) {
            $this->safeFailureLog();

            throw AdministratorAuthenticationException::failure($throwable);
        }
    }

    private function safeFailureLog(): void
    {
        $this->safeLog('error', 'administrator_logout_failed', [
            'event_code' => 'administrator_logout_failed',
            'outcome' => 'failed',
            'context' => 'http',
        ]);
    }

    /** @param array<string, int|string> $context */
    private function safeLog(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            error_log('Administrator logout security logging failed.');
        }
    }
}
