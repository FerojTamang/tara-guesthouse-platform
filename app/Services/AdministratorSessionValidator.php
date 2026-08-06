<?php

declare(strict_types=1);

namespace App\Services;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorIdentityValidator;
use App\Models\User;
use App\Repositories\AdministratorSessionRepository;
use Closure;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Revalidates a session identity against the current persisted account state.
 */
final class AdministratorSessionValidator implements AdministratorIdentityValidator
{
    /** @var Closure(): AdministratorSessionRepository */
    private readonly Closure $repositoryFactory;

    private ?AdministratorSessionRepository $repository = null;

    /** @param callable(): AdministratorSessionRepository $repositoryFactory */
    public function __construct(
        callable $repositoryFactory,
        private readonly LoggerInterface $logger,
    ) {
        $this->repositoryFactory = Closure::fromCallable($repositoryFactory);
    }

    public function isValid(AdministratorIdentity $identity): bool
    {
        try {
            $user = $this->repository()->findAdministratorById($identity->userId());
            $valid = $user instanceof User
                && $user->id() === $identity->userId()
                && $user->isAdministrator()
                && $user->isActive()
                && !$user->isDeleted();

            if (!$valid) {
                $this->safeLog('warning', 'administrator_session_revoked', [
                    'event_code' => 'administrator_session_revoked',
                    'outcome' => 'revoked',
                    'user_id' => $identity->userId(),
                    'context' => 'session',
                ]);
            }

            return $valid;
        } catch (Throwable) {
            $this->safeLog('error', 'administrator_session_validation_failed', [
                'event_code' => 'administrator_session_validation_failed',
                'outcome' => 'failed',
                'user_id' => $identity->userId(),
                'context' => 'session',
            ]);

            return false;
        }
    }

    private function repository(): AdministratorSessionRepository
    {
        if ($this->repository === null) {
            $repository = ($this->repositoryFactory)();

            if (!$repository instanceof AdministratorSessionRepository) {
                throw new RuntimeException('The Administrator session repository is invalid.');
            }

            $this->repository = $repository;
        }

        return $this->repository;
    }

    /** @param array<string, int|string> $context */
    private function safeLog(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            error_log('Administrator session security logging failed.');
        }
    }
}
