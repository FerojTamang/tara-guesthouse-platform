<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PaymentMigrationTest extends DatabaseTestCase
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
    ];

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR
                . 'database'
                . DIRECTORY_SEPARATOR
                . 'migrations',
        );
    }

    public function testPaymentMigrationsExposeTheApprovedSchemaAndRelationships(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());

        self::assertSame([
            'id',
            'name',
            'instructions',
            'account_reference',
            'qr_image_path',
            'requires_proof',
            'is_active',
            'created_at',
            'updated_at',
        ], array_keys($this->columns('payment_methods')));

        self::assertSame([
            'id',
            'booking_id',
            'payment_method_id',
            'currency',
            'amount',
            'transaction_reference',
            'proof_path',
            'guest_notes',
            'payment_status',
            'submitted_at',
            'verified_at',
            'verified_by',
            'rejected_at',
            'rejected_by',
            'verification_note',
            'created_at',
            'updated_at',
        ], array_keys($this->columns('payments')));

        foreach (['payment_methods', 'payments'] as $table) {
            self::assertSame('InnoDB', $this->tableOption($table, 'ENGINE'));
            self::assertSame(
                'utf8mb4_unicode_ci',
                $this->tableOption($table, 'TABLE_COLLATION'),
            );
        }

        self::assertSame(
            ['is_active', 'name', 'id'],
            $this->indexColumns('payment_methods', 'idx_payment_methods_active_name'),
        );
        self::assertSame(
            ['booking_id', 'created_at', 'id'],
            $this->indexColumns('payments', 'idx_payments_booking_created'),
        );
        self::assertSame(
            ['payment_method_id'],
            $this->indexColumns('payments', 'idx_payments_method'),
        );
        self::assertSame(
            ['payment_status', 'created_at', 'id'],
            $this->indexColumns('payments', 'idx_payments_status_created'),
        );
        self::assertSame(
            ['verified_by'],
            $this->indexColumns('payments', 'idx_payments_verified_by'),
        );
        self::assertSame(
            ['rejected_by'],
            $this->indexColumns('payments', 'idx_payments_rejected_by'),
        );
        self::assertSame(
            ['RESTRICT', 'RESTRICT'],
            $this->referentialRules('fk_payments_booking'),
        );
        self::assertSame(
            ['RESTRICT', 'RESTRICT'],
            $this->referentialRules('fk_payments_method'),
        );
        self::assertSame(
            ['RESTRICT', 'RESTRICT'],
            $this->referentialRules('fk_payments_verified_by'),
        );
        self::assertSame(
            ['RESTRICT', 'RESTRICT'],
            $this->referentialRules('fk_payments_rejected_by'),
        );

    }

    public function testPaymentConstraintsAttemptsAndRetentionRulesAreEnforced(): void
    {
        $this->runner->migrate();
        $roleId = $this->insertRole();
        $administratorId = $this->insertUser($roleId);
        $methodId = $this->insertPaymentMethod('Bank Transfer', 1, 1);
        $bookingId = $this->insertBooking('TGH-S3-PAY-001');

        $firstSubmitted = $this->insertPayment(
            $bookingId,
            $methodId,
            ['transaction_reference' => 'BANK-001'],
        );
        $secondSubmitted = $this->insertPayment(
            $bookingId,
            $methodId,
            ['transaction_reference' => 'BANK-002'],
        );

        self::assertGreaterThan(0, $firstSubmitted);
        self::assertGreaterThan($firstSubmitted, $secondSubmitted);
        self::assertSame(2, (int) $this->connection->query(
            'SELECT COUNT(*) FROM payments WHERE booking_id = ' . $bookingId,
        )->fetchColumn());

        $this->assertPdoFailure(
            fn () => $this->insertPaymentMethod('   ', 1, 1),
        );
        $this->assertPdoFailure(
            fn () => $this->insertPaymentMethod('Bad proof flag', 2, 1),
        );
        $this->assertPdoFailure(
            fn () => $this->insertPaymentMethod('Bad active flag', 1, 2),
        );

        foreach ([
            '',
            '/absolute.png',
            '../escape.png',
            'payments/../escape.png',
            'payments\\escape.png',
            'payments//escape.png',
            './escape.png',
        ] as $index => $path) {
            $this->assertPdoFailure(
                fn () => $this->insertPaymentMethod(
                    'Bad QR ' . $index,
                    1,
                    1,
                    $path,
                ),
            );
        }

        foreach ([
            ['currency' => 'USD'],
            ['amount' => '0.00'],
            ['payment_status' => 'unknown'],
            ['payment_status' => 'SUBMITTED'],
            ['booking_id' => $bookingId + 999],
            ['payment_method_id' => $methodId + 999],
            ['verified_by' => $administratorId + 999],
            ['rejected_by' => $administratorId + 999],
        ] as $index => $overrides) {
            $this->assertPdoFailure(
                fn () => $this->insertPayment(
                    $bookingId,
                    $methodId,
                    array_merge(
                        ['transaction_reference' => 'INVALID-' . $index],
                        $overrides,
                    ),
                ),
            );
        }

        foreach ([
            '',
            '/absolute.pdf',
            '../escape.pdf',
            'payment-proofs/../escape.pdf',
            'payment-proofs\\escape.pdf',
            'payment-proofs//escape.pdf',
            './escape.pdf',
        ] as $index => $path) {
            $this->assertPdoFailure(
                fn () => $this->insertPayment(
                    $bookingId,
                    $methodId,
                    [
                        'transaction_reference' => 'BAD-PROOF-' . $index,
                        'proof_path' => $path,
                    ],
                ),
            );
        }

        $verifiedBookingId = $this->insertBooking('TGH-S3-PAY-VERIFIED');
        $verifiedPaymentId = $this->insertPayment(
            $verifiedBookingId,
            $methodId,
            [
                'payment_status' => 'verified',
                'verified_at' => '2026-07-28 06:30:00',
                'verified_by' => $administratorId,
                'verification_note' => 'Verified migration fixture.',
            ],
        );
        self::assertSame(
            $administratorId,
            (int) $this->connection->query(
                'SELECT verified_by FROM payments WHERE id = ' . $verifiedPaymentId,
            )->fetchColumn(),
        );

        $rejectedBookingId = $this->insertBooking('TGH-S3-PAY-REJECTED');
        $rejectedPaymentId = $this->insertPayment(
            $rejectedBookingId,
            $methodId,
            [
                'payment_status' => 'rejected',
                'rejected_at' => '2026-07-28 06:40:00',
                'rejected_by' => $administratorId,
                'verification_note' => 'Rejected migration fixture.',
            ],
        );
        self::assertSame(
            $administratorId,
            (int) $this->connection->query(
                'SELECT rejected_by FROM payments WHERE id = ' . $rejectedPaymentId,
            )->fetchColumn(),
        );

        foreach (['pending', 'refunded'] as $index => $status) {
            $statusBookingId = $this->insertBooking('TGH-S3-PAY-STATUS-' . $index);
            self::assertGreaterThan(0, $this->insertPayment(
                $statusBookingId,
                $methodId,
                [
                    'payment_status' => $status,
                    'submitted_at' => null,
                    'transaction_reference' => null,
                ],
            ));
        }

        $this->assertPdoFailure(
            fn () => $this->connection->exec(
                'DELETE FROM bookings WHERE id = ' . $bookingId,
            ),
        );
        $this->assertPdoFailure(
            fn () => $this->connection->exec(
                'DELETE FROM payment_methods WHERE id = ' . $methodId,
            ),
        );
        $this->assertPdoFailure(
            fn () => $this->connection->exec(
                'DELETE FROM users WHERE id = ' . $administratorId,
            ),
        );
    }

    public function testPaymentMigrationsRollbackAndRemigrateCleanly(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(2, $this->paymentTableCount());

        self::assertSame(
            array_reverse(self::APPLICATION_MIGRATIONS),
            $this->runner->rollbackLastBatch(),
        );
        self::assertSame(0, $this->paymentTableCount());
        self::assertSame(
            0,
            (int) $this->connection
                ->query('SELECT COUNT(*) FROM schema_migrations')
                ->fetchColumn(),
        );

        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(2, $this->paymentTableCount());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, '
            . 'COLUMN_DEFAULT, EXTRA, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME, '
            . 'NUMERIC_PRECISION, NUMERIC_SCALE '
            . 'FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);
        $columns = [];

        foreach ($statement->fetchAll() as $column) {
            self::assertIsArray($column);
            $columns[$column['COLUMN_NAME']] = $column;
        }

        return $columns;
    }

    private function tableOption(string $table, string $column): string
    {
        if (!in_array($column, ['ENGINE', 'TABLE_COLLATION'], true)) {
            self::fail('Unsafe information-schema column.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT %s FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
                $column,
            ),
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
        $statement->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    /** @return array{string, string} */
    private function referentialRules(string $constraint): array
    {
        $statement = $this->connection->prepare(
            'SELECT UPDATE_RULE, DELETE_RULE '
            . 'FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['constraint_name' => $constraint]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return [(string) $row['UPDATE_RULE'], (string) $row['DELETE_RULE']];
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) "
            . "VALUES ('Administrator', 'Payment migration test')",
        );

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(int $roleId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users ('
            . 'role_id, full_name, username, email, password, account_status'
            . ') VALUES ('
            . ':role_id, :full_name, :username, :email, :password, :account_status'
            . ')',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Payment Administrator',
            'username' => 'payment-administrator',
            'email' => 'payment-administrator@example.test',
            'password' => 'test-only-password-hash',
            'account_status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertPaymentMethod(
        string $name,
        int $requiresProof,
        int $active,
        ?string $qrImagePath = null,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO payment_methods ('
            . 'name, instructions, account_reference, qr_image_path, '
            . 'requires_proof, is_active'
            . ') VALUES ('
            . ':name, :instructions, :account_reference, :qr_image_path, '
            . ':requires_proof, :is_active'
            . ')',
        );
        $statement->execute([
            'name' => $name,
            'instructions' => 'Use the configured manual transfer instructions.',
            'account_reference' => 'TEST-ACCOUNT',
            'qr_image_path' => $qrImagePath,
            'requires_proof' => $requiresProof,
            'is_active' => $active,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBooking(string $reference): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, '
            . 'guest_phone, check_in, check_out, adults, children, currency, '
            . 'room_amount, total_amount, booking_status, special_request, '
            . 'terms_accepted_at, policy_version, confirmation_deadline_at'
            . ') VALUES ('
            . ':booking_reference, NULL, :guest_full_name, :guest_email, '
            . ':guest_phone, :check_in, :check_out, :adults, :children, '
            . ':currency, :room_amount, :total_amount, :booking_status, NULL, '
            . ':terms_accepted_at, :policy_version, :confirmation_deadline_at'
            . ')',
        );
        $statement->execute([
            'booking_reference' => $reference,
            'guest_full_name' => 'Payment Test Guest',
            'guest_email' => 'payment.guest@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'currency' => 'NPR',
            'room_amount' => '5000.00',
            'total_amount' => '10000.00',
            'booking_status' => 'pending',
            'terms_accepted_at' => '2026-07-28 06:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-07-28 12:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    private function insertPayment(
        int $bookingId,
        int $paymentMethodId,
        array $overrides = [],
    ): int {
        $values = array_replace([
            'booking_id' => $bookingId,
            'payment_method_id' => $paymentMethodId,
            'currency' => 'NPR',
            'amount' => '10000.00',
            'transaction_reference' => 'PAYMENT-REFERENCE',
            'proof_path' => 'payment-proofs/fixture.pdf',
            'guest_notes' => 'Migration test payment.',
            'payment_status' => 'submitted',
            'submitted_at' => '2026-07-28 06:15:00',
            'verified_at' => null,
            'verified_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'verification_note' => null,
        ], $overrides);
        $statement = $this->connection->prepare(
            'INSERT INTO payments ('
            . 'booking_id, payment_method_id, currency, amount, '
            . 'transaction_reference, proof_path, guest_notes, payment_status, '
            . 'submitted_at, verified_at, verified_by, rejected_at, rejected_by, '
            . 'verification_note'
            . ') VALUES ('
            . ':booking_id, :payment_method_id, :currency, :amount, '
            . ':transaction_reference, :proof_path, :guest_notes, :payment_status, '
            . ':submitted_at, :verified_at, :verified_by, :rejected_at, :rejected_by, '
            . ':verification_note'
            . ')',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid payment data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function paymentTableCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME IN ('payment_methods', 'payments')",
        )->fetchColumn();
    }
}
