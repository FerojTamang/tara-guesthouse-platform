<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Notification;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/** Provides strict PDO persistence for Administrator notifications. */
final class PdoNotificationRepository implements NotificationRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT id, user_id, type, title, message, related_type, related_id, read_at, created_at
        FROM notifications
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $relatedType,
        ?int $relatedId,
        DateTimeImmutable $createdAt,
    ): Notification {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO notifications '
                . '(user_id, type, title, message, related_type, related_id, read_at, created_at) '
                . 'VALUES (:user_id, :type, :title, :message, :related_type, :related_id, NULL, :created_at)',
            );
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->bindValue('type', $type, PDO::PARAM_STR);
            $statement->bindValue('title', $title, PDO::PARAM_STR);
            $statement->bindValue('message', $message, PDO::PARAM_STR);
            $statement->bindValue('related_type', $relatedType, $relatedType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('related_id', $relatedId, $relatedId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('created_at', $createdAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $statement->execute();

            $notification = $this->findByIdForUser($this->generatedIdentifier(), $userId);

            if (!$notification instanceof Notification) {
                throw new RuntimeException('The created notification could not be loaded.');
            }

            return $notification;
        } catch (Throwable) {
            throw new DatabaseException('The notification write failed.');
        }
    }

    public function findPageForUser(int $userId, int $limit, int $offset): array
    {
        try {
            $statement = $this->connection->prepare(
                self::SELECT
                . ' WHERE user_id = :user_id '
                . 'ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset',
            );
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The notification records could not be mapped.');
            }

            $notifications = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The notification records could not be mapped.');
                }

                $notifications[] = $this->map($row);
            }

            return $notifications;
        } catch (Throwable) {
            throw new DatabaseException('The notification listing failed.');
        }
    }

    public function countForUser(int $userId): int
    {
        return $this->count('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id', $userId);
    }

    public function countUnreadForUser(int $userId): int
    {
        return $this->count(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL',
            $userId,
        );
    }

    public function findByIdForUser(int $id, int $userId): ?Notification
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::SELECT . ' WHERE id = :id AND user_id = :user_id LIMIT 1',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The notification record could not be mapped.');
            }

            return $this->map($row);
        } catch (Throwable) {
            throw new DatabaseException('The notification lookup failed.');
        }
    }

    public function markReadForUser(int $id, int $userId, DateTimeImmutable $readAt): ?Notification
    {
        try {
            $statement = $this->connection->prepare(
                'UPDATE notifications SET read_at = COALESCE(read_at, :read_at) '
                . 'WHERE id = :id AND user_id = :user_id',
            );
            $statement->bindValue('read_at', $readAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->execute();

            return $this->findByIdForUser($id, $userId);
        } catch (Throwable) {
            throw new DatabaseException('The notification read state could not be updated.');
        }
    }

    public function markAllReadForUser(int $userId, DateTimeImmutable $readAt): int
    {
        try {
            $statement = $this->connection->prepare(
                'UPDATE notifications SET read_at = :read_at '
                . 'WHERE user_id = :user_id AND read_at IS NULL',
            );
            $statement->bindValue('read_at', $readAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->execute();

            return $statement->rowCount();
        } catch (Throwable) {
            throw new DatabaseException('The notification read states could not be updated.');
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Notification
    {
        return new Notification(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'user_id'),
            $this->string($row, 'type'),
            $this->string($row, 'title'),
            $this->string($row, 'message'),
            $this->nullableString($row, 'related_type'),
            $this->nullablePositiveInteger($row, 'related_id'),
            $this->nullableDateTime($row, 'read_at'),
            $this->dateTime($row, 'created_at'),
        );
    }

    private function count(string $sql, int $userId): int
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->execute();
            $value = $statement->fetchColumn();

            if (!is_int($value) && !(is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) === 1)) {
                throw new RuntimeException('The notification count could not be mapped.');
            }

            $count = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

            if (!is_int($count)) {
                throw new RuntimeException('The notification count could not be mapped.');
            }

            return $count;
        } catch (Throwable) {
            throw new DatabaseException('The notification count could not be read.');
        }
    }

    /** @param array<string, mixed> $row */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->positiveInteger($row, $key);
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated notification identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated notification identifier is invalid.');
        }

        return $integer;
    }
}
