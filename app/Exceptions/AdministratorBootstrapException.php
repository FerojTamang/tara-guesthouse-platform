<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Reports a safe Administrator-bootstrap failure to the CLI boundary.
 */
final class AdministratorBootstrapException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $exitCode,
        private readonly bool $systemFailure,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function usage(string $message): self
    {
        return new self($message, 2, false);
    }

    public static function workflow(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 1, false, $previous);
    }

    public static function failure(?Throwable $previous = null): self
    {
        return new self('Administrator bootstrap failed.', 1, true, $previous);
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function isSystemFailure(): bool
    {
        return $this->systemFailure;
    }
}
