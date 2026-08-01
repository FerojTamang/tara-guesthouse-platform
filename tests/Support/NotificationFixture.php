<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Inserts deterministic Administrator notification fixtures. */
final class NotificationFixture
{
    public static function insertAdministrator(PDO $connection, string $label = 'notification'): int
    {
        $connection->exec(
            "INSERT INTO roles (role_name, description) VALUES ('Administrator', 'Notification fixture') "
            . "ON DUPLICATE KEY UPDATE role_name = VALUES(role_name)",
        );
        $roleId = (int) $connection->query(
            "SELECT id FROM roles WHERE role_name = 'Administrator' LIMIT 1",
        )->fetchColumn();
        $suffix = bin2hex(random_bytes(5));
        $statement = $connection->prepare(
            'INSERT INTO users '
            . '(role_id, full_name, username, email, password, account_status) '
            . "VALUES (:role_id, :full_name, :username, :email, :password, 'active')",
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => ucfirst($label) . ' Administrator',
            'username' => $label . '.' . $suffix,
            'email' => $label . '.' . $suffix . '@example.test',
            'password' => password_hash('Notification-Test-Password-123!', PASSWORD_DEFAULT),
        ]);

        return (int) $connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    public static function insert(PDO $connection, int $userId, array $overrides = []): int
    {
        $values = array_replace([
            'user_id' => $userId,
            'type' => 'contact_message_received',
            'title' => 'New contact message',
            'message' => 'A Guest submitted a new contact message.',
            'related_type' => 'contact_message',
            'related_id' => 1,
            'read_at' => null,
            'created_at' => '2026-08-01 06:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO notifications '
            . '(user_id, type, title, message, related_type, related_id, read_at, created_at) '
            . 'VALUES (:user_id, :type, :title, :message, :related_type, :related_id, :read_at, :created_at)',
        );

        foreach ($values as $key => $value) {
            $statement->bindValue(
                $key,
                $value,
                $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR),
            );
        }

        $statement->execute();

        return (int) $connection->lastInsertId();
    }
}
