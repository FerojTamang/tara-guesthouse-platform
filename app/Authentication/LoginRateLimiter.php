<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Exceptions\AdministratorAuthenticationException;
use JsonException;
use Throwable;

/**
 * Stores failed Administrator-login counters in private hashed-IP files.
 */
final class LoginRateLimiter
{
    private const STATE_VERSION = 1;

    private const LOCK_FILE = 'rate-limiter.lock';

    /**
     * @param array<string, mixed> $policy
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $policy,
    ) {
        $this->assertPolicy();
        $this->ensureDirectory();
    }

    public function inspect(string $remoteAddress, ?int $now = null): LoginRateLimitState
    {
        $timestamp = $now ?? time();
        $this->assertTimestamp($timestamp);
        $path = $this->bucketPath($remoteAddress);

        return $this->withDirectoryLock(function () use ($path, $timestamp): LoginRateLimitState {
            $state = $this->readStateFile($path);
            $normalized = $this->normalizeState($state, $timestamp);

            if ($normalized === $this->emptyState()) {
                $this->removeStateFile($path);
            } elseif ($normalized !== $state) {
                $this->writeStateFile($path, $normalized);
            }

            return $this->toPublicState($normalized, $timestamp);
        });
    }

    public function recordFailure(string $remoteAddress, ?int $now = null): LoginRateLimitState
    {
        $timestamp = $now ?? time();
        $this->assertTimestamp($timestamp);
        $path = $this->bucketPath($remoteAddress);

        return $this->withDirectoryLock(function () use ($path, $timestamp): LoginRateLimitState {
            $state = $this->normalizeState($this->readStateFile($path), $timestamp);

            if ($state['blocked_until'] <= $timestamp) {
                if ($state['failures'] === 0) {
                    $state['first_failure_at'] = $timestamp;
                }

                $state['failures']++;

                if ($state['failures'] >= $this->policy['max_failed_attempts']) {
                    $state['blocked_until'] = $timestamp + $this->policy['block_duration_seconds'];
                }
            }

            $this->writeStateFile($path, $state);

            return $this->toPublicState($state, $timestamp);
        });
    }

    public function clear(string $remoteAddress): void
    {
        $path = $this->bucketPath($remoteAddress);

        $this->withDirectoryLock(function () use ($path): null {
            $this->removeStateFile($path);

            return null;
        });
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function withDirectoryLock(callable $operation): mixed
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . self::LOCK_FILE;

        if (is_link($path)) {
            throw AdministratorAuthenticationException::failure();
        }

        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            throw AdministratorAuthenticationException::failure();
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw AdministratorAuthenticationException::failure();
            }

            try {
                if (is_link($path)) {
                    throw AdministratorAuthenticationException::failure();
                }

                return $operation();
            } finally {
                flock($handle, LOCK_UN);
            }
        } catch (AdministratorAuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdministratorAuthenticationException::failure($throwable);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{version: int, failures: int, first_failure_at: int, blocked_until: int}
     */
    private function readStateFile(string $path): array
    {
        if (is_link($path)) {
            throw AdministratorAuthenticationException::failure();
        }

        if (!file_exists($path)) {
            return $this->emptyState();
        }

        if (!is_file($path)) {
            throw AdministratorAuthenticationException::failure();
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw AdministratorAuthenticationException::failure();
        }

        try {
            $state = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw AdministratorAuthenticationException::failure($exception);
        }

        if (
            !is_array($state)
            || array_keys($state) !== ['version', 'failures', 'first_failure_at', 'blocked_until']
            || $state['version'] !== self::STATE_VERSION
            || !is_int($state['failures'])
            || !is_int($state['first_failure_at'])
            || !is_int($state['blocked_until'])
            || $state['failures'] <= 0
            || $state['failures'] > $this->policy['max_failed_attempts']
            || $state['first_failure_at'] <= 0
            || $state['blocked_until'] < 0
            || ($state['blocked_until'] > 0 && $state['failures'] !== $this->policy['max_failed_attempts'])
            || ($state['failures'] === $this->policy['max_failed_attempts'] && $state['blocked_until'] === 0)
        ) {
            throw AdministratorAuthenticationException::failure();
        }

        return $state;
    }

    /**
     * @param array{version: int, failures: int, first_failure_at: int, blocked_until: int} $state
     */
    private function writeStateFile(string $path, array $state): void
    {
        if (is_link($path)) {
            throw AdministratorAuthenticationException::failure();
        }

        try {
            $encoded = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw AdministratorAuthenticationException::failure($exception);
        }

        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            throw AdministratorAuthenticationException::failure();
        }

        try {
            if (
                is_link($path)
                || ftruncate($handle, 0) === false
                || fseek($handle, 0) !== 0
                || fwrite($handle, $encoded) !== strlen($encoded)
                || fflush($handle) === false
            ) {
                throw AdministratorAuthenticationException::failure();
            }
        } finally {
            fclose($handle);
        }
    }

    private function removeStateFile(string $path): void
    {
        if (is_link($path)) {
            throw AdministratorAuthenticationException::failure();
        }

        if (!file_exists($path)) {
            return;
        }

        if (!is_file($path) || !@unlink($path)) {
            throw AdministratorAuthenticationException::failure();
        }
    }

    /**
     * @param array{version: int, failures: int, first_failure_at: int, blocked_until: int} $state
     * @return array{version: int, failures: int, first_failure_at: int, blocked_until: int}
     */
    private function normalizeState(array $state, int $timestamp): array
    {
        if ($state['blocked_until'] > 0 && $state['blocked_until'] <= $timestamp) {
            return $this->emptyState();
        }

        if (
            $state['blocked_until'] === 0
            && $state['first_failure_at'] > 0
            && $state['first_failure_at'] + $this->policy['attempt_window_seconds'] <= $timestamp
        ) {
            return $this->emptyState();
        }

        return $state;
    }

    /**
     * @param array{version: int, failures: int, first_failure_at: int, blocked_until: int} $state
     */
    private function toPublicState(array $state, int $timestamp): LoginRateLimitState
    {
        if ($state['blocked_until'] > $timestamp) {
            return new LoginRateLimitState(
                true,
                0,
                $state['blocked_until'] - $timestamp,
            );
        }

        return new LoginRateLimitState(
            false,
            max(0, $this->policy['max_failed_attempts'] - $state['failures']),
            0,
        );
    }

    private function bucketPath(string $remoteAddress): string
    {
        $packedAddress = filter_var($remoteAddress, FILTER_VALIDATE_IP) !== false
            ? inet_pton($remoteAddress)
            : false;

        if ($packedAddress === false) {
            throw AdministratorAuthenticationException::failure();
        }

        return $this->directory
            . DIRECTORY_SEPARATOR
            . hash('sha256', $packedAddress)
            . '.json';
    }

    private function ensureDirectory(): void
    {
        if (is_link($this->directory)) {
            throw AdministratorAuthenticationException::failure();
        }

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw AdministratorAuthenticationException::failure();
        }

        if (is_link($this->directory) || !is_writable($this->directory)) {
            throw AdministratorAuthenticationException::failure();
        }

        $resolvedDirectory = realpath($this->directory);
        $resolvedPublicPath = defined('PUBLIC_PATH') ? realpath((string) PUBLIC_PATH) : false;

        if (
            $resolvedDirectory === false
            || (
                is_string($resolvedPublicPath)
                && ($resolvedDirectory === $resolvedPublicPath
                    || str_starts_with($resolvedDirectory, $resolvedPublicPath . DIRECTORY_SEPARATOR))
            )
        ) {
            throw AdministratorAuthenticationException::failure();
        }
    }

    private function assertPolicy(): void
    {
        foreach ([
            'max_failed_attempts',
            'attempt_window_seconds',
            'block_duration_seconds',
        ] as $key) {
            if (!isset($this->policy[$key]) || !is_int($this->policy[$key]) || $this->policy[$key] <= 0) {
                throw AdministratorAuthenticationException::failure();
            }
        }
    }

    private function assertTimestamp(int $timestamp): void
    {
        if ($timestamp < 0) {
            throw AdministratorAuthenticationException::failure();
        }
    }

    /**
     * @return array{version: int, failures: int, first_failure_at: int, blocked_until: int}
     */
    private function emptyState(): array
    {
        return [
            'version' => self::STATE_VERSION,
            'failures' => 0,
            'first_failure_at' => 0,
            'blocked_until' => 0,
        ];
    }
}
