<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Represents one sanitized transactional-email failure. */
final class MailException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly string $category,
    ) {
        parent::__construct($message);
    }

    public static function configuration(): self
    {
        return new self('Transactional email is not configured.', 'configuration');
    }

    public static function template(): self
    {
        return new self('Transactional email could not be prepared.', 'template');
    }

    public static function transport(): self
    {
        return new self('Transactional email delivery failed.', 'transport');
    }

    public static function failure(): self
    {
        return new self('Transactional email operation failed.', 'system');
    }

    public function isConfigurationFailure(): bool
    {
        return $this->category === 'configuration';
    }

    public function isTemplateFailure(): bool
    {
        return $this->category === 'template';
    }

    public function isTransportFailure(): bool
    {
        return $this->category === 'transport';
    }

    public function isSystemFailure(): bool
    {
        return $this->category === 'system';
    }
}
