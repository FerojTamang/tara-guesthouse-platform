<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Provides hardened native PHP-session lifecycle and centralized state access.
 */
final class Session
{
    private const INTERNAL_KEY = '__tgh';

    private const CREATED_AT = 'created_at';

    private const LAST_ACTIVITY_AT = 'last_activity_at';

    private const CSRF_TOKEN = 'csrf_token';

    /**
     * @param array<string, mixed> $configuration
     */
    private function __construct(private readonly array $configuration)
    {
    }

    /**
     * Configures and starts one native file-backed session.
     *
     * @param array<string, mixed> $configuration
     */
    public static function start(array $configuration): self
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException('Native PHP sessions are unavailable.');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A native PHP session is already active.');
        }

        self::validateConfiguration($configuration);

        self::setIni('session.save_handler', 'files');
        self::setIni('session.save_path', $configuration['save_path']);
        self::setIni('session.use_strict_mode', '1');
        self::setIni('session.use_cookies', '1');
        self::setIni('session.use_only_cookies', '1');
        self::setIni('session.use_trans_sid', '0');
        self::setIni('session.gc_maxlifetime', (string) $configuration['gc_maxlifetime']);

        if (!session_name($configuration['name'])) {
            throw new RuntimeException('The configured session name could not be applied.');
        }

        $cookie = $configuration['cookie'];

        if (!session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'] ?? '',
            'secure' => $cookie['secure'],
            'httponly' => true,
            'samesite' => $cookie['same_site'],
        ])) {
            throw new RuntimeException('The configured session cookie parameters could not be applied.');
        }

        if (!@session_start()) {
            throw new RuntimeException('The native PHP session could not be started.');
        }

        $session = new self($configuration);
        $session->initializeMetadata();

        return $session;
    }

    /**
     * Determines whether an application session key exists, including stored null.
     */
    public function has(string $key): bool
    {
        $this->assertApplicationKey($key);
        $this->assertActive();

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Retrieves an application session value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $_SESSION[$key] : $default;
    }

    /**
     * Stores an application session value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->assertApplicationKey($key);
        $this->assertActive();
        $_SESSION[$key] = $value;
    }

    /**
     * Removes an application session value.
     */
    public function remove(string $key): void
    {
        $this->assertApplicationKey($key);
        $this->assertActive();
        unset($_SESSION[$key]);
    }

    /**
     * Retrieves and removes an application session value.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);

        return $value;
    }

    /**
     * Replaces the active identifier and deletes the old session while preserving data.
     */
    public function regenerate(): void
    {
        $this->assertActive();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('The session identifier could not be regenerated.');
        }
    }

    /**
     * Clears prior state and creates a fresh active anonymous session.
     */
    public function reset(): void
    {
        $this->assertActive();
        $_SESSION = [];
        $this->regenerate();
        $this->writeMetadata(time());
    }

    /**
     * Clears state, expires the matching cookie, and destroys server-side data.
     */
    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('The native PHP session is not active.');
        }

        $_SESSION = [];
        $cookie = $this->configuration['cookie'];
        $cookieExpired = true;

        if ((bool) ini_get('session.use_cookies')) {
            $cookieExpired = !headers_sent() && setcookie(session_name(), '', [
                'expires' => 1,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'] ?? '',
                'secure' => $cookie['secure'],
                'httponly' => true,
                'samesite' => $cookie['same_site'],
            ]);
        }

        $destroyed = session_destroy();
        $_SESSION = [];

        if (!$destroyed || !$cookieExpired) {
            throw new RuntimeException('The native PHP session could not be destroyed safely.');
        }
    }

    /**
     * Writes and closes an active native session.
     */
    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
            throw new RuntimeException('The native PHP session could not be closed.');
        }
    }

    /**
     * Returns the CSRF token held in the private session namespace.
     */
    public function csrfToken(): ?string
    {
        $this->assertActive();
        $metadata = $_SESSION[self::INTERNAL_KEY] ?? null;

        if (!is_array($metadata)) {
            return null;
        }

        $token = $metadata[self::CSRF_TOKEN] ?? null;

        return is_string($token) ? $token : null;
    }

    /**
     * Stores a CSRF token in the private session namespace.
     */
    public function storeCsrfToken(string $token): void
    {
        $this->assertActive();
        $_SESSION[self::INTERNAL_KEY][self::CSRF_TOKEN] = $token;
    }

    /**
     * Removes the CSRF token from the private session namespace.
     */
    public function clearCsrfToken(): void
    {
        $this->assertActive();
        unset($_SESSION[self::INTERNAL_KEY][self::CSRF_TOKEN]);
    }

    private function initializeMetadata(): void
    {
        $this->assertActive();
        $now = time();

        if (!array_key_exists(self::INTERNAL_KEY, $_SESSION)) {
            if ($_SESSION !== []) {
                $this->reset();

                return;
            }

            $this->writeMetadata($now);

            return;
        }

        $metadata = $_SESSION[self::INTERNAL_KEY];

        if (!$this->validMetadata($metadata, $now)) {
            $this->reset();

            return;
        }

        $inactiveFor = $now - $metadata[self::LAST_ACTIVITY_AT];
        $age = $now - $metadata[self::CREATED_AT];

        if (
            $inactiveFor >= $this->configuration['inactivity_timeout']
            || $age >= $this->configuration['absolute_lifetime']
        ) {
            $this->reset();

            return;
        }

        $_SESSION[self::INTERNAL_KEY][self::LAST_ACTIVITY_AT] = $now;
    }

    private function writeMetadata(int $now): void
    {
        $_SESSION[self::INTERNAL_KEY] = [
            self::CREATED_AT => $now,
            self::LAST_ACTIVITY_AT => $now,
        ];
    }

    private function validMetadata(mixed $metadata, int $now): bool
    {
        if (!is_array($metadata)) {
            return false;
        }

        $createdAt = $metadata[self::CREATED_AT] ?? null;
        $lastActivityAt = $metadata[self::LAST_ACTIVITY_AT] ?? null;

        return is_int($createdAt)
            && is_int($lastActivityAt)
            && $createdAt > 0
            && $lastActivityAt >= $createdAt
            && $lastActivityAt <= $now;
    }

    private function assertApplicationKey(string $key): void
    {
        if (trim($key) === '') {
            throw new \InvalidArgumentException('Session keys cannot be empty.');
        }

        if ($key === self::INTERNAL_KEY) {
            throw new \InvalidArgumentException('The reserved session namespace cannot be accessed directly.');
        }
    }

    private function assertActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('The native PHP session is not active.');
        }
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function validateConfiguration(array $configuration): void
    {
        $cookie = $configuration['cookie'] ?? null;
        $savePath = $configuration['save_path'] ?? null;

        if (
            ($configuration['save_handler'] ?? null) !== 'files'
            || ($configuration['strict_mode'] ?? null) !== true
            || ($configuration['use_cookies'] ?? null) !== true
            || ($configuration['use_only_cookies'] ?? null) !== true
            || ($configuration['use_trans_sid'] ?? null) !== false
            || !is_string($configuration['name'] ?? null)
            || !is_int($configuration['inactivity_timeout'] ?? null)
            || !is_int($configuration['absolute_lifetime'] ?? null)
            || !is_int($configuration['gc_maxlifetime'] ?? null)
            || !is_string($savePath)
            || !is_dir($savePath)
            || !is_array($cookie)
            || ($cookie['lifetime'] ?? null) !== 0
            || !is_bool($cookie['secure'] ?? null)
            || ($cookie['http_only'] ?? null) !== true
            || !is_string($cookie['same_site'] ?? null)
            || !is_string($cookie['path'] ?? null)
            || !(is_string($cookie['domain'] ?? null) || ($cookie['domain'] ?? null) === null)
        ) {
            throw new RuntimeException('The native session configuration is invalid.');
        }

        if (
            preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D', $configuration['name']) !== 1
            || $configuration['inactivity_timeout'] <= 0
            || $configuration['absolute_lifetime'] < $configuration['inactivity_timeout']
            || $configuration['gc_maxlifetime'] < $configuration['absolute_lifetime']
            || !in_array($cookie['same_site'], ['Lax', 'Strict'], true)
            || !str_starts_with($cookie['path'], '/')
        ) {
            throw new RuntimeException('The native session configuration is invalid.');
        }

        $resolvedSavePath = realpath($savePath);

        if ($resolvedSavePath === false || $resolvedSavePath !== $savePath) {
            throw new RuntimeException('The native session save path is invalid.');
        }

        $probe = @tempnam($savePath, '.tgh-session-probe-');

        if (!is_string($probe)) {
            throw new RuntimeException('The private session directory is not writable.');
        }

        $resolvedProbe = realpath($probe);
        $probeDirectory = $resolvedProbe === false ? false : realpath(dirname($resolvedProbe));
        $caseInsensitivePaths = DIRECTORY_SEPARATOR === '\\';
        $comparableProbeDirectory = is_string($probeDirectory) && $caseInsensitivePaths
            ? strtolower($probeDirectory)
            : $probeDirectory;
        $comparableSavePath = $caseInsensitivePaths ? strtolower($resolvedSavePath) : $resolvedSavePath;

        if ($comparableProbeDirectory !== $comparableSavePath) {
            @unlink($probe);

            throw new RuntimeException('The private session directory is not writable.');
        }

        if (!@unlink($probe)) {
            throw new RuntimeException('The private session directory probe could not be removed.');
        }
    }

    private static function setIni(string $key, string $value): void
    {
        if (ini_set($key, $value) === false) {
            throw new RuntimeException(sprintf('The PHP setting %s could not be applied.', $key));
        }
    }
}
