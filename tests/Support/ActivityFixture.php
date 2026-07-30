<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Inserts deterministic activity records into the isolated test database.
 */
final class ActivityFixture
{
    /**
     * @param array<string, int|string> $overrides
     */
    public static function insert(PDO $connection, array $overrides = []): int
    {
        $values = array_replace([
            'title' => 'Jungle Walk',
            'slug' => 'jungle-walk',
            'description' => 'Explore the surrounding forest with a local guide.',
            'price' => '2500.00',
            'is_active' => 1,
            'is_published' => 1,
            'sort_order' => 10,
            'created_at' => '2026-07-30 07:00:00',
            'updated_at' => '2026-07-30 07:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO activities ('
            . 'title, slug, description, price, is_active, is_published, sort_order, '
            . 'created_at, updated_at'
            . ') VALUES ('
            . ':title, :slug, :description, :price, :is_active, :is_published, :sort_order, '
            . ':created_at, :updated_at'
            . ')',
        );
        $statement->execute($values);

        return (int) $connection->lastInsertId();
    }
}
