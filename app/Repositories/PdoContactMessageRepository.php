<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\ContactMessage;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/** Provides PDO persistence for retained Guest inquiries. */
final class PdoContactMessageRepository implements ContactMessageRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT id, name, email, phone, subject, message, status, created_at, updated_at
        FROM contact_messages
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
        if (!$this->connection->inTransaction()) {
            throw new DatabaseException('The contact message write failed.');
        }

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO contact_messages '
                . '(name, email, phone, subject, message, status, created_at, updated_at) '
                . 'VALUES (:name, :email, :phone, :subject, :message, NULL, :created_at, :updated_at)',
            );
            $timestamp = $createdAt->format('Y-m-d H:i:s');
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
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
            $sql = self::SELECT;
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

            $contacts = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The contact message records could not be mapped.');
                }

                $contacts[] = $this->map($row);
            }

            return $contacts;
        } catch (Throwable) {
            throw new DatabaseException('The contact message listing failed.');
        }
    }

    public function findById(int $id): ?ContactMessage
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                self::SELECT . ' WHERE id = :id LIMIT 1',
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

            return $this->map($row);
        } catch (Throwable) {
            throw new DatabaseException('The contact message lookup failed.');
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): ContactMessage
    {
        return new ContactMessage(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'name'),
            $this->string($row, 'email'),
            $this->nullableString($row, 'phone'),
            $this->string($row, 'subject'),
            $this->string($row, 'message'),
            $this->nullableString($row, 'status'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated contact message identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated contact message identifier is invalid.');
        }

        return $integer;
    }

    private function escapeLike(string $value): string
    {
        return strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
