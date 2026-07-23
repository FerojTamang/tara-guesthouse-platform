<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class RoomImageException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $category,
        ?Throwable $previous = null,
        private readonly ?Throwable $cleanupFailure = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function roomNotFound(): self
    {
        return new self('The requested room was not found.', 'not_found');
    }

    public static function limitReached(int $maximum): self
    {
        return new self(
            sprintf('This room already has the maximum of %d images.', $maximum),
            'limit',
        );
    }

    public static function storage(?Throwable $previous = null): self
    {
        return new self('Room image storage failed.', 'storage', $previous);
    }

    public static function system(?Throwable $previous = null): self
    {
        return new self('Room image operation failed.', 'system', $previous);
    }

    public static function cleanup(Throwable $operationFailure, Throwable $cleanupFailure): self
    {
        return new self('Room image cleanup failed.', 'cleanup', $operationFailure, $cleanupFailure);
    }

    public function isNotFound(): bool { return $this->category === 'not_found'; }
    public function isLimit(): bool { return $this->category === 'limit'; }
    public function isStorageFailure(): bool { return $this->category === 'storage'; }
    public function isSystemFailure(): bool { return $this->category === 'system'; }
    public function isCleanupFailure(): bool { return $this->category === 'cleanup'; }
    public function cleanupCause(): ?Throwable { return $this->cleanupFailure; }
}
