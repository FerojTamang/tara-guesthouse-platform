<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Inserts deterministic CMS actors, settings and pages. */
final class CmsFixture
{
    public static function insertAdministrator(PDO $connection, string $suffix = 'cms'): int
    {
        $roleId = $connection->query(
            "SELECT id FROM roles WHERE role_name = 'Administrator' LIMIT 1",
        )->fetchColumn();

        if ($roleId === false) {
            $connection->prepare(
                'INSERT INTO roles (role_name, description) VALUES (:role_name, :description)',
            )->execute([
                'role_name' => 'Administrator',
                'description' => 'Version 1 Administrator',
            ]);
            $roleId = $connection->lastInsertId();
        }

        $roleId = (int) $roleId;

        $connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, password, account_status) '
            . 'VALUES (:role_id, :full_name, :username, :email, :password, :account_status)',
        )->execute([
            'role_id' => $roleId,
            'full_name' => 'CMS Administrator',
            'username' => 'cms.' . $suffix,
            'email' => 'cms.' . $suffix . '@example.test',
            'password' => password_hash('S4-009-Test-Password', PASSWORD_DEFAULT),
            'account_status' => 'active',
        ]);

        return (int) $connection->lastInsertId();
    }

    /** @param array<string, int|string> $overrides */
    public static function insertSetting(PDO $connection, int $actorId, array $overrides = []): int
    {
        $values = array_replace([
            'setting_key' => 'contact.email',
            'setting_value' => 'hello@example.test',
            'value_type' => 'email',
            'is_public' => 1,
            'updated_by' => $actorId,
            'created_at' => '2026-07-31 00:00:00',
            'updated_at' => '2026-07-31 00:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO settings (setting_key, setting_value, value_type, is_public, updated_by, created_at, updated_at) '
            . 'VALUES (:setting_key, :setting_value, :value_type, :is_public, :updated_by, :created_at, :updated_at)',
        );
        $statement->execute($values);

        return (int) $connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    public static function insertPage(PDO $connection, int $actorId, array $overrides = []): int
    {
        $values = array_replace([
            'title' => 'About Tara Guest House',
            'slug' => 'about',
            'content' => 'Welcome to Tara Guest House.',
            'meta_title' => 'About Tara Guest House',
            'meta_description' => 'Learn about Tara Guest House.',
            'status' => 'draft',
            'published_at' => null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'created_at' => '2026-07-31 00:00:00',
            'updated_at' => '2026-07-31 00:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO pages (title, slug, content, meta_title, meta_description, status, published_at, '
            . 'created_by, updated_by, created_at, updated_at) VALUES ('
            . ':title, :slug, :content, :meta_title, :meta_description, :status, :published_at, '
            . ':created_by, :updated_by, :created_at, :updated_at)',
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
