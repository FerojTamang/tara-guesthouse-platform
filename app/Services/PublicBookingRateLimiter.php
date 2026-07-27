<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\BookingRateLimitState;
use JsonException;
use Throwable;

/**
 * Stores public booking-attempt counters in private, hashed-IP files.
 */
final class PublicBookingRateLimiter
{
    private const STATE_VERSION = 1;

    private const LOCK_FILE = 'rate-limiter.lock';

    /**
     * @param array{
     *     max_attempts: int,
     *     attempt_window_seconds: int,
     *     block_duration_seconds: int
     * } $policy
     */
    public function __construct(
        private readonly string $directory,
        private readonly array $policy,
    ) {
        try {
            $this->assertPolicy();
            $this->ensureDirectory();
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    public function consume(
        string $remoteAddress,
        ?int $now = null,
    ): BookingRateLimitState {
        try {
            $timestamp = $now ?? time();
            $this->assertTimestamp($timestamp);
            $path = $this->bucketPath($remoteAddress);

            return $this->withDirectoryLock(
                function () use ($path, $timestamp): BookingRateLimitState {
                    $state = $this->normalizeState(
                        $this->readStateFile($path),
                        $timestamp,
                    );

                    if ($state['blocked_until'] > $timestamp) {
                        $this->writeStateFile($path, $state);

                        return $this->publicState($state, $timestamp);
                    }

                    if ($state['attempts'] >= $this->policy['max_attempts']) {
                        $state['blocked_until'] = $timestamp
                            + $this->policy['block_duration_seconds'];
                        $this->writeStateFile($path, $state);

                        return $this->publicState($state, $timestamp);
                    }

                    if ($state['attempts'] === 0) {
                        $state['window_started_at'] = $timestamp;
                    }

                    $state['attempts']++;
                    $this->writeStateFile($path, $state);

                    return $this->publicState($state, $timestamp);
                },
            );
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    /**
     * @return array{
     *     version: int,
     *     attempts: int,
     *     window_started_at: int,
     *     blocked_until: int
     * }
     */
    private function emptyState(): array
    {
        return [
            'version' => self::STATE_VERSION,
            'attempts' => 0,
            'window_started_at' => 0,
            'blocked_until' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array{
     *     version: int,
     *     attempts: int,
     *     window_started_at: int,
     *     blocked_until: int
     * }
     */
    private function normalizeState(array $state, int $timestamp): array
    {
        if ($state === []) {
            return $this->emptyState();
        }

        if (
            array_keys($state) !== [
                'version',
                'attempts',
                'window_started_at',
                'blocked_until',
            ]
            || $state['version'] !== self::STATE_VERSION
            || !is_int($state['attempts'])
            || !is_int($state['window_started_at'])
            || !is_int($state['blocked_until'])
            || $state['attempts'] < 0
            || $state['window_started_at'] < 0
            || $state['blocked_until'] < 0
        ) {
            throw BookingException::failure();
        }

        if ($state['blocked_until'] > $timestamp) {
            return $state;
        }

        if (
            $state['window_started_at'] === 0
            || $timestamp - $state['window_started_at']
                >= $this->policy['attempt_window_seconds']
        ) {
            return $this->emptyState();
        }

        $state['blocked_until'] = 0;

        return $state;
    }

    /**
     * @param array{
     *     version: int,
     *     attempts: int,
     *     window_started_at: int,
     *     blocked_until: int
     * } $state
     */
    private function publicState(
        array $state,
        int $timestamp,
    ): BookingRateLimitState {
        $blocked = $state['blocked_until'] > $timestamp;

        return new BookingRateLimitState(
            $blocked,
            $blocked
                ? 0
                : max(
                    0,
                    $this->policy['max_attempts'] - $state['attempts'],
                ),
            $blocked ? $state['blocked_until'] - $timestamp : 0,
        );
    }

    private function assertPolicy(): void
    {
        if (
            array_keys($this->policy) !== [
                'max_attempts',
                'attempt_window_seconds',
                'block_duration_seconds',
            ]
            || $this->policy['max_attempts'] <= 0
            || $this->policy['attempt_window_seconds'] <= 0
            || $this->policy['block_duration_seconds'] <= 0
        ) {
            throw BookingException::failure();
        }
    }

    private function ensureDirectory(): void
    {
        if (
            (!is_dir($this->directory)
                && !mkdir($this->directory, 0700, true)
                && !is_dir($this->directory))
            || !is_writable($this->directory)
        ) {
            throw BookingException::failure();
        }

        @chmod($this->directory, 0700);
    }

    private function assertTimestamp(int $timestamp): void
    {
        if ($timestamp < 0) {
            throw BookingException::failure();
        }
    }

    private function bucketPath(string $remoteAddress): string
    {
        if (filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
            throw BookingException::failure();
        }

        return $this->directory
            . DIRECTORY_SEPARATOR
            . hash('sha256', $remoteAddress)
            . '.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function readStateFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if (!is_string($contents)) {
            throw BookingException::failure();
        }

        try {
            $state = json_decode(
                $contents,
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $failure) {
            throw BookingException::failure($failure);
        }

        if (!is_array($state) || array_is_list($state)) {
            throw BookingException::failure();
        }

        return $state;
    }

    /**
     * @param array<string, int> $state
     */
    private function writeStateFile(string $path, array $state): void
    {
        try {
            $encoded = json_encode(
                $state,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $failure) {
            throw BookingException::failure($failure);
        }

        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));

        if (
            file_put_contents($temporary, $encoded, LOCK_EX) === false
            || !@chmod($temporary, 0600)
            || !rename($temporary, $path)
        ) {
            @unlink($temporary);
            throw BookingException::failure();
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function withDirectoryLock(callable $operation): mixed
    {
        $lockPath = $this->directory
            . DIRECTORY_SEPARATOR
            . self::LOCK_FILE;
        $handle = fopen($lockPath, 'c+b');

        if ($handle === false) {
            throw BookingException::failure();
        }

        try {
            @chmod($lockPath, 0600);

            if (!flock($handle, LOCK_EX)) {
                throw BookingException::failure();
            }

            try {
                return $operation();
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
