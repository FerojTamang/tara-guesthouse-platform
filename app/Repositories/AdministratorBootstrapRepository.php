<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\AdministratorBootstrapException;
use App\Models\Role;
use App\Models\User;
use PDO;
use Throwable;

/**
 * Provides only the persistence operations required for first-Administrator bootstrap.
 */
class AdministratorBootstrapRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * Executes one service-defined bootstrap workflow in a new transaction.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function withinTransaction(callable $operation): mixed
    {
        if ($this->connection->inTransaction()) {
            throw AdministratorBootstrapException::failure();
        }

        $started = false;

        try {
            if (!$this->connection->beginTransaction()) {
                throw AdministratorBootstrapException::failure();
            }

            $started = true;
            $result = $operation();

            if (!$this->connection->commit()) {
                throw AdministratorBootstrapException::failure();
            }

            $started = false;

            return $result;
        } catch (AdministratorBootstrapException $exception) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        } catch (Throwable $throwable) {
            if ($started && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw AdministratorBootstrapException::failure($throwable);
        } finally {
            if ($started && $this->connection->inTransaction()) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable) {
                    // Preserve the safe bootstrap failure; never expose connection details.
                }
            }
        }
    }

    /**
     * Locks and validates the one exact role prerequisite.
     */
    public function lockAdministratorRole(): int
    {
        try {
            $statement = $this->connection->prepare(
                'SELECT id, role_name, description FROM roles '
                . 'WHERE role_name = :role_name LIMIT 1 FOR UPDATE',
            );
            $statement->execute(['role_name' => Role::ADMINISTRATOR]);
            $role = $statement->fetch();

            if (
                !is_array($role)
                || ($role['role_name'] ?? null) !== Role::ADMINISTRATOR
                || ($role['description'] ?? null) !== null
            ) {
                throw AdministratorBootstrapException::workflow(
                    'Administrator role prerequisite is missing or invalid.',
                );
            }

            return $this->positiveInteger($role['id'] ?? null);
        } catch (AdministratorBootstrapException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdministratorBootstrapException::failure($throwable);
        }
    }

    /**
     * Returns every stored user assigned to the locked Administrator role.
     *
     * @return list<array{id: int, username: string, email: string, account_status: string, deleted: bool}>
     */
    public function administratorUsers(int $roleId): array
    {
        try {
            $statement = $this->connection->prepare(
                'SELECT id, username, email, account_status, deleted_at FROM users '
                . 'WHERE role_id = :role_id ORDER BY id FOR UPDATE',
            );
            $statement->bindValue('role_id', $roleId, PDO::PARAM_INT);
            $statement->execute();

            return array_map($this->mapUserFact(...), $statement->fetchAll());
        } catch (AdministratorBootstrapException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdministratorBootstrapException::failure($throwable);
        }
    }

    /**
     * Returns username/email collisions across every role and deleted state.
     *
     * @return list<array{id: int, username: string, email: string, account_status: string, deleted: bool}>
     */
    public function identityCollisions(string $username, string $email): array
    {
        try {
            $statement = $this->connection->prepare(
                'SELECT id, username, email, account_status, deleted_at FROM users '
                . 'WHERE username = :username OR email = :email ORDER BY id FOR UPDATE',
            );
            $statement->execute(['username' => $username, 'email' => $email]);

            return array_map($this->mapUserFact(...), $statement->fetchAll());
        } catch (AdministratorBootstrapException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdministratorBootstrapException::failure($throwable);
        }
    }

    /**
     * Inserts one active Administrator and returns its generated identifier.
     */
    public function insertAdministrator(
        int $roleId,
        string $fullName,
        string $username,
        string $email,
        ?string $phone,
        #[\SensitiveParameter] string $passwordHash,
    ): int {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO users ('
                . 'role_id, full_name, username, email, phone, password, profile_image, '
                . 'last_login_at, account_status, deleted_at'
                . ') VALUES ('
                . ':role_id, :full_name, :username, :email, :phone, :password, NULL, NULL, :status, NULL'
                . ')',
            );
            $statement->bindValue('role_id', $roleId, PDO::PARAM_INT);
            $statement->bindValue('full_name', $fullName, PDO::PARAM_STR);
            $statement->bindValue('username', $username, PDO::PARAM_STR);
            $statement->bindValue('email', $email, PDO::PARAM_STR);
            $statement->bindValue('phone', $phone, $phone === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('password', $passwordHash, PDO::PARAM_STR);
            $statement->bindValue('status', User::STATUS_ACTIVE, PDO::PARAM_STR);
            $statement->execute();

            if ($statement->rowCount() !== 1) {
                throw AdministratorBootstrapException::failure();
            }

            return $this->positiveInteger($this->connection->lastInsertId());
        } catch (AdministratorBootstrapException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdministratorBootstrapException::failure($throwable);
        }
    }

    /**
     * @param mixed $row
     * @return array{id: int, username: string, email: string, account_status: string, deleted: bool}
     */
    private function mapUserFact(mixed $row): array
    {
        if (
            !is_array($row)
            || !is_string($row['username'] ?? null)
            || !is_string($row['email'] ?? null)
            || !is_string($row['account_status'] ?? null)
            || (($row['deleted_at'] ?? null) !== null && !is_string($row['deleted_at']))
        ) {
            throw AdministratorBootstrapException::failure();
        }

        return [
            'id' => $this->positiveInteger($row['id'] ?? null),
            'username' => $row['username'],
            'email' => $row['email'],
            'account_status' => $row['account_status'],
            'deleted' => $row['deleted_at'] !== null,
        ];
    }

    private function positiveInteger(mixed $value): int
    {
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            throw AdministratorBootstrapException::failure();
        }

        if (!is_int($integer) || $integer <= 0) {
            throw AdministratorBootstrapException::failure();
        }

        return $integer;
    }
}
