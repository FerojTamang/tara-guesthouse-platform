<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Core\CsrfTokenManager;
use App\Core\Session;
use Throwable;

/**
 * Owns the authenticated Administrator identity inside the hardened PHP session.
 */
final class AdministratorSession
{
    private const IDENTITY_KEY = 'administrator_identity';

    private bool $identityResolved = false;

    private ?AdministratorIdentity $resolvedIdentity = null;

    public function __construct(
        private readonly Session $session,
        private readonly CsrfTokenManager $csrfTokens,
        private readonly ?AdministratorIdentityValidator $identityValidator = null,
    ) {
    }

    /**
     * Returns the current valid identity or safely removes malformed/revoked state.
     */
    public function identity(): ?AdministratorIdentity
    {
        if ($this->identityResolved) {
            return $this->resolvedIdentity;
        }

        if (!$this->session->has(self::IDENTITY_KEY)) {
            return $this->resolve(null);
        }

        $identity = AdministratorIdentity::fromSessionData(
            $this->session->get(self::IDENTITY_KEY),
        );

        if ($identity === null) {
            $this->discardIdentity();

            return $this->resolve(null);
        }

        try {
            $identityIsValid = $this->identityValidator === null
                || $this->identityValidator->isValid($identity);
        } catch (Throwable) {
            $identityIsValid = false;
        }

        if (!$identityIsValid) {
            $this->discardIdentity();

            return $this->resolve(null);
        }

        return $this->resolve($identity);
    }

    public function isAuthenticated(): bool
    {
        return $this->identity() !== null;
    }

    /**
     * Establishes one authenticated identity after successful credential checks.
     */
    public function establish(AdministratorIdentity $identity): void
    {
        $this->session->renew();
        $this->csrfTokens->rotate();
        $this->session->set(self::IDENTITY_KEY, $identity->toSessionData());
        $this->resolve($identity);
    }

    /**
     * Destroys the complete authenticated session and returns its prior identity.
     */
    public function terminate(): ?AdministratorIdentity
    {
        $identity = $this->identity();
        $this->session->destroy();
        $this->resolve(null);

        return $identity;
    }

    private function discardIdentity(): void
    {
        $this->session->remove(self::IDENTITY_KEY);
        $this->session->regenerate();
        $this->csrfTokens->rotate();
    }

    private function resolve(?AdministratorIdentity $identity): ?AdministratorIdentity
    {
        $this->identityResolved = true;
        $this->resolvedIdentity = $identity;

        return $identity;
    }
}
