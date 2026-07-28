<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoBookingStatusWriteRepository;
use App\Requests\AdministratorBookingStatusRequest;
use App\Services\AdministratorBookingStatusService;
use App\Services\PdoBookingTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorBookingStatusServiceIntegrationTest extends DatabaseTestCase
{
    private int $administratorId;

    private int $bookingId;

    private AdministratorBookingStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR
                . 'database'
                . DIRECTORY_SEPARATOR
                . 'migrations',
        ))->migrate();

        $roleId = $this->insertRole();
        $this->administratorId = $this->insertUser($roleId);
        $roomTypeId = $this->insertRoomType();
        $roomId = $this->insertRoom($roomTypeId);
        $this->bookingId = $this->insertBooking();
        $this->insertBookingRoom($this->bookingId, $roomId);
        $this->insertCreationEvent($this->bookingId);
        $this->service = new AdministratorBookingStatusService(
            new PdoBookingStatusWriteRepository($this->connection),
            new PdoAuditLogRepository($this->connection),
            new PdoBookingTransaction($this->connection),
            static fn (): DateTimeImmutable => new DateTimeImmutable(
                '2026-08-01 12:30:00 UTC',
            ),
        );
    }

    public function testTransitionUpdatesBookingAndAppendsEventAndAuditAtomically(): void
    {
        $request = AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_CANCELLED,
            'Guest requested cancellation.',
            true,
        );

        $result = $this->service->transition(
            $this->bookingId,
            $request,
            $this->administratorId,
            '203.0.113.40',
            'Integration Test Agent',
        );

        self::assertSame(Booking::STATUS_PENDING, $result->fromStatus());
        self::assertSame(Booking::STATUS_CANCELLED, $result->toStatus());
        $booking = $this->connection->query(
            'SELECT booking_status, cancelled_at, cancellation_reason, updated_at '
            . 'FROM bookings WHERE id = ' . $this->bookingId,
        )->fetch();
        self::assertIsArray($booking);
        self::assertSame(Booking::STATUS_CANCELLED, $booking['booking_status']);
        self::assertSame('2026-08-01 12:30:00', $booking['cancelled_at']);
        self::assertSame('Guest requested cancellation.', $booking['cancellation_reason']);
        self::assertSame('2026-08-01 12:30:00', $booking['updated_at']);

        $event = $this->connection->query(
            'SELECT event_type, from_status, to_status, performed_by, reason, created_at '
            . 'FROM booking_events WHERE booking_id = ' . $this->bookingId
            . ' ORDER BY id DESC LIMIT 1',
        )->fetch();
        self::assertIsArray($event);
        self::assertSame('booking_cancelled', $event['event_type']);
        self::assertSame(Booking::STATUS_PENDING, $event['from_status']);
        self::assertSame(Booking::STATUS_CANCELLED, $event['to_status']);
        self::assertSame($this->administratorId, (int) $event['performed_by']);
        self::assertSame('Guest requested cancellation.', $event['reason']);
        self::assertSame('2026-08-01 12:30:00', $event['created_at']);

        $audit = $this->connection->query(
            'SELECT user_id, event_type, entity_type, entity_id, description, '
            . 'metadata_json, ip_address, user_agent, created_at '
            . 'FROM audit_logs ORDER BY id DESC LIMIT 1',
        )->fetch();
        self::assertIsArray($audit);
        self::assertSame($this->administratorId, (int) $audit['user_id']);
        self::assertSame('booking_status_changed', $audit['event_type']);
        self::assertSame('booking', $audit['entity_type']);
        self::assertSame($this->bookingId, (int) $audit['entity_id']);
        self::assertSame('Administrator changed a booking status.', $audit['description']);
        self::assertSame([
            'from_status' => Booking::STATUS_PENDING,
            'to_status' => Booking::STATUS_CANCELLED,
        ], json_decode(
            $audit['metadata_json'],
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame('203.0.113.40', $audit['ip_address']);
        self::assertSame('Integration Test Agent', $audit['user_agent']);
        self::assertSame('2026-08-01 12:30:00', $audit['created_at']);
    }

    public function testForbiddenTransitionDoesNotMutateOrAppendHistory(): void
    {
        $request = AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_CHECKED_IN,
            null,
            false,
        );

        try {
            $this->service->transition(
                $this->bookingId,
                $request,
                $this->administratorId,
                null,
                null,
            );
            self::fail('Forbidden pending to checked-in transition succeeded.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertSame(
            Booking::STATUS_PENDING,
            $this->connection->query(
                'SELECT booking_status FROM bookings WHERE id = ' . $this->bookingId,
            )->fetchColumn(),
        );
        self::assertSame(1, $this->countRows('booking_events'));
        self::assertSame(0, $this->countRows('audit_logs'));
    }

    public function testAuditFailureRollsBackBookingAndEventWrites(): void
    {
        $this->connection->exec('DROP TABLE audit_logs');
        $request = AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_REJECTED,
            'Unable to accept the request.',
            true,
        );

        try {
            $this->service->transition(
                $this->bookingId,
                $request,
                $this->administratorId,
                null,
                null,
            );
            self::fail('Missing audit persistence did not fail the transition.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
        }

        self::assertSame(
            Booking::STATUS_PENDING,
            $this->connection->query(
                'SELECT booking_status FROM bookings WHERE id = ' . $this->bookingId,
            )->fetchColumn(),
        );
        self::assertNull($this->connection->query(
            'SELECT rejected_at FROM bookings WHERE id = ' . $this->bookingId,
        )->fetchColumn());
        self::assertSame(1, $this->countRows('booking_events'));
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM ' . $table,
        )->fetchColumn();
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) "
            . "VALUES ('Administrator', 'Booking status integration test')",
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
            'full_name' => 'Status Administrator',
            'username' => 'status-administrator',
            'email' => 'status-administrator@example.test',
            'password' => 'test-only-password-hash',
            'account_status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoomType(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_types ('
            . 'name, slug, description, base_price, capacity_adults, capacity_children, is_active'
            . ') VALUES ('
            . ':name, :slug, NULL, :base_price, :capacity_adults, :capacity_children, :is_active'
            . ')',
        );
        $statement->execute([
            'name' => 'Status Test Type',
            'slug' => 'status-test-type',
            'base_price' => '5000.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'is_active' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(int $roomTypeId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms ('
            . 'room_type_id, room_number, name, slug, description, price_per_night, '
            . 'capacity_adults, capacity_children, operational_status, is_published'
            . ') VALUES ('
            . ':room_type_id, :room_number, :name, :slug, NULL, :price_per_night, '
            . ':capacity_adults, :capacity_children, :operational_status, :is_published'
            . ')',
        );
        $statement->execute([
            'room_type_id' => $roomTypeId,
            'room_number' => 'ST-101',
            'name' => 'Status Test Room',
            'slug' => 'status-test-room',
            'price_per_night' => '5000.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'operational_status' => 'active',
            'is_published' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBooking(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, '
            . 'check_in, check_out, adults, children, currency, room_amount, total_amount, '
            . 'booking_status, special_request, terms_accepted_at, policy_version, '
            . 'confirmation_deadline_at, created_at, updated_at'
            . ') VALUES ('
            . ':booking_reference, NULL, :guest_full_name, :guest_email, :guest_phone, '
            . ':check_in, :check_out, :adults, :children, :currency, :room_amount, :total_amount, '
            . ':booking_status, NULL, :terms_accepted_at, :policy_version, '
            . ':confirmation_deadline_at, :created_at, :updated_at'
            . ')',
        );
        $statement->execute([
            'booking_reference' => 'TGH-STATUS-INTEGRATION-0001',
            'guest_full_name' => 'Integration Guest',
            'guest_email' => 'integration-guest@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'currency' => Booking::CURRENCY_NPR,
            'room_amount' => '10000.00',
            'total_amount' => '10000.00',
            'booking_status' => Booking::STATUS_PENDING,
            'terms_accepted_at' => '2026-08-01 10:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-08-01 16:00:00',
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBookingRoom(int $bookingId, int $roomId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO booking_rooms ('
            . 'booking_id, room_id, price_per_night, nights, subtotal, created_at'
            . ') VALUES ('
            . ':booking_id, :room_id, :price_per_night, :nights, :subtotal, :created_at'
            . ')',
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'room_id' => $roomId,
            'price_per_night' => '5000.00',
            'nights' => 2,
            'subtotal' => '10000.00',
            'created_at' => '2026-08-01 10:00:00',
        ]);
    }

    private function insertCreationEvent(int $bookingId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO booking_events ('
            . 'booking_id, event_type, from_status, to_status, '
            . 'performed_by, reason, metadata_json, created_at'
            . ') VALUES ('
            . ':booking_id, :event_type, NULL, :to_status, '
            . 'NULL, NULL, NULL, :created_at'
            . ')',
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'event_type' => 'booking_created',
            'to_status' => Booking::STATUS_PENDING,
            'created_at' => '2026-08-01 10:00:00',
        ]);
    }
}
