<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Inserts deterministic gallery categories and items into the isolated test database.
 */
final class GalleryFixture
{
    /** @param array<string, int|string> $overrides */
    public static function insertCategory(PDO $connection, array $overrides = []): int
    {
        $values = array_replace([
            'name' => 'Nature',
            'slug' => 'nature',
            'is_active' => 1,
            'created_at' => '2026-07-30 08:00:00',
            'updated_at' => '2026-07-30 08:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO gallery_categories (name, slug, is_active, created_at, updated_at) '
            . 'VALUES (:name, :slug, :is_active, :created_at, :updated_at)',
        );
        $statement->execute($values);

        return (int) $connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    public static function insertItem(PDO $connection, array $overrides = []): int
    {
        $values = array_replace([
            'gallery_category_id' => null,
            'title' => 'Riverside Sunrise',
            'caption' => 'Morning light beside the river.',
            'file_path' => 'gallery/0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef.jpg',
            'alt_text' => 'Sunrise over the river beside Tara Guest House',
            'sort_order' => 10,
            'is_active' => 1,
            'is_published' => 1,
            'created_at' => '2026-07-30 08:00:00',
            'updated_at' => '2026-07-30 08:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO gallery ('
            . 'gallery_category_id, title, caption, file_path, alt_text, sort_order, '
            . 'is_active, is_published, created_at, updated_at'
            . ') VALUES ('
            . ':gallery_category_id, :title, :caption, :file_path, :alt_text, :sort_order, '
            . ':is_active, :is_published, :created_at, :updated_at'
            . ')',
        );
        $statement->bindValue(
            'gallery_category_id',
            $values['gallery_category_id'],
            $values['gallery_category_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );

        foreach ([
            'title',
            'caption',
            'file_path',
            'alt_text',
            'sort_order',
            'is_active',
            'is_published',
            'created_at',
            'updated_at',
        ] as $key) {
            $value = $values[$key];
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
