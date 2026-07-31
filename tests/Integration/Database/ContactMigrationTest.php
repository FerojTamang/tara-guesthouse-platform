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

    public function testMigrationExposesRetainedInquiryColumnsAndIndexes(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame([
            'id', 'name', 'email', 'phone', 'subject', 'message', 'status', 'created_at', 'updated_at',
        ], $this->columns());
        self::assertSame(['created_at', 'id'], $this->indexColumns('idx_contact_messages_created'));
        self::assertSame(['status', 'created_at', 'id'], $this->indexColumns('idx_contact_messages_status_created'));
        self::assertSame(['email', 'created_at', 'id'], $this->indexColumns('idx_contact_messages_email_created'));
        self::assertSame('YES', $this->columnNullability('status'));
        self::assertSame('InnoDB', $this->tableOption('ENGINE'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('TABLE_COLLATION'));
    }

    public function testConstraintsPermitNullPolicyStateAndRetainOldMessages(): void
    {
        $this->runner->migrate();
        $oldId = ContactFixture::insert($this->connection, [
            'status' => null,
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
        ]);
        self::assertGreaterThan(0, $oldId);
        self::assertSame('1', (string) $this->connection->query(
            'SELECT COUNT(*) FROM contact_messages WHERE id = ' . $oldId,
        )->fetchColumn());

        foreach ([
            ['name' => ' Asha'],
            ['email' => ' guest@example.test'],
            ['phone' => str_repeat('1', 21)],
            ['subject' => 'No'],
            ['message' => 'Too short'],
            ['status' => 'Awaiting Reply'],
        ] as $overrides) {
            $this->assertPdoFailure(fn () => ContactFixture::insert($this->connection, $overrides));
        }
    }

    public function testRollbackRemovesContactTableBeforeDependencies(): void
    {
        $this->runner->migrate();
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->tableCount());
    }

    /** @return list<string> */
    private function columns(): array
    {
        $rows = $this->connection->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'contact_messages' ORDER BY ORDINAL_POSITION",
        )->fetchAll();
        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $rows);
    }

    /** @return list<string> */
    private function indexColumns(string $index): array
    {
        $statement = $this->connection->prepare(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'contact_messages' AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX",
        );
        $statement->execute(['index_name' => $index]);
        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $statement->fetchAll());
    }

    private function columnNullability(string $column): string
    {
        $statement = $this->connection->prepare(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = :column_name",
        );
        $statement->execute(['column_name' => $column]);
        return (string) $statement->fetchColumn();
    }

    private function tableOption(string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT ' . $column . " FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'contact_messages'",
        );
        $statement->execute();
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

    private function tableCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME = 'contact_messages'",
        )->fetchColumn();
    }
}
