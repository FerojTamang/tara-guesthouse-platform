<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class PublicRoomCatalogException extends RuntimeException
{
    private function __construct(private readonly bool $notFound, ?Throwable $previous = null)
    {
        parent::__construct($notFound ? 'Public room not found.' : 'Public room catalogue failed.', 0, $previous);
    }

    public static function notFound(): self { return new self(true); }
    public static function failure(?Throwable $previous = null): self { return new self(false, $previous); }
    public function isNotFound(): bool { return $this->notFound; }
}
