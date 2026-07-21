<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AdministratorBootstrapException;
use App\Models\User;
use App\Repositories\AdministratorBootstrapRepository;
use App\Requests\AdministratorBootstrapRequest;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Coordinates the single-use initial-Administrator bootstrap workflow.
 */
final class AdministratorBootstrapService
{
    /** @var Closure(): AdministratorBootstrapRepository */
    private readonly Closure $repositoryFactory;

    /** @var Closure(string): string */
    private readonly Closure $passwordHasher;

    /**
     * @param callable(): AdministratorBootstrapRepository $repositoryFactory
     * @param array<string, mixed> $policy
     */
    public function __construct(
        callable $repositoryFactory,
        private readonly LoggerInterface $logger,
        private readonly array $policy,
        ?callable $passwordHasher = null,
    ) {
        $this->repositoryFactory = Closure::fromCallable($repositoryFactory);
        $this->passwordHasher = Closure::fromCallable(
            $passwordHasher ?? static fn (#[\SensitiveParameter] string $password): string => password_hash(
                $password,
                PASSWORD_DEFAULT,
            ),
        );
    }

    /**
     * Enforces environment policy before any password prompt or database connection.
     */
    public function assertEnvironment(string $environment, bool $force): void
    {
        self::assertEnvironmentPolicy($this->policy, $environment, $force);
    }

    /**
     * Enforces environment policy without requiring database or logger configuration.
     *
     * @param array<string, mixed> $policy
     */
    public static function assertEnvironmentPolicy(
        array $policy,
        string $environment,
        bool $force,
    ): void {
        $allowed = $policy['allowed_environments'] ?? null;
        $forceRequired = $policy['force_required_environments'] ?? null;

        if (!is_array($allowed) || !is_array($forceRequired)) {
            throw AdministratorBootstrapException::failure();
        }

        if (!in_array($environment, $allowed, true)) {
            throw AdministratorBootstrapException::usage(
                'Administrator bootstrap is not allowed in this environment.',
            );
        }

        if (in_array($environment, $forceRequired, true) && !$force) {
            throw AdministratorBootstrapException::usage('This environment requires --force.');
        }
    }

    /**
     * Creates the first Administrator or fails without modifying an existing account.
     */
    public function bootstrap(
        AdministratorBootstrapRequest $request,
        string $environment,
        bool $force,
    ): int {
        try {
            $this->assertEnvironment($environment, $force);
            $passwordHash = ($this->passwordHasher)($request->password());

            if (
                $passwordHash === ''
                || hash_equals($request->password(), $passwordHash)
                || (password_get_info($passwordHash)['algoName'] ?? 'unknown') === 'unknown'
            ) {
                throw AdministratorBootstrapException::failure();
            }

            $repository = ($this->repositoryFactory)();

            if (!$repository instanceof AdministratorBootstrapRepository) {
                throw AdministratorBootstrapException::failure();
            }

            $userId = $repository->withinTransaction(function () use ($repository, $request, $passwordHash): int {
                $roleId = $repository->lockAdministratorRole();
                $administrators = $repository->administratorUsers($roleId);

                if (count($administrators) > 1) {
                    throw AdministratorBootstrapException::failure();
                }

                $collisions = $repository->identityCollisions($request->username(), $request->email());

                if ($administrators !== []) {
                    $administrator = $administrators[0];

                    if (
                        $administrator['deleted']
                        || in_array(
                            $administrator['account_status'],
                            [User::STATUS_INACTIVE, User::STATUS_LOCKED],
                            true,
                        )
                    ) {
                        throw AdministratorBootstrapException::workflow(
                            'Existing Administrator state requires account-management tooling.',
                        );
                    }

                    if (
                        strcasecmp($administrator['username'], $request->username()) === 0
                        && strcasecmp($administrator['email'], $request->email()) === 0
                    ) {
                        throw AdministratorBootstrapException::workflow(
                            'Administrator bootstrap is already complete; no changes were made.',
                        );
                    }

                    if ($collisions !== []) {
                        throw AdministratorBootstrapException::workflow(
                            'Administrator bootstrap conflicts with existing account data.',
                        );
                    }

                    throw AdministratorBootstrapException::workflow(
                        'Administrator bootstrap is already complete; no changes were made.',
                    );
                }

                if ($collisions !== []) {
                    throw AdministratorBootstrapException::workflow(
                        'Administrator bootstrap conflicts with existing account data.',
                    );
                }

                return $repository->insertAdministrator(
                    $roleId,
                    $request->fullName(),
                    $request->username(),
                    $request->email(),
                    $request->phone(),
                    $passwordHash,
                );
            });

            $this->safeLog('info', 'administrator_bootstrap_succeeded', [
                'event_code' => 'administrator_bootstrap_succeeded',
                'outcome' => 'created',
                'user_id' => $userId,
                'context' => 'cli',
            ]);

            return $userId;
        } catch (AdministratorBootstrapException $exception) {
            $eventCode = $exception->isSystemFailure()
                ? 'administrator_bootstrap_failed'
                : 'administrator_bootstrap_rejected';
            $this->safeLog($exception->isSystemFailure() ? 'error' : 'warning', $eventCode, [
                'event_code' => $eventCode,
                'outcome' => $exception->isSystemFailure() ? 'failed' : 'rejected',
                'context' => 'cli',
            ]);

            throw $exception;
        } catch (Throwable $throwable) {
            $this->safeLog('error', 'administrator_bootstrap_failed', [
                'event_code' => 'administrator_bootstrap_failed',
                'outcome' => 'failed',
                'context' => 'cli',
            ]);

            throw AdministratorBootstrapException::failure($throwable);
        }
    }

    /** @param array<string, int|string> $context */
    private function safeLog(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            error_log('Administrator bootstrap security logging failed.');
        }
    }
}
