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
     * @param array<string, int|string|null> $overrides
     */
    public static function insert(PDO $connection, array $overrides = []): int
    {
        $values = array_replace([
            'title' => 'Jungle Walk',
            'slug' => 'jungle-walk',
            'summary' => 'Explore the forest with a local guide.',
            'description' => 'Explore the surrounding forest with a local guide.',
            'featured_image_path' => null,
            'price' => '2500.00',
            'price_unit' => 'per person',
            'duration_label' => '3 hours',
            'location' => 'Dhulikhel',
            'is_active' => 1,
            'is_published' => 1,
            'is_homepage_featured' => 0,
            'homepage_sort_order' => 0,
            'sort_order' => 10,
            'created_at' => '2026-07-30 07:00:00',
            'updated_at' => '2026-07-30 07:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO activities ('
            . 'title, slug, summary, description, featured_image_path, price, price_unit, '
            . 'duration_label, location, is_active, is_published, is_homepage_featured, '
            . 'homepage_sort_order, sort_order, '
            . 'created_at, updated_at'
            . ') VALUES ('
            . ':title, :slug, :summary, :description, :featured_image_path, :price, :price_unit, '
            . ':duration_label, :location, :is_active, :is_published, :is_homepage_featured, '
            . ':homepage_sort_order, :sort_order, '
            . ':created_at, :updated_at'
            . ')',
        );
        $statement->execute($values);

        return (int) $connection->lastInsertId();
    }
}
