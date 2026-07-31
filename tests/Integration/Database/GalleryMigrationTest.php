<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\GalleryFixture;

#[Group('database')]
final class GalleryMigrationTest extends DatabaseTestCase
{
    private const APPLICATION_MIGRATIONS = [
        '20260721000100_create_roles_table',
        '20260721000200_create_users_table',
        '20260722000100_create_room_types_table',
        '20260722000200_create_rooms_table',
        '20260722000300_create_room_images_table',
        '20260722000400_create_amenities_table',
        '20260722000500_create_room_amenities_table',
        '20260723000100_add_room_image_persistence_constraints',
        '20260726000100_create_bookings_table',
        '20260726000200_create_booking_rooms_table',
        '20260726000300_create_booking_events_table',
        '20260727000100_create_audit_logs_table',
        '20260728000100_create_payment_methods_table',
        '20260728000200_create_payments_table',
        '20260730000100_create_invoices_table',
        '20260730000200_create_invoice_items_table',
        '20260730000300_create_activities_table',
        '20260730000400_create_gallery_categories_table',
        '20260730000500_create_gallery_table',
        '20260731000100_create_settings_table',
        '20260731000200_create_pages_table',
        '20260731000300_create_contact_messages_table',
    ];

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR . 'database'
                . DIRECTORY_SEPARATOR . 'migrations',
        );
    }

    public function testGalleryMigrationsExposeApprovedSchemaIndexesAndForeignKey(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());

        self::assertSame([
            'id', 'name', 'slug', 'is_active', 'created_at', 'updated_at',
        ], $this->columns('gallery_categories'));
        self::assertSame(
            ['bigint', 'NO', 'PRI', null, 'auto_increment', 'unsigned'],
            $this->column('gallery_categories', 'id'),
        );
        self::assertSame(
            ['varchar', 'NO', 'UNI', null, '', '150'],
            $this->column('gallery_categories', 'name', includeLength: true),
        );
        self::assertSame(
            ['varchar', 'NO', 'UNI', null, '', '200'],
            $this->column('gallery_categories', 'slug', includeLength: true),
        );
        self::assertSame(
            ['tinyint', 'NO', 'MUL', null, '', null],
            $this->column('gallery_categories', 'is_active'),
        );
        self::assertSame(['name'], $this->indexColumns('gallery_categories', 'uq_gallery_categories_name'));
        self::assertSame(['slug'], $this->indexColumns('gallery_categories', 'uq_gallery_categories_slug'));
        self::assertSame(
            ['is_active', 'name', 'id'],
            $this->indexColumns('gallery_categories', 'idx_gallery_categories_active_order'),
        );

        self::assertSame([
            'id',
            'gallery_category_id',
            'title',
            'caption',
            'file_path',
            'alt_text',
            'sort_order',
            'is_active',
            'is_published',
            'created_at',
            'updated_at',
        ], $this->columns('gallery'));
        self::assertSame(
            ['bigint', 'YES', 'MUL', 'NULL', '', 'unsigned'],
            $this->column('gallery', 'gallery_category_id'),
        );
        self::assertSame(
            ['varchar', 'NO', '', null, '', '200'],
            $this->column('gallery', 'title', includeLength: true),
        );
        self::assertSame(['text', 'YES', '', 'NULL', '', null], $this->column('gallery', 'caption'));
        self::assertSame(
            ['varchar', 'NO', '', null, '', '500'],
            $this->column('gallery', 'file_path', includeLength: true),
        );
        self::assertSame(
            ['varchar', 'NO', '', null, '', '500'],
            $this->column('gallery', 'alt_text', includeLength: true),
        );
        self::assertSame(['int', 'NO', 'MUL', '0', '', 'unsigned'], $this->column('gallery', 'sort_order'));
        self::assertSame(['tinyint', 'NO', 'MUL', null, '', null], $this->column('gallery', 'is_active'));
        self::assertSame(['tinyint', 'NO', '', null, '', null], $this->column('gallery', 'is_published'));
        self::assertSame(
            ['is_active', 'is_published', 'sort_order', 'id'],
            $this->indexColumns('gallery', 'idx_gallery_public_order'),
        );
        self::assertSame(
            ['gallery_category_id', 'is_active', 'is_published', 'sort_order', 'id'],
            $this->indexColumns('gallery', 'idx_gallery_category_public_order'),
        );
        self::assertSame('gallery_categories', $this->foreignReference('gallery', 'fk_gallery_category'));
        self::assertSame('SET NULL', $this->foreignDeleteRule('gallery', 'fk_gallery_category'));
        self::assertSame('InnoDB', $this->tableOption('gallery_categories', 'ENGINE'));
        self::assertSame('InnoDB', $this->tableOption('gallery', 'ENGINE'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('gallery', 'TABLE_COLLATION'));
    }

    public function testGalleryConstraintsNullableCategoryAndRemovalRetentionAreEnforced(): void
    {
        $this->runner->migrate();
        $categoryId = GalleryFixture::insertCategory($this->connection);
        $itemId = GalleryFixture::insertItem($this->connection, [
            'gallery_category_id' => $categoryId,
        ]);

        self::assertGreaterThan(0, $itemId);
        self::assertSame((string) $categoryId, (string) $this->connection->query(
            'SELECT gallery_category_id FROM gallery WHERE id = ' . $itemId,
        )->fetchColumn());

        $this->connection->exec('DELETE FROM gallery_categories WHERE id = ' . $categoryId);
        self::assertSame('1', (string) $this->connection->query(
            'SELECT gallery_category_id IS NULL FROM gallery WHERE id = ' . $itemId,
        )->fetchColumn());

        self::assertGreaterThan(0, GalleryFixture::insertItem($this->connection, [
            'title' => 'Uncategorized Courtyard',
            'caption' => null,
            'file_path' => 'gallery/uncategorized-courtyard.webp',
            'alt_text' => 'Guest house courtyard',
        ]));

        foreach ([
            ['name' => ' '],
            ['name' => ' Nature '],
            ['slug' => 'Uppercase'],
            ['is_active' => 2],
        ] as $override) {
            $this->assertPdoFailure(
                fn () => GalleryFixture::insertCategory(
                    $this->connection,
                    array_replace(['name' => 'Category ' . bin2hex(random_bytes(3)), 'slug' => 'category-' . bin2hex(random_bytes(3))], $override),
                ),
            );
        }

        foreach ([
            ['title' => ' '],
            ['caption' => ' '],
            ['file_path' => '/gallery/image.jpg'],
            ['file_path' => 'gallery/../image.jpg'],
            ['file_path' => 'gallery//image.jpg'],
            ['alt_text' => ' '],
            ['sort_order' => -1],
            ['is_active' => 2],
            ['is_published' => 2],
            ['gallery_category_id' => 999999],
        ] as $override) {
            $this->assertPdoFailure(
                fn () => GalleryFixture::insertItem(
                    $this->connection,
                    array_replace([
                        'title' => 'Fixture ' . bin2hex(random_bytes(3)),
                        'file_path' => 'gallery/' . bin2hex(random_bytes(16)) . '.jpg',
                    ], $override),
                ),
            );
        }
    }

    public function testGalleryMigrationsRollbackAndReapplyCleanly(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->tableCount('gallery'));
        self::assertSame(0, $this->tableCount('gallery_categories'));
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(1, $this->tableCount('gallery'));
        self::assertSame(1, $this->tableCount('gallery_categories'));
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    /** @return array{string, string, string, ?string, string, ?string} */
    private function column(
        string $table,
        string $column,
        bool $includeLength = false,
    ): array {
        $statement = $this->connection->prepare(
            'SELECT DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE, '
            . 'CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND COLUMN_NAME = :column_name',
        );
        $statement->execute(['table_name' => $table, 'column_name' => $column]);
        $row = $statement->fetch();
        self::assertIsArray($row);
        $detail = null;

        if ($includeLength) {
            $detail = $row['CHARACTER_MAXIMUM_LENGTH'] === null
                ? null
                : (string) $row['CHARACTER_MAXIMUM_LENGTH'];
        } elseif (str_contains((string) $row['COLUMN_TYPE'], 'unsigned')) {
            $detail = 'unsigned';
        }

        return [
            (string) $row['DATA_TYPE'],
            (string) $row['IS_NULLABLE'],
            (string) $row['COLUMN_KEY'],
            $row['COLUMN_DEFAULT'] === null ? null : (string) $row['COLUMN_DEFAULT'],
            (string) $row['EXTRA'],
            $detail,
        ];
    }

    private function tableOption(string $table, string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT ' . $column . ' AS value FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX',
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    private function foreignReference(string $table, string $constraint): string
    {
        $statement = $this->connection->prepare(
            'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['table_name' => $table, 'constraint_name' => $constraint]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }

    private function foreignDeleteRule(string $table, string $constraint): string
    {
        $statement = $this->connection->prepare(
            'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['table_name' => $table, 'constraint_name' => $constraint]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);

        return $value;
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid gallery data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function tableCount(string $table): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);

        return (int) $statement->fetchColumn();
    }
}
