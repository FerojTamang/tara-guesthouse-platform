<?php

declare(strict_types=1);

namespace App\Services;

use App\Authentication\AdministratorIdentity;
use App\Exceptions\AdministratorAuthenticationException;
use App\Requests\AdministratorLoginRequest;
use Closure;
use Throwable;

/**
 * Defers database-backed authentication service construction until login is submitted.
 */
final class LazyAdministratorAuthenticator implements AdministratorAuthenticator
{
    /** @var Closure(): AdministratorAuthenticator */
    private readonly Closure $factory;

    private ?AdministratorAuthenticator $authenticator = null;

    /** @param callable(): AdministratorAuthenticator $factory */
    public function __construct(callable $factory)
    {
        $this->factory = Closure::fromCallable($factory);
    }

    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return $this->authenticator()->authenticate($request, $remoteAddress, $context);
    }

    private function authenticator(): AdministratorAuthenticator
    {
        if ($this->authenticator !== null) {
            return $this->authenticator;
        }

        try {
            $authenticator = ($this->factory)();
        } catch (AdministratorAuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdministratorAuthenticationException::failure($throwable);
        }

        if (!$authenticator instanceof AdministratorAuthenticator) {
            throw AdministratorAuthenticationException::failure();
        }

        $this->authenticator = $authenticator;

        return $this->authenticator;
    }
}
