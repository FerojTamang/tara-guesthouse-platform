<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ContactMessage;
use DateTimeImmutable;

/** Defines retained contact-message persistence without mutation or deletion. */
interface ContactMessageRepository
{
    public function create(
        string $name,
        string $email,
        ?string $phone,
        string $subject,
        string $message,
        DateTimeImmutable $createdAt,
    ): ContactMessage;

    /** @return list<ContactMessage> */
    public function findAll(?string $search = null): array;

    public function findById(int $id): ?ContactMessage;
}
