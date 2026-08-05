<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/** Provides PDO persistence for retained Guest inquiries and outbound replies. */
final class PdoContactMessageRepository implements ContactMessageRepository
{
    use MapsRepositoryRows;

    private const CONTACT_SELECT = <<<'SQL'
        SELECT id, name, email, phone, subject, message, status, created_at, updated_at
        FROM contact_messages
        SQL;

    private const REPLY_SELECT = <<<'SQL'
        SELECT id, contact_message_id, reply_body, sent_by, delivery_status,
               sent_at, failure_code, created_at, updated_at
        FROM contact_message_replies
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(
        string $name,
        string $email,
        ?string $phone,
        string $subject,
        string $message,
        DateTimeImmutable $createdAt,
    ): ContactMessage {
        $this->requireTransaction('The contact message write failed.');

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO contact_messages '
                . '(name, email, phone, subject, message, status, created_at, updated_at) '
                . 'VALUES (:name, :email, :phone, :subject, :message, :status, :created_at, :updated_at)',
            );
            $timestamp = $createdAt->format('Y-m-d H:i:s');
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
                'status' => ContactMessage::STATUS_NEW,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $contact = $this->findById($this->generatedIdentifier());

            if (!$contact instanceof ContactMessage) {
                throw new RuntimeException('The created contact message could not be loaded.');
            }

            return $contact;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The contact message write failed.');
        }
    }

    public function findAll(?string $search = null): array
    {
        try {
            $sql = self::CONTACT_SELECT;
            $parameters = [];

            if ($search !== null) {
                $sql .= ' WHERE name LIKE :search_name ESCAPE \'!\' '
                    . 'OR email LIKE :search_email ESCAPE \'!\' '
                    . 'OR subject LIKE :search_subject ESCAPE \'!\'';
                $pattern = '%' . $this->escapeLike($search) . '%';
                $parameters = [
                    'search_name' => $pattern,
                    'search_email' => $pattern,
                    'search_subject' => $pattern,
                ];
            }

            $sql .= ' ORDER BY created_at DESC, id DESC';
            $statement = $this->connection->prepare($sql);
            $statement->execute($parameters);
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The contact message records could not be mapped.');
            }

            return array_map(function (mixed $row): ContactMessage {
                if (!is_array($row)) {
                    throw new RuntimeException('The contact message records could not be mapped.');
                }

                return $this->mapContact($row);
            }, $rows);
        } catch (Throwable) {
            throw new DatabaseException('The contact message listing failed.');
        }
    }

    public function findById(int $id): ?ContactMessage
    {
        return $this->findContact($id, false);
    }

    public function findByIdForUpdate(int $id): ?ContactMessage
    {
        $this->requireTransaction('The contact message lock failed.');

        return $this->findContact($id, true);
    }

    public function updateStatus(
        int $id,
        string $status,
        DateTimeImmutable $updatedAt,
    ): ContactMessage {
        $this->requireTransaction('The contact workflow update failed.');

        try {
            $statement = $this->connection->prepare(
                'UPDATE contact_messages '
                . 'SET status = :status, updated_at = :updated_at '
                . 'WHERE id = :id',
            );
            $statement->bindValue('status', $status);
            $statement->bindValue('updated_at', $updatedAt->format('Y-m-d H:i:s'));
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->execute();

            $contact = $this->findByIdForUpdate($id);

            if (!$contact instanceof ContactMessage || $contact->status() !== $status) {
                throw new RuntimeException('The updated contact message could not be loaded.');
            }

            return $contact;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The contact workflow update failed.');
        }
    }

    public function createReply(
        int $contactMessageId,
        string $replyBody,
        int $sentBy,
        DateTimeImmutable $createdAt,
    ): ContactMessageReply {
        $this->requireTransaction('The contact reply write failed.');

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO contact_message_replies '
                . '(contact_message_id, reply_body, sent_by, delivery_status, sent_at, failure_code, created_at, updated_at) '
                . 'VALUES (:contact_message_id, :reply_body, :sent_by, :delivery_status, NULL, NULL, :created_at, :updated_at)',
            );
            $timestamp = $createdAt->format('Y-m-d H:i:s');
            $statement->execute([
                'contact_message_id' => $contactMessageId,
                'reply_body' => $replyBody,
                'sent_by' => $sentBy,
                'delivery_status' => ContactMessageReply::STATUS_PENDING,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $reply = $this->findReplyById($this->generatedIdentifier());

            if (!$reply instanceof ContactMessageReply) {
                throw new RuntimeException('The created contact reply could not be loaded.');
            }

            return $reply;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The contact reply write failed.');
        }
    }

    public function markReplySent(
        int $replyId,
        DateTimeImmutable $sentAt,
    ): ContactMessageReply {
        return $this->finalizeReply(
            $replyId,
            ContactMessageReply::STATUS_SENT,
            $sentAt,
            null,
            'The contact reply delivery update failed.',
        );
    }

    public function markReplyFailed(
        int $replyId,
        string $failureCode,
        DateTimeImmutable $updatedAt,
    ): ContactMessageReply {
        return $this->finalizeReply(
            $replyId,
            ContactMessageReply::STATUS_FAILED,
            null,
            $failureCode,
            'The contact reply delivery update failed.',
            $updatedAt,
        );
    }

    public function findRepliesByContactMessageId(int $contactMessageId): array
    {
        if ($contactMessageId <= 0) {
            return [];
        }

        try {
            $statement = $this->connection->prepare(
                self::REPLY_SELECT
                . ' WHERE contact_message_id = :contact_message_id '
                . 'ORDER BY created_at DESC, id DESC',
            );
            $statement->bindValue('contact_message_id', $contactMessageId, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The contact reply records could not be mapped.');
            }

            return array_map(function (mixed $row): ContactMessageReply {
                if (!is_array($row)) {
                    throw new RuntimeException('The contact reply records could not be mapped.');
                }

                return $this->mapReply($row);
            }, $rows);
        } catch (Throwable) {
            throw new DatabaseException('The contact reply listing failed.');
        }
    }

    private function findContact(int $id, bool $forUpdate): ?ContactMessage
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::CONTACT_SELECT . ' WHERE id = :id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''),
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The contact message record could not be mapped.');
            }

            return $this->mapContact($row);
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException($forUpdate
                ? 'The contact message lock failed.'
                : 'The contact message lookup failed.');
        }
    }

    private function findReplyById(int $id): ?ContactMessageReply
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->connection->prepare(self::REPLY_SELECT . ' WHERE id = :id LIMIT 1');
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        if (!is_array($row)) {
            throw new RuntimeException('The contact reply record could not be mapped.');
        }

        return $this->mapReply($row);
    }

    private function finalizeReply(
        int $replyId,
        string $status,
        ?DateTimeImmutable $sentAt,
        ?string $failureCode,
        string $failureMessage,
        ?DateTimeImmutable $updatedAt = null,
    ): ContactMessageReply {
        $this->requireTransaction($failureMessage);
        $timestamp = ($updatedAt ?? $sentAt);

        if (!$timestamp instanceof DateTimeImmutable) {
            throw new DatabaseException($failureMessage);
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE contact_message_replies '
                . 'SET delivery_status = :delivery_status, sent_at = :sent_at, '
                . 'failure_code = :failure_code, updated_at = :updated_at '
                . 'WHERE id = :id AND delivery_status = :pending_status',
            );
            $statement->execute([
                'delivery_status' => $status,
                'sent_at' => $sentAt?->format('Y-m-d H:i:s'),
                'failure_code' => $failureCode,
                'updated_at' => $timestamp->format('Y-m-d H:i:s'),
                'id' => $replyId,
                'pending_status' => ContactMessageReply::STATUS_PENDING,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The reply was already finalized.');
            }

            $reply = $this->findReplyById($replyId);

            if (!$reply instanceof ContactMessageReply) {
                throw new RuntimeException('The finalized contact reply could not be loaded.');
            }

            return $reply;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException($failureMessage);
        }
    }

    /** @param array<string, mixed> $row */
    private function mapContact(array $row): ContactMessage
    {
        return new ContactMessage(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'name'),
            $this->string($row, 'email'),
            $this->nullableString($row, 'phone'),
            $this->string($row, 'subject'),
            $this->string($row, 'message'),
            $this->string($row, 'status'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function mapReply(array $row): ContactMessageReply
    {
        return new ContactMessageReply(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'contact_message_id'),
            $this->string($row, 'reply_body'),
            $this->positiveInteger($row, 'sent_by'),
            $this->string($row, 'delivery_status'),
            $this->nullableDate($row, 'sent_at'),
            $this->nullableString($row, 'failure_code'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }


    /** @param array<string, mixed> $row */
    private function nullableDate(array $row, string $key): ?DateTimeImmutable
    {
        return ($row[$key] ?? null) === null ? null : $this->dateTime($row, $key);
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated identifier is invalid.');
        }

        return $integer;
    }

    private function requireTransaction(string $message): void
    {
        if (!$this->connection->inTransaction()) {
            throw new DatabaseException($message);
        }
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
