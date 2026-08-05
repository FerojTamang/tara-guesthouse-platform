<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class InvoiceMigrationTest extends DatabaseTestCase
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
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR . 'database'
                . DIRECTORY_SEPARATOR . 'migrations',
        );
    }

    public function testInvoiceMigrationsExposeApprovedSchemaIndexesAndRetention(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());

        self::assertSame([
            'id',
            'booking_id',
            'invoice_number',
            'currency',
            'subtotal',
            'tax_amount',
            'total_amount',
            'issued_at',
            'created_by',
            'created_at',
            'updated_at',
        ], $this->columns('invoices'));
        self::assertSame([
            'id',
            'invoice_id',
            'description',
            'quantity',
            'unit_price',
            'line_total',
        ], $this->columns('invoice_items'));

        foreach (['invoices', 'invoice_items'] as $table) {
            self::assertSame('InnoDB', $this->tableOption($table, 'ENGINE'));
            self::assertSame('utf8mb4_unicode_ci', $this->tableOption($table, 'TABLE_COLLATION'));
        }

        self::assertSame(['booking_id'], $this->indexColumns('invoices', 'uq_invoices_booking'));
        self::assertSame(['invoice_number'], $this->indexColumns('invoices', 'uq_invoices_number'));
        self::assertSame(['issued_at', 'id'], $this->indexColumns('invoices', 'idx_invoices_issued'));
        self::assertSame(['created_by'], $this->indexColumns('invoices', 'idx_invoices_created_by'));
        self::assertSame(['invoice_id', 'id'], $this->indexColumns('invoice_items', 'idx_invoice_items_invoice'));
        self::assertSame(['RESTRICT', 'RESTRICT'], $this->referentialRules('fk_invoices_booking'));
        self::assertSame(['RESTRICT', 'RESTRICT'], $this->referentialRules('fk_invoices_created_by'));
        self::assertSame(['RESTRICT', 'RESTRICT'], $this->referentialRules('fk_invoice_items_invoice'));
    }

    public function testInvoiceUniquenessFinancialChecksAndRetentionAreEnforced(): void
    {
        $this->runner->migrate();
        $roleId = $this->insertRole();
        $administratorId = $this->insertUser($roleId);
        $bookingId = $this->insertBooking('TGH-2026-INVMIGRATION00001');
        $invoiceId = $this->insertInvoice($bookingId, $administratorId);
        $itemId = $this->insertItem($invoiceId);

        self::assertGreaterThan(0, $invoiceId);
        self::assertGreaterThan(0, $itemId);
        self::assertSame('TGH-INV-2026-000001', $this->connection->query(
            'SELECT invoice_number FROM invoices WHERE id = ' . $invoiceId,
        )->fetchColumn());

        $this->assertPdoFailure(fn () => $this->insertInvoice(
            $bookingId,
            $administratorId,
            'TGH-INV-2026-000002',
        ));
        $secondBookingId = $this->insertBooking('TGH-2026-INVMIGRATION00002');
        $this->assertPdoFailure(fn () => $this->insertInvoice(
            $secondBookingId,
            $administratorId,
            'TGH-INV-2026-000001',
        ));

        foreach ([
            ['currency' => 'USD'],
            ['subtotal' => '0.00'],
            ['tax_amount' => '1.00'],
            ['total_amount' => '17000.00'],
            ['invoice_number' => 'INV-1'],
        ] as $index => $overrides) {
            $booking = $this->insertBooking(sprintf('TGH-2026-INVINVALID%05d', $index));
            $this->assertPdoFailure(fn () => $this->insertInvoice(
                $booking,
                $administratorId,
                'TGH-INV-2026-' . sprintf('%06d', $index + 10),
                $overrides,
            ));
        }

        foreach ([
            ['description' => '   '],
            ['quantity' => '0.00'],
            ['unit_price' => '0.00'],
            ['line_total' => '17000.00'],
        ] as $overrides) {
            $this->assertPdoFailure(fn () => $this->insertItem($invoiceId, $overrides));
        }

        $this->assertPdoFailure(fn () => $this->connection->exec(
            'DELETE FROM bookings WHERE id = ' . $bookingId,
        ));
        $this->assertPdoFailure(fn () => $this->connection->exec(
            'DELETE FROM users WHERE id = ' . $administratorId,
        ));
        $this->assertPdoFailure(fn () => $this->connection->exec(
            'DELETE FROM invoices WHERE id = ' . $invoiceId,
        ));
    }

    public function testInvoiceMigrationsRollbackAndReapplyCleanly(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->invoiceTableCount());
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(2, $this->invoiceTableCount());
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

    /** @return array{string, string} */
    private function referentialRules(string $constraint): array
    {
        $statement = $this->connection->prepare(
            'SELECT UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['constraint_name' => $constraint]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return [(string) $row['UPDATE_RULE'], (string) $row['DELETE_RULE']];
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) VALUES ('Administrator', 'Invoice test')",
        );

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(int $roleId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, password, account_status) '
            . 'VALUES (:role_id, :full_name, :username, :email, :password, :status)',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Invoice Administrator',
            'username' => 'invoice-administrator',
            'email' => 'invoice-administrator@example.test',
            'password' => 'test-only-password-hash',
            'status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBooking(string $reference): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, '
            . 'check_in, check_out, adults, children, currency, room_amount, total_amount, '
            . 'booking_status, special_request, terms_accepted_at, policy_version, '
            . 'confirmation_deadline_at, confirmed_at'
            . ') VALUES ('
            . ':reference, NULL, :name, :email, :phone, :check_in, :check_out, 2, 0, '
            . "'NPR', '18000.00', '18000.00', 'confirmed', NULL, :terms, 'v1', :deadline, :confirmed_at"
            . ')',
        );
        $statement->execute([
            'reference' => $reference,
            'name' => 'Invoice Migration Guest',
            'email' => 'invoice-migration@example.test',
            'phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-13',
            'terms' => '2026-07-30 03:00:00',
            'deadline' => '2026-07-30 05:00:00',
            'confirmed_at' => '2026-07-30 04:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, string> $overrides */
    private function insertInvoice(
        int $bookingId,
        int $administratorId,
        string $invoiceNumber = 'TGH-INV-2026-000001',
        array $overrides = [],
    ): int {
        $values = array_replace([
            'booking_id' => $bookingId,
            'invoice_number' => $invoiceNumber,
            'currency' => 'NPR',
            'subtotal' => '18000.00',
            'tax_amount' => '0.00',
            'total_amount' => '18000.00',
            'issued_at' => '2026-07-30 05:00:00',
            'created_by' => $administratorId,
        ], $overrides);
        $statement = $this->connection->prepare(
            'INSERT INTO invoices ('
            . 'booking_id, invoice_number, currency, subtotal, tax_amount, total_amount, issued_at, created_by'
            . ') VALUES ('
            . ':booking_id, :invoice_number, :currency, :subtotal, :tax_amount, :total_amount, :issued_at, :created_by'
            . ')',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, string> $overrides */
    private function insertItem(int $invoiceId, array $overrides = []): int
    {
        $values = array_replace([
            'invoice_id' => (string) $invoiceId,
            'description' => 'Accommodation',
            'quantity' => '3.00',
            'unit_price' => '6000.00',
            'line_total' => '18000.00',
        ], $overrides);
        $statement = $this->connection->prepare(
            'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total) '
            . 'VALUES (:invoice_id, :description, :quantity, :unit_price, :line_total)',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid invoice data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function invoiceTableCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('invoices', 'invoice_items')",
        )->fetchColumn();
    }
}
