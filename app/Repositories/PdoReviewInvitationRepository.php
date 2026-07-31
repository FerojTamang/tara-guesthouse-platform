<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Exceptions\ReviewException;
use App\Models\ReviewInvitation;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Provides PDO persistence for review invitations. */
final class PdoReviewInvitationRepository implements ReviewInvitationRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT id, booking_id, token_hash, expires_at, used_at, created_at
        FROM review_invitations
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(
        int $bookingId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): ReviewInvitation {
        if (!$this->connection->inTransaction()) {
            throw new DatabaseException('The review invitation write failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO review_invitations '
                . '(booking_id, token_hash, expires_at, used_at, created_at) '
                . 'VALUES (:booking_id, :token_hash, :expires_at, NULL, :created_at)',
            );
            $statement->execute([
                'booking_id' => $bookingId,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ]);
            $invitation = $this->findById($this->generatedIdentifier());

            if (!$invitation instanceof ReviewInvitation) {
                throw new RuntimeException('The created review invitation could not be loaded.');
            }

            return $invitation;
        } catch (PDOException $exception) {
            if ($this->isDuplicate($exception)) {
                throw ReviewException::invitationConflict($exception);
            }

            throw new DatabaseException('The review invitation write failed.');
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The review invitation write failed.');
        }
    }

    public function findById(int $id): ?ReviewInvitation
    {
        return $id > 0
            ? $this->lookup(self::SELECT . ' WHERE id = :lookup LIMIT 1', $id, PDO::PARAM_INT)
            : null;
    }

    public function findByBookingId(int $bookingId): ?ReviewInvitation
    {
        return $bookingId > 0
            ? $this->lookup(self::SELECT . ' WHERE booking_id = :lookup LIMIT 1', $bookingId, PDO::PARAM_INT)
            : null;
    }

    public function findByTokenHash(string $tokenHash): ?ReviewInvitation
    {
        return $this->validTokenHash($tokenHash)
            ? $this->lookup(self::SELECT . ' WHERE token_hash = :lookup LIMIT 1', $tokenHash, PDO::PARAM_STR)
            : null;
    }

    public function lockByTokenHash(string $tokenHash): ?ReviewInvitation
    {
        if (!$this->connection->inTransaction()) {
            throw new DatabaseException('The review invitation lock failed.');
        }

        return $this->validTokenHash($tokenHash)
            ? $this->lookup(
                self::SELECT . ' WHERE token_hash = :lookup LIMIT 1 FOR UPDATE',
                $tokenHash,
                PDO::PARAM_STR,
                'The review invitation lock failed.',
            )
            : null;
    }

    public function markUsed(int $invitationId, DateTimeImmutable $usedAt): ?ReviewInvitation
    {
        if (!$this->connection->inTransaction()) {
            throw new DatabaseException('The review invitation use update failed.');
        }

        if ($invitationId <= 0 || $usedAt->getOffset() !== 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE review_invitations SET used_at = :used_at '
                . 'WHERE id = :id AND used_at IS NULL AND expires_at > :used_at_guard',
            );
            $timestamp = $usedAt->format('Y-m-d H:i:s');
            $statement->execute([
                'id' => $invitationId,
                'used_at' => $timestamp,
                'used_at_guard' => $timestamp,
            ]);

            return $statement->rowCount() === 1 ? $this->findById($invitationId) : null;
        } catch (Throwable) {
            throw new DatabaseException('The review invitation use update failed.');
        }
    }

    private function lookup(
        string $sql,
        int|string $value,
        int $type,
        string $failureMessage = 'The review invitation lookup failed.',
    ): ?ReviewInvitation {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, $type);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The review invitation record could not be mapped.');
            }

            return $this->map($row);
        } catch (Throwable) {
            throw new DatabaseException($failureMessage);
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): ReviewInvitation
    {
        return new ReviewInvitation(
            $this->positiveInteger($row, 'id'),
            $this->positiveInteger($row, 'booking_id'),
            $this->string($row, 'token_hash'),
            $this->dateTime($row, 'expires_at'),
            $this->nullableDateTime($row, 'used_at'),
            $this->dateTime($row, 'created_at'),
        );
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated review invitation identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated review invitation identifier is invalid.');
        }

        return $integer;
    }

    private function validTokenHash(string $tokenHash): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $tokenHash) === 1;
    }

    private function isDuplicate(PDOException $exception): bool
    {
        return ($exception->errorInfo[0] ?? $exception->getCode()) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
