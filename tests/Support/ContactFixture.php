<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Inserts deterministic retained contact messages. */
final class ContactFixture
{
    /** @param array<string, string|null> $overrides */
    public static function insert(PDO $connection, array $overrides = []): int
    {
        $values = array_replace([
            'name' => 'Asha Guest',
            'email' => 'asha@example.test',
            'phone' => '+977 9800000000',
            'subject' => 'Airport transfer question',
            'message' => 'Could you please help arrange an airport transfer?',
            'status' => 'new',
            'created_at' => '2026-07-31 12:00:00',
            'updated_at' => '2026-07-31 12:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO contact_messages '
            . '(name, email, phone, subject, message, status, created_at, updated_at) '
            . 'VALUES (:name, :email, :phone, :subject, :message, :status, :created_at, :updated_at)',
        );

        foreach ($values as $key => $value) {
            $statement->bindValue($key, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        }

        $statement->execute();

        return (int) $connection->lastInsertId();
    }
}
