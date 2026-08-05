<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ContactFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ContactMigrationTest extends DatabaseTestCase
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

    public function testMigrationExposesControlledInquiryAndReplySchema(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame([
            'id', 'name', 'email', 'phone', 'subject', 'message', 'status', 'created_at', 'updated_at',
        ], $this->columns('contact_messages'));
        self::assertSame(
            ['created_at', 'id'],
            $this->indexColumns('contact_messages', 'idx_contact_messages_created'),
        );
        self::assertSame(
            ['status', 'created_at', 'id'],
            $this->indexColumns('contact_messages', 'idx_contact_messages_status_created'),
        );
        self::assertSame(
            ['email', 'created_at', 'id'],
            $this->indexColumns('contact_messages', 'idx_contact_messages_email_created'),
        );
        self::assertSame('NO', $this->columnNullability('contact_messages', 'status'));
        self::assertSame('new', $this->columnDefault('contact_messages', 'status'));
        self::assertSame('InnoDB', $this->tableOption('contact_messages', 'ENGINE'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('contact_messages', 'TABLE_COLLATION'));

        self::assertSame([
            'id',
            'contact_message_id',
            'reply_body',
            'sent_by',
            'delivery_status',
            'sent_at',
            'failure_code',
            'created_at',
            'updated_at',
        ], $this->columns('contact_message_replies'));
        self::assertSame(
            ['contact_message_id', 'created_at', 'id'],
            $this->indexColumns('contact_message_replies', 'idx_contact_replies_message_created'),
        );
        self::assertSame(
            ['delivery_status', 'created_at', 'id'],
            $this->indexColumns('contact_message_replies', 'idx_contact_replies_delivery_created'),
        );
        self::assertSame(
            ['sent_by'],
            $this->indexColumns('contact_message_replies', 'idx_contact_replies_sent_by'),
        );
        self::assertSame('InnoDB', $this->tableOption('contact_message_replies', 'ENGINE'));
        self::assertSame(
            'utf8mb4_unicode_ci',
            $this->tableOption('contact_message_replies', 'TABLE_COLLATION'),
        );
    }

    public function testConstraintsRequireControlledWorkflowStateAndRetainOldMessages(): void
    {
        $this->runner->migrate();
        $oldId = ContactFixture::insert($this->connection, [
            'status' => 'new',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        self::assertGreaterThan(0, $oldId);
        self::assertSame('1', (string) $this->connection->query(
            'SELECT COUNT(*) FROM contact_messages WHERE id = ' . $oldId,
        )->fetchColumn());

        foreach (['new', 'in_progress', 'replied', 'closed'] as $status) {
            self::assertGreaterThan(0, ContactFixture::insert($this->connection, ['status' => $status]));
        }

        foreach ([
            ['name' => ' Asha'],
            ['email' => ' guest@example.test'],
            ['phone' => str_repeat('1', 21)],
            ['subject' => 'No'],
            ['message' => 'Too short'],
            ['status' => null],
            ['status' => 'pending'],
            ['status' => 'Awaiting Reply'],
        ] as $overrides) {
            $this->assertPdoFailure(fn () => ContactFixture::insert($this->connection, $overrides));
        }
    }

    public function testRollbackRemovesContactTablesBeforeDependencies(): void
    {
        $this->runner->migrate();
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->tableCount('contact_message_replies'));
        self::assertSame(0, $this->tableCount('contact_messages'));
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX',
        );
        $statement->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    private function columnNullability(string $table, string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
        );
        $statement->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (string) $statement->fetchColumn();
    }

    private function columnDefault(string $table, string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
        );
        $statement->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        $default = (string) $statement->fetchColumn();

        // MariaDB versions differ when exposing string defaults through
        // information_schema: some return new, while newer releases return
        // the SQL literal 'new'. Normalize only a quoted string literal so
        // the contract verifies the semantic default rather than driver
        // presentation details.
        if (strlen($default) >= 2 && $default[0] === "'" && $default[-1] === "'") {
            return str_replace("''", "'", substr($default, 1, -1));
        }

        return $default;
    }

    private function tableOption(string $table, string $column): string
    {
        if (!in_array($column, ['ENGINE', 'TABLE_COLLATION'], true)) {
            self::fail('Unsafe information-schema column.');
        }

        $statement = $this->connection->prepare(
            'SELECT ' . $column . ' FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);

        return (string) $statement->fetchColumn();
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid contact data.');
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
