<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ActivityFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ActivityMigrationTest extends DatabaseTestCase
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

    public function testActivityMigrationExposesApprovedSchemaAndIndexes(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());

        self::assertSame([
            'id',
            'title',
            'slug',
            'description',
            'price',
            'is_active',
            'is_published',
            'sort_order',
            'created_at',
            'updated_at',
        ], $this->columns('activities'));
        self::assertSame(
            ['bigint', 'NO', 'PRI', null, 'auto_increment', 'unsigned'],
            $this->column('activities', 'id'),
        );
        self::assertSame(
            ['varchar', 'NO', '', null, '', '200'],
            $this->column('activities', 'title', includeLength: true),
        );
        self::assertSame(
            ['varchar', 'NO', 'UNI', null, '', '200'],
            $this->column('activities', 'slug', includeLength: true),
        );
        self::assertSame(['text', 'NO', '', null, '', null], $this->column('activities', 'description'));
        self::assertSame(
            ['decimal', 'NO', '', null, '', '12,2'],
            $this->column('activities', 'price', includePrecision: true),
        );
        self::assertSame(['tinyint', 'NO', 'MUL', null, '', null], $this->column('activities', 'is_active'));
        self::assertSame(['tinyint', 'NO', '', null, '', null], $this->column('activities', 'is_published'));
        self::assertSame(['int', 'NO', 'MUL', '0', '', 'unsigned'], $this->column('activities', 'sort_order'));
        self::assertSame('InnoDB', $this->tableOption('activities', 'ENGINE'));
        self::assertSame(
            'utf8mb4_unicode_ci',
            $this->tableOption('activities', 'TABLE_COLLATION'),
        );
        self::assertSame(['slug'], $this->indexColumns('activities', 'uq_activities_slug'));
        self::assertSame([
            'is_active',
            'is_published',
            'sort_order',
            'id',
        ], $this->indexColumns('activities', 'idx_activities_public_order'));
        self::assertSame(
            ['sort_order', 'id'],
            $this->indexColumns('activities', 'idx_activities_admin_order'),
        );
    }

    public function testActivityConstraintsAndIndependentVisibilityFlagsAreEnforced(): void
    {
        $this->runner->migrate();
        $activityId = ActivityFixture::insert($this->connection);

        self::assertGreaterThan(0, $activityId);
        self::assertSame('2500.00', $this->connection->query(
            'SELECT price FROM activities WHERE id = ' . $activityId,
        )->fetchColumn());

        $this->connection->exec(
            'UPDATE activities SET is_active = 0 WHERE id = ' . $activityId,
        );
        self::assertSame(['0', '1'], $this->visibilityState($activityId));

        $this->connection->exec(
            'UPDATE activities SET is_published = 0 WHERE id = ' . $activityId,
        );
        self::assertSame(['0', '0'], $this->visibilityState($activityId));

        foreach ([
            ['slug' => 'jungle-walk'],
            ['title' => ' '],
            ['title' => ' Untrimmed title'],
            ['slug' => 'Uppercase-Slug'],
            ['description' => ' '],
            ['description' => 'Untrimmed description '],
            ['price' => '-0.01'],
            ['is_active' => 2],
            ['is_published' => 2],
        ] as $override) {
            $this->assertPdoFailure(
                fn () => ActivityFixture::insert(
                    $this->connection,
                    array_replace(['slug' => 'fixture-' . bin2hex(random_bytes(4))], $override),
                ),
            );
        }

        self::assertGreaterThan(0, ActivityFixture::insert($this->connection, [
            'title' => 'Free Bird Watching',
            'slug' => 'free-bird-watching',
            'price' => '0.00',
            'sort_order' => 0,
        ]));
    }

    public function testActivityMigrationRollsBackAndReappliesCleanly(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->activityTableCount());
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(1, $this->activityTableCount());
    }

    /** @return array{string, string} */
    private function visibilityState(int $activityId): array
    {
        $row = $this->connection->query(
            'SELECT is_active, is_published FROM activities WHERE id = ' . $activityId,
        )->fetch();
        self::assertIsArray($row);

        return [(string) $row['is_active'], (string) $row['is_published']];
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

    /**
     * @return array{string, string, string, ?string, string, ?string}
     */
    private function column(
        string $table,
        string $column,
        bool $includeLength = false,
        bool $includePrecision = false,
    ): array {
        $statement = $this->connection->prepare(
            'SELECT DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE, '
            . 'CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE '
            . 'FROM information_schema.COLUMNS '
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
        } elseif ($includePrecision) {
            $detail = sprintf('%s,%s', $row['NUMERIC_PRECISION'], $row['NUMERIC_SCALE']);
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

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid activity data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function activityTableCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities'",
        )->fetchColumn();
    }
}
