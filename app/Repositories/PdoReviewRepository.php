<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Exceptions\ReviewException;
use App\Models\Review;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Provides PDO persistence for verified-stay reviews. */
final class PdoReviewRepository implements ReviewRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT
            id,
            booking_id,
            review_invitation_id,
            guest_name_snapshot,
            rating,
            title,
            comment,
            review_status,
            moderated_by,
            moderated_at,
            moderation_note,
            created_at,
            updated_at
        FROM reviews
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function createPending(
        int $bookingId,
        int $reviewInvitationId,
        string $guestNameSnapshot,
        int $rating,
        ?string $title,
        string $comment,
        DateTimeImmutable $createdAt,
    ): Review {
        if (!$this->connection->inTransaction()) {
            throw new DatabaseException('The review write failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO reviews ('
                . 'booking_id, review_invitation_id, guest_name_snapshot, rating, title, comment, '
                . 'review_status, moderated_by, moderated_at, moderation_note, created_at, updated_at'
                . ') VALUES ('
                . ':booking_id, :review_invitation_id, :guest_name_snapshot, :rating, :title, :comment, '
                . "'pending', NULL, NULL, NULL, :created_at, :updated_at)"
            );
            $timestamp = $createdAt->format('Y-m-d H:i:s');
            $statement->execute([
                'booking_id' => $bookingId,
                'review_invitation_id' => $reviewInvitationId,
                'guest_name_snapshot' => $guestNameSnapshot,
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $review = $this->findById($this->generatedIdentifier());

            if (!$review instanceof Review) {
                throw new RuntimeException('The created review could not be loaded.');
            }

            return $review;
        } catch (PDOException $exception) {
            if ($this->isDuplicate($exception)) {
                throw ReviewException::reviewConflict($exception);
            }

            throw new DatabaseException('The review write failed.');
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The review write failed.');
        }
    }

    public function findById(int $id): ?Review
    {
        return $id > 0
            ? $this->lookup(self::SELECT . ' WHERE id = :lookup LIMIT 1', $id, PDO::PARAM_INT)
            : null;
    }

    public function findByBookingId(int $bookingId): ?Review
    {
        return $bookingId > 0
            ? $this->lookup(self::SELECT . ' WHERE booking_id = :lookup LIMIT 1', $bookingId, PDO::PARAM_INT)
            : null;
    }

    public function findByInvitationId(int $reviewInvitationId): ?Review
    {
        return $reviewInvitationId > 0
            ? $this->lookup(
                self::SELECT . ' WHERE review_invitation_id = :lookup LIMIT 1',
                $reviewInvitationId,
                PDO::PARAM_INT,
            )
            : null;
    }

    private function lookup(string $sql, int $value, int $type): ?Review
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, $type);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The review record could not be mapped.');
            }

            return $this->map($row);
        } catch (Throwable) {
            throw new DatabaseException('The review lookup failed.');
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Review
    {
        return new Review(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'booking_id'),
            $this->positiveInteger($row, 'review_invitation_id'),
            $this->string($row, 'guest_name_snapshot'),
            $this->positiveInteger($row, 'rating'),
            $this->nullableString($row, 'title'),
            $this->string($row, 'comment'),
            $this->string($row, 'review_status'),
            $this->nullablePositiveInteger($row, 'moderated_by'),
            $this->nullableDateTime($row, 'moderated_at'),
            $this->nullableString($row, 'moderation_note'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        return ($row[$key] ?? null) === null ? null : $this->positiveInteger($row, $key);
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated review identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated review identifier is invalid.');
        }

        return $integer;
    }

    private function isDuplicate(PDOException $exception): bool
    {
        return ($exception->errorInfo[0] ?? $exception->getCode()) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
