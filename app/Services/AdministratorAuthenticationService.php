<?php

declare(strict_types=1);

namespace App\Services;

use App\Authentication\AdministratorIdentity;
use App\Authentication\LoginRateLimiter;
use App\Exceptions\AdministratorAuthenticationException;
use App\Models\User;
use App\Repositories\AdministratorAuthenticationRepository;
use App\Requests\AdministratorLoginRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Verifies Administrator credentials and records a successful login safely.
 */
final class AdministratorAuthenticationService implements AdministratorAuthenticator
{
    /** @var Closure(string, string): bool */
    private readonly Closure $passwordVerifier;

    /** @var Closure(string): bool */
    private readonly Closure $passwordRehashChecker;

    /** @var Closure(string): string */
    private readonly Closure $passwordHasher;

    /** @var Closure(): DateTimeImmutable */
    private readonly Closure $clock;

    /** @var Closure(): string */
    private readonly Closure $dummyHashFactory;

    private ?string $dummyPasswordHash = null;

    public function __construct(
        private readonly AdministratorAuthenticationRepository $repository,
        private readonly LoginRateLimiter $rateLimiter,
        private readonly LoggerInterface $logger,
        ?callable $passwordVerifier = null,
        ?callable $passwordRehashChecker = null,
        ?callable $passwordHasher = null,
        ?callable $clock = null,
        ?callable $dummyHashFactory = null,
    ) {
        $this->passwordVerifier = Closure::fromCallable(
            $passwordVerifier
                ?? static fn (#[\SensitiveParameter] string $password, string $hash): bool => password_verify(
                    $password,
                    $hash,
                ),
        );
        $this->passwordRehashChecker = Closure::fromCallable(
            $passwordRehashChecker
                ?? static fn (string $hash): bool => password_needs_rehash($hash, PASSWORD_DEFAULT),
        );
        $this->passwordHasher = Closure::fromCallable(
            $passwordHasher
                ?? static fn (#[\SensitiveParameter] string $password): string => password_hash(
                    $password,
                    PASSWORD_DEFAULT,
                ),
        );
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
        $this->dummyHashFactory = Closure::fromCallable(
            $dummyHashFactory
                ?? static fn (): string => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        );
    }

    /**
     * Authenticates one request without creating or modifying the PHP session.
     */
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        $safeContext = in_array($context, ['http', 'api'], true)
            ? $context
            : 'authentication';

        try {
            if ($safeContext !== $context) {
                throw AdministratorAuthenticationException::failure();
            }
            $state = $this->rateLimiter->inspect($remoteAddress);

            if ($state->isBlocked()) {
                throw AdministratorAuthenticationException::rateLimited($state->retryAfterSeconds());
            }

            // Build the runtime dummy hash for every unblocked attempt so missing-account
            // verification performs the same expensive setup as existing-account verification.
            $dummyPasswordHash = $this->dummyPasswordHash();
            $user = $request->isEmailIdentifier()
                ? $this->repository->findAdministratorByEmail($request->identifier())
                : $this->repository->findAdministratorByUsername($request->identifier());
            $hash = $user?->passwordHash() ?? $dummyPasswordHash;
            $passwordIsValid = ($this->passwordVerifier)($request->password(), $hash);
            $accountIsEligible = $user instanceof User
                && $user->isAdministrator()
                && $user->isActive()
                && !$user->isDeleted();

            if (!$passwordIsValid || !$accountIsEligible) {
                $failedState = $this->rateLimiter->recordFailure($remoteAddress);

                if ($failedState->isBlocked()) {
                    throw AdministratorAuthenticationException::rateLimited(
                        $failedState->retryAfterSeconds(),
                    );
                }

                throw AdministratorAuthenticationException::invalidCredentials();
            }

            $replacementHash = null;

            if (($this->passwordRehashChecker)($user->passwordHash())) {
                $replacementHash = ($this->passwordHasher)($request->password());
                $this->assertPasswordHash($replacementHash, $request->password());
            }

            $loggedInAt = ($this->clock)();

            if (!$loggedInAt instanceof DateTimeImmutable || $loggedInAt->getOffset() !== 0) {
                throw AdministratorAuthenticationException::failure();
            }

            $this->repository->recordSuccessfulLogin(
                $user->id(),
                $user->passwordHash(),
                $replacementHash,
                $loggedInAt,
            );
            $this->rateLimiter->clear($remoteAddress);

            $this->safeLog('info', 'administrator_login_succeeded', [
                'event_code' => 'administrator_login_succeeded',
                'outcome' => 'authenticated',
                'user_id' => $user->id(),
                'context' => $safeContext,
            ]);

            return new AdministratorIdentity($user->id());
        } catch (AdministratorAuthenticationException $exception) {
            $this->logFailure($exception, $safeContext);

            throw $exception;
        } catch (Throwable $throwable) {
            $exception = AdministratorAuthenticationException::failure($throwable);
            $this->logFailure($exception, $safeContext);

            throw $exception;
        }
    }

    private function dummyPasswordHash(): string
    {
        if ($this->dummyPasswordHash === null) {
            $hash = ($this->dummyHashFactory)();
            $this->assertPasswordHash($hash, 'dummy-password-sentinel');
            $this->dummyPasswordHash = $hash;
        }

        return $this->dummyPasswordHash;
    }

    private function assertPasswordHash(
        string $hash,
        #[\SensitiveParameter] string $plaintext,
    ): void {
        if (
            $hash === ''
            || hash_equals($plaintext, $hash)
            || (password_get_info($hash)['algoName'] ?? 'unknown') === 'unknown'
        ) {
            throw AdministratorAuthenticationException::failure();
        }
    }

    private function logFailure(
        AdministratorAuthenticationException $exception,
        string $context,
    ): void
    {
        if ($exception->isRateLimited()) {
            $this->safeLog('warning', 'administrator_login_rate_limited', [
                'event_code' => 'administrator_login_rate_limited',
                'outcome' => 'blocked',
                'context' => $context,
            ]);

            return;
        }

        if ($exception->isSystemFailure()) {
            $this->safeLog('error', 'administrator_login_failed', [
                'event_code' => 'administrator_login_failed',
                'outcome' => 'failed',
                'context' => $context,
            ]);

            return;
        }

        $this->safeLog('warning', 'administrator_login_rejected', [
            'event_code' => 'administrator_login_rejected',
            'outcome' => 'rejected',
            'context' => $context,
        ]);
    }

    /** @param array<string, int|string> $context */
    private function safeLog(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            error_log('Administrator authentication security logging failed.');
        }
    }
}
