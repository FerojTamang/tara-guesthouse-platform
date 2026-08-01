<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CmsFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class CmsMigrationTest extends DatabaseTestCase
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
        '20260801000100_create_review_invitations_table',
        '20260801000200_create_reviews_table',
        '20260801000300_create_notifications_table',
        '20260801000400_add_operational_query_indexes',
        '20260802000100_add_activity_catalogue_homepage_curation',
    ];

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        );
    }

    public function testCmsMigrationsExposeApprovedColumnsIndexesAndActorForeignKeys(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());

        self::assertSame([
            'id', 'setting_key', 'setting_value', 'value_type', 'is_public', 'updated_by', 'created_at', 'updated_at',
        ], $this->columns('settings'));
        self::assertSame(['setting_key'], $this->indexColumns('settings', 'uq_settings_key'));
        self::assertSame(['is_public', 'setting_key', 'id'], $this->indexColumns('settings', 'idx_settings_public_key'));
        self::assertSame('users', $this->foreignReference('settings', 'fk_settings_updated_by'));
        self::assertSame('RESTRICT', $this->foreignDeleteRule('settings', 'fk_settings_updated_by'));

        self::assertSame([
            'id', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'status', 'published_at',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ], $this->columns('pages'));
        self::assertSame(['slug'], $this->indexColumns('pages', 'uq_pages_slug'));
        self::assertSame(['status', 'slug', 'id'], $this->indexColumns('pages', 'idx_pages_public_slug'));
        self::assertSame('users', $this->foreignReference('pages', 'fk_pages_created_by'));
        self::assertSame('users', $this->foreignReference('pages', 'fk_pages_updated_by'));
        self::assertSame('InnoDB', $this->tableOption('settings', 'ENGINE'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('pages', 'TABLE_COLLATION'));
    }

    public function testCmsConstraintsStatusPublicationAndActorRetentionAreEnforced(): void
    {
        $this->runner->migrate();
        $actorId = CmsFixture::insertAdministrator($this->connection, 'constraints');
        $settingId = CmsFixture::insertSetting($this->connection, $actorId);
        $pageId = CmsFixture::insertPage($this->connection, $actorId);

        self::assertGreaterThan(0, $settingId);
        self::assertGreaterThan(0, $pageId);

        foreach ([
            fn () => CmsFixture::insertSetting($this->connection, $actorId, ['setting_key' => 'Bad Key']),
            fn () => CmsFixture::insertSetting($this->connection, $actorId, ['setting_key' => 'contact.phone', 'value_type' => 'secret']),
            fn () => CmsFixture::insertSetting($this->connection, $actorId, ['setting_key' => 'contact.phone', 'is_public' => 2]),
            fn () => CmsFixture::insertPage($this->connection, $actorId, ['slug' => 'Bad Slug']),
            fn () => CmsFixture::insertPage($this->connection, $actorId, ['slug' => 'published-without-time', 'status' => 'published']),
            fn () => CmsFixture::insertPage($this->connection, $actorId, ['slug' => 'unknown-status', 'status' => 'scheduled']),
        ] as $operation) {
            $this->assertPdoFailure($operation);
        }

        $this->assertPdoFailure(fn () => $this->connection->exec('DELETE FROM users WHERE id = ' . $actorId));
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM settings WHERE id = ' . $settingId)->fetchColumn());
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM pages WHERE id = ' . $pageId)->fetchColumn());
    }

    public function testRollbackRemovesCmsTablesBeforeUsers(): void
    {
        $this->runner->migrate();
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->tableCount('pages'));
        self::assertSame(0, $this->tableCount('settings'));
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);
        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $statement->fetchAll());
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX',
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);
        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $statement->fetchAll());
    }

    private function foreignReference(string $table, string $constraint): string
    {
        $statement = $this->connection->prepare(
            'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND CONSTRAINT_NAME = :constraint_name',
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
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['table_name' => $table, 'constraint_name' => $constraint]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);
        return $value;
    }

    private function tableOption(string $table, string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT ' . $column . ' FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);
        $value = $statement->fetchColumn();
        self::assertIsString($value);
        return $value;
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid CMS data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function tableCount(string $table): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);
        return (int) $statement->fetchColumn();
    }
}
