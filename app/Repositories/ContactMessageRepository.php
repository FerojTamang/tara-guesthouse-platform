<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use DateTimeImmutable;

/** Defines retained inquiry persistence and controlled Administrator replies. */
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

    public function findByIdForUpdate(int $id): ?ContactMessage;

    public function updateStatus(
        int $id,
        string $status,
        DateTimeImmutable $updatedAt,
    ): ContactMessage;

    public function createReply(
        int $contactMessageId,
        string $replyBody,
        int $sentBy,
        DateTimeImmutable $createdAt,
    ): ContactMessageReply;

    public function markReplySent(
        int $replyId,
        DateTimeImmutable $sentAt,
    ): ContactMessageReply;

    public function markReplyFailed(
        int $replyId,
        string $failureCode,
        DateTimeImmutable $updatedAt,
    ): ContactMessageReply;

    /** @return list<ContactMessageReply> */
    public function findRepliesByContactMessageId(int $contactMessageId): array;
}
