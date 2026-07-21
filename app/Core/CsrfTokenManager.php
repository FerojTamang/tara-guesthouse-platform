<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Manages the session-bound synchronizer token used by future CSRF middleware.
 */
final class CsrfTokenManager
{
    public function __construct(private readonly Session $session)
    {
    }

    /**
     * Returns the current valid token or creates one when absent.
     */
    public function token(): string
    {
        $token = $this->session->csrfToken();

        if ($token !== null && preg_match('/^[a-f0-9]{64}$/D', $token) === 1) {
            return $token;
        }

        return $this->rotate();
    }

    /**
     * Validates a candidate without generating or mutating session state.
     */
    public function validate(?string $candidate): bool
    {
        if ($candidate === null || preg_match('/^[a-f0-9]{64}$/D', $candidate) !== 1) {
            return false;
        }

        $expected = $this->session->csrfToken();

        return $expected !== null
            && preg_match('/^[a-f0-9]{64}$/D', $expected) === 1
            && hash_equals($expected, $candidate);
    }

    /**
     * Replaces the current token with 256 bits of cryptographic randomness.
     */
    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->storeCsrfToken($token);

        return $token;
    }

    /**
     * Removes the current token from session state.
     */
    public function clear(): void
    {
        $this->session->clearCsrfToken();
    }
}
