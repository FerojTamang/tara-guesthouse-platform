<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\Migration;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ActivityHomepageCurationMigrationTest extends DatabaseTestCase
{
    public function testLegacyRowsAreBackfilledAndSurviveDownAndReapply(): void
    {
        foreach ([
            '20260730000300_create_activities_table.php',
            '20260730000400_create_gallery_categories_table.php',
            '20260730000500_create_gallery_table.php',
        ] as $filename) {
            $this->migration($filename)->up($this->connection);
        }

        $this->connection->exec(
            "INSERT INTO activities (title, slug, description, price, is_active, is_published, sort_order) "
            . "VALUES ('Legacy Walk', 'legacy-walk', 'Legacy activity description.', 700.00, 1, 1, 9)",
        );
        $this->connection->exec(
            "INSERT INTO gallery (title, file_path, alt_text, sort_order, is_active, is_published) "
            . "VALUES ('Legacy Image', 'gallery/legacy.jpg', 'Legacy image', 7, 1, 1)",
        );

        $migration = $this->migration('20260802000100_add_activity_catalogue_homepage_curation.php');
        $migration->up($this->connection);

        self::assertSame(
            ['Legacy activity description.', 1, 9],
            $this->connection->query(
                "SELECT summary, is_homepage_featured, homepage_sort_order FROM activities WHERE slug = 'legacy-walk'",
            )->fetch(\PDO::FETCH_NUM),
        );
        self::assertSame(
            [1, 7],
            $this->connection->query(
                "SELECT is_homepage_featured, homepage_sort_order FROM gallery WHERE title = 'Legacy Image'",
            )->fetch(\PDO::FETCH_NUM),
        );

        $this->connection->exec("UPDATE activities SET price = NULL WHERE slug = 'legacy-walk'");

        $migration->down($this->connection);
        self::assertSame('Legacy Walk', $this->connection->query(
            "SELECT title FROM activities WHERE slug = 'legacy-walk'",
        )->fetchColumn());
        self::assertSame('0.00', $this->connection->query(
            "SELECT price FROM activities WHERE slug = 'legacy-walk'",
        )->fetchColumn());
        self::assertSame('Legacy Image', $this->connection->query(
            "SELECT title FROM gallery WHERE title = 'Legacy Image'",
        )->fetchColumn());
        self::assertFalse($this->columnExists('activities', 'summary'));
        self::assertFalse($this->columnExists('gallery', 'is_homepage_featured'));

        $migration->up($this->connection);
        self::assertTrue($this->columnExists('activities', 'summary'));
        self::assertTrue($this->columnExists('gallery', 'is_homepage_featured'));
    }

    private function migration(string $filename): Migration
    {
        $migration = require dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'database'
            . DIRECTORY_SEPARATOR . 'migrations'
            . DIRECTORY_SEPARATOR . $filename;
        self::assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
        );
        $statement->execute(['table_name' => $table, 'column_name' => $column]);

        return (int) $statement->fetchColumn() === 1;
    }
}
