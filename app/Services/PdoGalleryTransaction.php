<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DatabaseException;
use PDO;
use Throwable;

/**
 * Provides sanitized PDO transaction controls for gallery administration.
 */
final readonly class PdoGalleryTransaction implements GalleryTransaction
{
    public function __construct(private PDO $connection)
    {
    }

    public function begin(): void
    {
        try {
            if ($this->connection->inTransaction() || !$this->connection->beginTransaction()) {
                throw new DatabaseException('The gallery transaction could not be started.');
            }
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The gallery transaction could not be started.');
        }
    }

    public function commit(): void
    {
        try {
            if (!$this->connection->inTransaction() || !$this->connection->commit()) {
                throw new DatabaseException('The gallery transaction could not be committed.');
            }
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The gallery transaction could not be committed.');
        }
    }

    public function rollBack(): void
    {
        try {
            if (!$this->connection->inTransaction() || !$this->connection->rollBack()) {
                throw new DatabaseException('The gallery transaction could not be rolled back.');
            }
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The gallery transaction could not be rolled back.');
        }
    }

    public function inProgress(): bool
    {
        try {
            return $this->connection->inTransaction();
        } catch (Throwable) {
            throw new DatabaseException('The gallery transaction state could not be read.');
        }
    }
}
