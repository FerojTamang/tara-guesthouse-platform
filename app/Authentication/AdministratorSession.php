<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Core\CsrfTokenManager;
use App\Core\Session;

/**
 * Owns the authenticated Administrator identity inside the hardened PHP session.
 */
final class AdministratorSession
{
    private const IDENTITY_KEY = 'administrator_identity';

    public function __construct(
        private readonly Session $session,
        private readonly CsrfTokenManager $csrfTokens,
    ) {
    }

    /**
     * Returns the current valid identity or safely removes malformed state.
     */
    public function identity(): ?AdministratorIdentity
    {
        if (!$this->session->has(self::IDENTITY_KEY)) {
            return null;
        }

        $identity = AdministratorIdentity::fromSessionData(
            $this->session->get(self::IDENTITY_KEY),
        );

        if ($identity === null) {
            $this->discardMalformedIdentity();
        }

        return $identity;
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
        $this->session->regenerate();
        $this->csrfTokens->rotate();
        $this->session->set(self::IDENTITY_KEY, $identity->toSessionData());
    }

    private function discardMalformedIdentity(): void
    {
        $this->session->remove(self::IDENTITY_KEY);
        $this->session->regenerate();
        $this->csrfTokens->rotate();
    }
}
