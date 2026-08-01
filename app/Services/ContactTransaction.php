<?php

declare(strict_types=1);

namespace App\Services;

/** Defines transaction controls for contact-message submission. */
interface ContactTransaction
{
    public function begin(): void;
    public function commit(): void;
    public function rollBack(): void;
    public function inProgress(): bool;
}
