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
        '20260804000100_add_contact_reply_workflow',
        '20260804220000_create_professional_content_foundation',
        '20260805000100_create_navigation_items_table',
        '20260805000200_add_advanced_publishing_controls',
        '20260805000300_expand_navigation_routes_for_publishing',
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

        self::assertSame([
            'id', 'storage_path', 'original_name', 'mime_type', 'file_extension',
            'file_size_bytes', 'width_pixels', 'height_pixels', 'checksum_sha256',
            'title', 'alt_text', 'caption', 'uploaded_by', 'created_at', 'updated_at', 'deleted_at',
            'active_checksum_sha256',
        ], $this->columns('content_media'));
        self::assertSame(['storage_path'], $this->indexColumns('content_media', 'uq_content_media_storage_path'));
        self::assertSame(['active_checksum_sha256'], $this->indexColumns('content_media', 'uq_content_media_active_checksum'));
        self::assertSame('users', $this->foreignReference('content_media', 'fk_content_media_uploaded_by'));

        self::assertSame([
            'page_id', 'content_type', 'summary', 'notice_style', 'publish_at', 'expires_at',
            'is_featured', 'is_pinned', 'featured_media_id', 'social_title', 'social_description',
            'social_media_id', 'seo_noindex', 'updated_by', 'created_at', 'updated_at',
        ], $this->columns('page_content_profiles'));
        self::assertSame(
            ['content_type', 'is_pinned', 'is_featured', 'publish_at', 'expires_at', 'page_id'],
            $this->indexColumns('page_content_profiles', 'idx_page_content_publishing'),
        );
        self::assertSame('pages', $this->foreignReference('page_content_profiles', 'fk_page_content_profile_page'));
        self::assertSame('content_media', $this->foreignReference('page_content_profiles', 'fk_page_content_profile_featured_media'));
        self::assertSame('CASCADE', $this->foreignDeleteRule('page_content_profiles', 'fk_page_content_profile_page'));

        self::assertSame(['page_id', 'media_id', 'block_key', 'sort_order', 'created_at'], $this->columns('page_block_media'));
        self::assertSame('pages', $this->foreignReference('page_block_media', 'fk_page_block_media_page'));
        self::assertSame('content_media', $this->foreignReference('page_block_media', 'fk_page_block_media_asset'));

        self::assertSame([
            'id', 'location', 'label', 'link_type', 'route_key', 'page_id', 'parent_id',
            'sort_order', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at',
        ], $this->columns('navigation_items'));
        self::assertSame(
            ['location', 'is_active', 'parent_id', 'sort_order', 'id'],
            $this->indexColumns('navigation_items', 'idx_navigation_items_public'),
        );
        self::assertSame('pages', $this->foreignReference('navigation_items', 'fk_navigation_items_page'));
        self::assertSame('navigation_items', $this->foreignReference('navigation_items', 'fk_navigation_items_parent'));
        self::assertSame(7, (int) $this->connection->query('SELECT COUNT(*) FROM navigation_items')->fetchColumn());
        $this->connection->exec(
            "INSERT INTO navigation_items (location, label, link_type, route_key, sort_order, is_active) VALUES "
            . "('main', 'News', 'route', 'news', 80, 1), "
            . "('main', 'Notices', 'route', 'notices', 90, 1)",
        );
        self::assertSame(2, (int) $this->connection->query(
            "SELECT COUNT(*) FROM navigation_items WHERE route_key IN ('news', 'notices')",
        )->fetchColumn());
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
            fn () => $this->connection->exec(
                "INSERT INTO navigation_items (location, label, link_type, route_key, sort_order, is_active) "
                . "VALUES ('main', 'Unknown', 'route', 'unknown', 100, 1)",
            ),
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
        self::assertSame(0, $this->tableCount('navigation_items'));
        self::assertSame(0, $this->tableCount('page_block_media'));
        self::assertSame(0, $this->tableCount('page_content_profiles'));
        self::assertSame(0, $this->tableCount('content_media'));
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
