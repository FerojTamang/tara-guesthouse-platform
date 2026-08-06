<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\Role;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Provides narrowly scoped persistence access to Version 1 Administrator accounts.
 */
final class UserRepository implements AdministratorAuthenticationRepository, AdministratorSessionRepository
{
    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            u.id AS user_id,
            u.full_name,
            u.username,
            u.email,
            u.phone,
            u.password AS password_hash,
            u.profile_image,
            u.last_login_at,
            u.account_status,
            u.created_at AS user_created_at,
            u.updated_at AS user_updated_at,
            u.deleted_at,
            r.id AS role_id,
            r.role_name,
            r.description AS role_description,
            r.created_at AS role_created_at,
            r.updated_at AS role_updated_at
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        SQL;

    /**
     * Creates the read-only repository with an explicit PDO connection.
     */
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * Finds a non-deleted Administrator by its numeric identifier.
     */
    public function findAdministratorById(int $id): ?User
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS
                . ' WHERE u.id = :lookup AND BINARY r.role_name = BINARY :administrator'
                . ' AND u.deleted_at IS NULL LIMIT 1',
            $id,
            PDO::PARAM_INT,
        );
    }

    /**
     * Finds a non-deleted Administrator by its exact supplied username value.
     */
    public function findAdministratorByUsername(string $username): ?User
    {
        return $this->lookup(
            self::SELECT_COLUMNS
                . ' WHERE u.username = :lookup AND BINARY r.role_name = BINARY :administrator'
                . ' AND u.deleted_at IS NULL LIMIT 1',
            $username,
            PDO::PARAM_STR,
        );
    }

    /**
     * Finds a non-deleted Administrator by its exact supplied email value.
     */
    public function findAdministratorByEmail(string $email): ?User
    {
        return $this->lookup(
            self::SELECT_COLUMNS
                . ' WHERE u.email = :lookup AND BINARY r.role_name = BINARY :administrator'
                . ' AND u.deleted_at IS NULL LIMIT 1',
            $email,
            PDO::PARAM_STR,
        );
    }

    /**
     * Atomically records one successful active-Administrator login.
     */
    public function recordSuccessfulLogin(
        int $userId,
        #[\SensitiveParameter] string $expectedPasswordHash,
        #[\SensitiveParameter] ?string $replacementPasswordHash,
        DateTimeImmutable $loggedInAt,
    ): void {
        if (
            $userId <= 0
            || $expectedPasswordHash === ''
            || (
                $replacementPasswordHash !== null
                && (
                    $replacementPasswordHash === ''
                    || (password_get_info($replacementPasswordHash)['algoName'] ?? 'unknown') === 'unknown'
                )
            )
            || $loggedInAt->getOffset() !== 0
            || $this->connection->inTransaction()
        ) {
            throw new DatabaseException('The Administrator login update failed.');
        }

        try {
            if (!$this->connection->beginTransaction()) {
                throw new RuntimeException('The Administrator login transaction could not start.');
            }

            $statement = $this->connection->prepare(
                'SELECT u.password AS password_hash, u.account_status, u.deleted_at, r.role_name '
                . 'FROM users u INNER JOIN roles r ON r.id = u.role_id '
                . 'WHERE u.id = :user_id LIMIT 1 FOR UPDATE',
            );
            $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
            $statement->execute();
            $row = $statement->fetch();

            if (
                !is_array($row)
                || !array_key_exists('password_hash', $row)
                || !array_key_exists('account_status', $row)
                || !array_key_exists('deleted_at', $row)
                || !array_key_exists('role_name', $row)
                || !is_string($row['password_hash'])
                || !is_string($row['account_status'])
                || $row['deleted_at'] !== null
                || !is_string($row['role_name'])
                || $row['role_name'] !== Role::ADMINISTRATOR
                || $row['account_status'] !== User::STATUS_ACTIVE
                || !hash_equals($row['password_hash'], $expectedPasswordHash)
            ) {
                throw new RuntimeException('The Administrator login state changed.');
            }

            $sql = 'UPDATE users SET last_login_at = :last_login_at';

            if ($replacementPasswordHash !== null) {
                $sql .= ', password = :replacement_password_hash';
            }

            $sql .= ' WHERE id = :user_id';
            $update = $this->connection->prepare($sql);
            $update->bindValue('last_login_at', $loggedInAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
            $update->bindValue('user_id', $userId, PDO::PARAM_INT);

            if ($replacementPasswordHash !== null) {
                $update->bindValue('replacement_password_hash', $replacementPasswordHash, PDO::PARAM_STR);
            }

            $update->execute();

            if ($update->rowCount() > 1) {
                throw new RuntimeException('The Administrator login update affected an invalid row count.');
            }

            $verification = $this->connection->prepare(
                'SELECT password, last_login_at FROM users WHERE id = :user_id LIMIT 1',
            );
            $verification->bindValue('user_id', $userId, PDO::PARAM_INT);
            $verification->execute();
            $verified = $verification->fetch();
            $expectedStoredHash = $replacementPasswordHash ?? $expectedPasswordHash;

            if (
                !is_array($verified)
                || !is_string($verified['password'] ?? null)
                || !is_string($verified['last_login_at'] ?? null)
                || !hash_equals($expectedStoredHash, $verified['password'])
                || $verified['last_login_at'] !== $loggedInAt->format('Y-m-d H:i:s')
            ) {
                throw new RuntimeException('The Administrator login update could not be verified.');
            }

            if (!$this->connection->commit()) {
                throw new RuntimeException('The Administrator login transaction could not commit.');
            }
        } catch (Throwable) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw new DatabaseException('The Administrator login update failed.');
        }
    }

    private function lookup(string $sql, int|string $value, int $type): ?User
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, $type);
            $statement->bindValue('administrator', Role::ADMINISTRATOR, PDO::PARAM_STR);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The user record could not be mapped.');
            }

            return $this->mapUser($row);
        } catch (Throwable) {
            throw new DatabaseException('The Administrator account lookup failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapUser(array $row): User
    {
        $role = new Role(
            $this->positiveInteger($row, 'role_id'),
            $this->string($row, 'role_name'),
            $this->nullableString($row, 'role_description'),
            $this->dateTime($row, 'role_created_at'),
            $this->dateTime($row, 'role_updated_at'),
        );

        return new User(
            $this->positiveInteger($row, 'user_id'),
            $role,
            $this->string($row, 'full_name'),
            $this->string($row, 'username'),
            $this->string($row, 'email'),
            $this->nullableString($row, 'phone'),
            $this->string($row, 'password_hash'),
            $this->nullableString($row, 'profile_image'),
            $this->nullableDateTime($row, 'last_login_at'),
            $this->string($row, 'account_status'),
            $this->dateTime($row, 'user_created_at'),
            $this->dateTime($row, 'user_updated_at'),
            $this->nullableDateTime($row, 'deleted_at'),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function positiveInteger(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            throw new RuntimeException('The user record could not be mapped.');
        }

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The user record could not be mapped.');
        }

        return $integer;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (!is_string($value)) {
            throw new RuntimeException('The user record could not be mapped.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value !== null && !is_string($value)) {
            throw new RuntimeException('The user record could not be mapped.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function dateTime(array $row, string $key): DateTimeImmutable
    {
        $value = $this->string($row, $key);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))) {
            throw new RuntimeException('The user record could not be mapped.');
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function nullableDateTime(array $row, string $key): ?DateTimeImmutable
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->dateTime($row, $key);
    }
}
