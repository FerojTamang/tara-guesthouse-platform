<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class BookingMigrationTest extends DatabaseTestCase
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

    public function testBookingMigrationsExposeTheApprovedSchemaAndRelationships(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());

        self::assertSame([
            'id',
            'booking_reference',
            'user_id',
            'guest_full_name',
            'guest_email',
            'guest_phone',
            'check_in',
            'check_out',
            'adults',
            'children',
            'currency',
            'room_amount',
            'total_amount',
            'booking_status',
            'special_request',
            'terms_accepted_at',
            'policy_version',
            'confirmation_deadline_at',
            'confirmed_at',
            'checked_in_at',
            'checked_out_at',
            'cancelled_at',
            'cancellation_reason',
            'rejected_at',
            'rejection_reason',
            'created_at',
            'updated_at',
            'deleted_at',
        ], array_keys($this->columns('bookings')));

        self::assertSame([
            'id',
            'booking_id',
            'room_id',
            'price_per_night',
            'nights',
            'subtotal',
            'created_at',
        ], array_keys($this->columns('booking_rooms')));

        self::assertSame([
            'id',
            'booking_id',
            'event_type',
            'from_status',
            'to_status',
            'performed_by',
            'reason',
            'metadata_json',
            'created_at',
        ], array_keys($this->columns('booking_events')));

        foreach (['bookings', 'booking_rooms', 'booking_events'] as $table) {
            self::assertSame('InnoDB', $this->tableOption($table, 'ENGINE'));
            self::assertSame('utf8mb4_unicode_ci', $this->tableOption($table, 'TABLE_COLLATION'));
        }

        self::assertSame(['booking_reference'], $this->indexColumns('bookings', 'uq_bookings_booking_reference'));
        self::assertSame(['user_id'], $this->indexColumns('bookings', 'idx_bookings_user'));
        self::assertSame(['guest_email'], $this->indexColumns('bookings', 'idx_bookings_guest_email'));
        self::assertSame(['check_in', 'check_out'], $this->indexColumns('bookings', 'idx_bookings_date_range'));
        self::assertSame(
            ['booking_status', 'confirmation_deadline_at'],
            $this->indexColumns('bookings', 'idx_bookings_status_deadline'),
        );
        self::assertSame(['booking_id'], $this->indexColumns('booking_rooms', 'uq_booking_rooms_booking'));
        self::assertSame(
            ['room_id', 'booking_id'],
            $this->indexColumns('booking_rooms', 'idx_booking_rooms_room_booking'),
        );
        self::assertSame(
            ['booking_id', 'created_at', 'id'],
            $this->indexColumns('booking_events', 'idx_booking_events_booking_created'),
        );
        self::assertSame(['performed_by'], $this->indexColumns('booking_events', 'idx_booking_events_actor'));
        self::assertSame(['event_type'], $this->indexColumns('booking_events', 'idx_booking_events_type'));

        self::assertSame(['RESTRICT', 'SET NULL'], $this->referentialRules('fk_bookings_user'));
        self::assertSame(['RESTRICT', 'CASCADE'], $this->referentialRules('fk_booking_rooms_booking'));
        self::assertSame(['RESTRICT', 'RESTRICT'], $this->referentialRules('fk_booking_rooms_room'));
        self::assertSame(['RESTRICT', 'CASCADE'], $this->referentialRules('fk_booking_events_booking'));
        self::assertSame(['RESTRICT', 'SET NULL'], $this->referentialRules('fk_booking_events_performer'));
    }

    public function testBookingConstraintsOneRoomRuleAndReferentialActionsAreEnforced(): void
    {
        $this->runner->migrate();
        $roleId = $this->insertRole();
        $userId = $this->insertUser($roleId);
        $roomTypeId = $this->insertRoomType();
        $roomId = $this->insertRoom($roomTypeId, 'S2-101', 's2-room-101');
        $secondRoomId = $this->insertRoom($roomTypeId, 'S2-102', 's2-room-102');

        $bookingId = $this->insertBooking('TGH-S2-VALID-001', ['user_id' => $userId]);
        $this->insertBookingRoom($bookingId, $roomId);
        $eventId = $this->insertBookingEvent($bookingId, $userId);

        $this->assertPdoFailure(fn () => $this->insertBooking('TGH-S2-VALID-001'));

        $invalidBookings = [
            ['TGH-S2-BAD-DATE', ['check_out' => '2026-08-10']],
            ['TGH-S2-BAD-ADULT', ['adults' => 0]],
            ['TGH-S2-BAD-CHILD', ['children' => -1]],
            ['TGH-S2-BAD-CURRENCY', ['currency' => 'USD']],
            ['TGH-S2-BAD-ROOM-AMOUNT', ['room_amount' => '0.00']],
            ['TGH-S2-BAD-TOTAL', ['total_amount' => '0.00']],
            ['TGH-S2-BAD-STATUS', ['booking_status' => 'completed']],
        ];

        foreach ($invalidBookings as [$reference, $overrides]) {
            $this->assertPdoFailure(fn () => $this->insertBooking($reference, $overrides));
        }

        foreach (['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'rejected'] as $index => $status) {
            $id = $this->insertBooking('TGH-S2-STATUS-' . ($index + 1), ['booking_status' => $status]);
            self::assertGreaterThan(0, $id);
        }

        $this->assertPdoFailure(fn () => $this->insertBookingRoom($bookingId, $secondRoomId));

        foreach ([
            ['price_per_night' => '0.00'],
            ['nights' => 0],
            ['subtotal' => '0.00'],
        ] as $index => $overrides) {
            $otherBooking = $this->insertBooking('TGH-S2-ROOM-RULE-' . ($index + 1));
            $this->assertPdoFailure(fn () => $this->insertBookingRoom($otherBooking, $secondRoomId, $overrides));
        }

        $this->assertPdoFailure(fn () => $this->insertBookingEvent(
            $bookingId,
            $userId,
            ['from_status' => 'completed'],
        ));
        $this->assertPdoFailure(fn () => $this->insertBookingEvent(
            $bookingId,
            $userId,
            ['to_status' => 'completed'],
        ));
        $this->assertPdoFailure(fn () => $this->insertBookingEvent(
            $bookingId,
            $userId,
            ['metadata_json' => '{invalid-json'],
        ));

        $this->connection->exec('DELETE FROM users WHERE id = ' . $userId);
        self::assertNull($this->connection->query(
            'SELECT user_id FROM bookings WHERE id = ' . $bookingId,
        )->fetchColumn());
        self::assertNull($this->connection->query(
            'SELECT performed_by FROM booking_events WHERE id = ' . $eventId,
        )->fetchColumn());

        $this->assertPdoFailure(fn () => $this->connection->exec('DELETE FROM rooms WHERE id = ' . $roomId));

        $this->connection->exec('DELETE FROM bookings WHERE id = ' . $bookingId);
        self::assertSame(0, (int) $this->connection->query(
            'SELECT COUNT(*) FROM booking_rooms WHERE booking_id = ' . $bookingId,
        )->fetchColumn());
        self::assertSame(0, (int) $this->connection->query(
            'SELECT COUNT(*) FROM booking_events WHERE booking_id = ' . $bookingId,
        )->fetchColumn());
        self::assertSame(1, $this->connection->exec('DELETE FROM rooms WHERE id = ' . $roomId));
    }

    public function testBookingMigrationsRollbackAndRemigrateCleanly(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(3, $this->bookingTableCount());

        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->bookingTableCount());
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());

        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame(3, $this->bookingTableCount());
    }

    /** @return array<string, array<string, mixed>> */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, '
            . 'CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME, NUMERIC_PRECISION, NUMERIC_SCALE '
            . 'FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
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
                'SELECT %s FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
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
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name '
            . 'ORDER BY SEQ_IN_INDEX',
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);

        return array_map(
            static fn (array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll(),
        );
    }

    /** @return array{0: string, 1: string} */
    private function referentialRules(string $constraint): array
    {
        $statement = $this->connection->prepare(
            'SELECT UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['constraint_name' => $constraint]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return [$row['UPDATE_RULE'], $row['DELETE_RULE']];
    }

    private function insertRole(): int
    {
        $this->connection->exec("INSERT INTO roles (role_name, description) VALUES ('Administrator', NULL)");

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(int $roleId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users '
            . '(role_id, full_name, username, email, phone, password, profile_image, account_status) '
            . "VALUES (:role_id, 'Booking Tester', 'booking.tester', 'booking.tester@example.test', NULL, "
            . "'not-a-real-password-hash', NULL, 'active')",
        );
        $statement->execute(['role_id' => $roleId]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoomType(): int
    {
        $this->connection->exec(
            "INSERT INTO room_types "
            . "(name, slug, description, base_price, capacity_adults, capacity_children, is_active) "
            . "VALUES ('Booking Test Type', 'booking-test-type', NULL, 5000.00, 2, 1, 1)",
        );

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(int $roomTypeId, string $roomNumber, string $slug): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms '
            . '(room_type_id, room_number, name, slug, description, price_per_night, capacity_adults, '
            . 'capacity_children, operational_status, is_published, deleted_at) '
            . "VALUES (:room_type_id, :room_number, 'Booking Test Room', :slug, NULL, 5000.00, 2, 1, "
            . "'active', 1, NULL)",
        );
        $statement->execute([
            'room_type_id' => $roomTypeId,
            'room_number' => $roomNumber,
            'slug' => $slug,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    private function insertBooking(string $reference, array $overrides = []): int
    {
        $values = array_merge([
            'booking_reference' => $reference,
            'user_id' => null,
            'guest_full_name' => 'Booking Test Guest',
            'guest_email' => 'booking.guest@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'currency' => 'NPR',
            'room_amount' => '5000.00',
            'total_amount' => '10000.00',
            'booking_status' => 'pending',
            'special_request' => null,
            'terms_accepted_at' => '2026-07-26 09:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-07-27 09:00:00',
        ], $overrides);

        $statement = $this->connection->prepare(
            'INSERT INTO bookings '
            . '(booking_reference, user_id, guest_full_name, guest_email, guest_phone, check_in, check_out, '
            . 'adults, children, currency, room_amount, total_amount, booking_status, special_request, '
            . 'terms_accepted_at, policy_version, confirmation_deadline_at) '
            . 'VALUES (:booking_reference, :user_id, :guest_full_name, :guest_email, :guest_phone, :check_in, '
            . ':check_out, :adults, :children, :currency, :room_amount, :total_amount, :booking_status, '
            . ':special_request, :terms_accepted_at, :policy_version, :confirmation_deadline_at)',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, int|string> $overrides */
    private function insertBookingRoom(int $bookingId, int $roomId, array $overrides = []): int
    {
        $values = array_merge([
            'booking_id' => $bookingId,
            'room_id' => $roomId,
            'price_per_night' => '5000.00',
            'nights' => 2,
            'subtotal' => '10000.00',
        ], $overrides);
        $statement = $this->connection->prepare(
            'INSERT INTO booking_rooms (booking_id, room_id, price_per_night, nights, subtotal) '
            . 'VALUES (:booking_id, :room_id, :price_per_night, :nights, :subtotal)',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    private function insertBookingEvent(int $bookingId, ?int $performedBy, array $overrides = []): int
    {
        $values = array_merge([
            'booking_id' => $bookingId,
            'event_type' => 'booking_created',
            'from_status' => null,
            'to_status' => 'pending',
            'performed_by' => $performedBy,
            'reason' => null,
            'metadata_json' => '{"source":"migration-test"}',
        ], $overrides);
        $statement = $this->connection->prepare(
            'INSERT INTO booking_events '
            . '(booking_id, event_type, from_status, to_status, performed_by, reason, metadata_json) '
            . 'VALUES (:booking_id, :event_type, :from_status, :to_status, :performed_by, :reason, :metadata_json)',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid booking data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function bookingTableCount(): int
    {
        return (int) $this->connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() "
            . "AND TABLE_NAME IN ('bookings', 'booking_rooms', 'booking_events')",
        )->fetchColumn();
    }
}
