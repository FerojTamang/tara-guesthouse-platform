<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\Booking;
use App\Repositories\BookingRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class BookingRepositoryTest extends DatabaseTestCase
{
    private BookingRepository $repository;

    private int $roomId;

    private int $bookingId;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $roomTypeId = $this->insertRoomType();
        $this->roomId = $this->insertRoom($roomTypeId);
        $this->bookingId = $this->insertBooking('TGH-READ-0001', null);
        $this->insertBookingRoom($this->bookingId, $this->roomId);
        $this->insertEvent(
            $this->bookingId,
            'booking_created',
            null,
            Booking::STATUS_PENDING,
            null,
            null,
            null,
            '2026-08-01 10:00:00',
        );
        $this->insertEvent(
            $this->bookingId,
            'booking_note_added',
            Booking::STATUS_PENDING,
            Booking::STATUS_PENDING,
            null,
            'Guest requested a quiet room.',
            '{"source":"guest"}',
            '2026-08-01 10:05:00',
        );

        $this->repository = new BookingRepository($this->connection);
    }

    public function testItFindsCurrentBookingsByIdAndReference(): void
    {
        $byId = $this->repository->findById($this->bookingId);
        $byReference = $this->repository->findByReference('TGH-READ-0001');

        self::assertNotNull($byId);
        self::assertNotNull($byReference);
        self::assertSame($this->bookingId, $byId->id());
        self::assertSame($this->bookingId, $byReference->id());
        self::assertSame('TGH-READ-0001', $byId->bookingReference());
        self::assertNull($byId->userId());
        self::assertSame('Guest Example', $byId->guestFullName());
        self::assertSame('guest@example.test', $byId->guestEmail());
        self::assertSame('+9779812345678', $byId->guestPhone());
        self::assertSame('2026-08-10', $byId->checkIn()->format('Y-m-d'));
        self::assertSame('2026-08-13', $byId->checkOut()->format('Y-m-d'));
        self::assertSame(2, $byId->adults());
        self::assertSame(1, $byId->children());
        self::assertSame(Booking::CURRENCY_NPR, $byId->currency());
        self::assertSame('18000.00', $byId->roomAmount());
        self::assertSame('18000.00', $byId->totalAmount());
        self::assertSame(Booking::STATUS_PENDING, $byId->bookingStatus());
        self::assertSame('Late arrival.', $byId->specialRequest());
        self::assertSame('v1', $byId->policyVersion());
        self::assertSame('2026-08-02 10:00:00', $byId->confirmationDeadlineAt()->format('Y-m-d H:i:s'));
        self::assertNull($byId->confirmedAt());
        self::assertNull($byId->cancelledAt());
        self::assertNull($byId->deletedAt());
        self::assertSame(0, $byId->createdAt()->getOffset());
    }

    public function testItListsCurrentBookingsNewestFirstAndFiltersCanonicalStatus(): void
    {
        $confirmedId = $this->insertBooking('TGH-READ-0002', null);
        $this->connection->exec(
            "UPDATE bookings SET booking_status = 'confirmed', "
            . "created_at = '2026-08-01 12:00:00', "
            . "updated_at = '2026-08-01 12:00:00' "
            . 'WHERE id = ' . $confirmedId,
        );
        $this->connection->exec(
            "UPDATE bookings SET created_at = '2026-08-01 09:00:00', "
            . "updated_at = '2026-08-01 09:00:00' "
            . 'WHERE id = ' . $this->bookingId,
        );
        $this->insertBooking(
            'TGH-READ-DELETED-0001',
            '2026-08-01 13:00:00',
        );

        $all = $this->repository->findAll();

        self::assertSame(
            [$confirmedId, $this->bookingId],
            array_map(
                static fn (Booking $booking): int => $booking->id(),
                $all,
            ),
        );
        self::assertSame(
            [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING],
            array_map(
                static fn (Booking $booking): string => $booking->bookingStatus(),
                $all,
            ),
        );

        $pending = $this->repository->findAll(
            Booking::STATUS_PENDING,
        );
        self::assertCount(1, $pending);
        self::assertSame($this->bookingId, $pending[0]->id());

        $confirmed = $this->repository->findAll(
            Booking::STATUS_CONFIRMED,
        );
        self::assertCount(1, $confirmed);
        self::assertSame($confirmedId, $confirmed[0]->id());

        self::assertSame([], $this->repository->findAll('PENDING'));
        self::assertSame([], $this->repository->findAll('completed'));
    }

    public function testInvalidOrMissingLookupsReturnNullWithoutQueryDisclosure(): void
    {
        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(999999));
        self::assertNull($this->repository->findByReference(''));
        self::assertNull($this->repository->findByReference('TGH-MISSING'));
        self::assertNull($this->repository->findRoomByBookingId(0));
        self::assertNull($this->repository->findRoomByBookingId(999999));
        self::assertSame([], $this->repository->findEventsByBookingId(0));
        self::assertSame([], $this->repository->findEventsByBookingId(999999));
    }

    public function testSoftDeletedBookingsAreHiddenFromCurrentLookups(): void
    {
        $deletedId = $this->insertBooking('TGH-DELETED-0001', '2026-08-01 11:00:00');

        self::assertNull($this->repository->findById($deletedId));
        self::assertNull($this->repository->findByReference('TGH-DELETED-0001'));
    }

    public function testItMapsTheOneRoomSnapshot(): void
    {
        $room = $this->repository->findRoomByBookingId($this->bookingId);

        self::assertNotNull($room);
        self::assertSame($this->bookingId, $room->bookingId());
        self::assertSame($this->roomId, $room->roomId());
        self::assertSame('6000.00', $room->pricePerNight());
        self::assertSame(3, $room->nights());
        self::assertSame('18000.00', $room->subtotal());
        self::assertSame(0, $room->createdAt()->getOffset());
    }

    public function testItListsEventsInStableChronologicalOrder(): void
    {
        $events = $this->repository->findEventsByBookingId($this->bookingId);

        self::assertCount(2, $events);
        self::assertSame('booking_created', $events[0]->eventType());
        self::assertNull($events[0]->fromStatus());
        self::assertSame(Booking::STATUS_PENDING, $events[0]->toStatus());
        self::assertNull($events[0]->performedBy());
        self::assertNull($events[0]->reason());
        self::assertNull($events[0]->metadataJson());

        self::assertSame('booking_note_added', $events[1]->eventType());
        self::assertSame(Booking::STATUS_PENDING, $events[1]->fromStatus());
        self::assertSame(Booking::STATUS_PENDING, $events[1]->toStatus());
        self::assertSame('Guest requested a quiet room.', $events[1]->reason());
        self::assertSame('{"source":"guest"}', $events[1]->metadataJson());
    }

    public function testDatabaseFailuresExposeOnlyStableMessages(): void
    {
        $this->dropTables(['booking_events']);

        try {
            $this->repository->findEventsByBookingId($this->bookingId);
            self::fail('Expected a sanitized booking event listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking event listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        $this->dropTables(['booking_rooms']);

        try {
            $this->repository->findRoomByBookingId($this->bookingId);
            self::fail('Expected a sanitized booking room lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking room lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        $this->dropTables(['bookings']);

        try {
            $this->repository->findAll();
            self::fail('Expected a sanitized booking listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->findById($this->bookingId);
            self::fail('Expected a sanitized booking lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function insertRoomType(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_types ('
            . 'name, slug, description, base_price, capacity_adults, capacity_children, is_active'
            . ') VALUES ('
            . ':name, :slug, :description, :base_price, :capacity_adults, :capacity_children, :is_active'
            . ')',
        );
        $statement->execute([
            'name' => 'Booking Repository Type',
            'slug' => 'booking-repository-type',
            'description' => null,
            'base_price' => '6000.00',
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
            . ':room_type_id, :room_number, :name, :slug, :description, :price_per_night, '
            . ':capacity_adults, :capacity_children, :operational_status, :is_published'
            . ')',
        );
        $statement->execute([
            'room_type_id' => $roomTypeId,
            'room_number' => 'BR-101',
            'name' => 'Booking Repository Room',
            'slug' => 'booking-repository-room',
            'description' => null,
            'price_per_night' => '6000.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'operational_status' => 'active',
            'is_published' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBooking(string $reference, ?string $deletedAt): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, '
            . 'check_in, check_out, adults, children, currency, room_amount, total_amount, '
            . 'booking_status, special_request, terms_accepted_at, policy_version, '
            . 'confirmation_deadline_at, created_at, updated_at, deleted_at'
            . ') VALUES ('
            . ':booking_reference, NULL, :guest_full_name, :guest_email, :guest_phone, '
            . ':check_in, :check_out, :adults, :children, :currency, :room_amount, :total_amount, '
            . ':booking_status, :special_request, :terms_accepted_at, :policy_version, '
            . ':confirmation_deadline_at, :created_at, :updated_at, :deleted_at'
            . ')',
        );
        $statement->execute([
            'booking_reference' => $reference,
            'guest_full_name' => 'Guest Example',
            'guest_email' => 'guest@example.test',
            'guest_phone' => '+9779812345678',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-13',
            'adults' => 2,
            'children' => 1,
            'currency' => Booking::CURRENCY_NPR,
            'room_amount' => '18000.00',
            'total_amount' => '18000.00',
            'booking_status' => Booking::STATUS_PENDING,
            'special_request' => 'Late arrival.',
            'terms_accepted_at' => '2026-08-01 10:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-08-02 10:00:00',
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
            'deleted_at' => $deletedAt,
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
            'price_per_night' => '6000.00',
            'nights' => 3,
            'subtotal' => '18000.00',
            'created_at' => '2026-08-01 10:00:00',
        ]);
    }

    private function insertEvent(
        int $bookingId,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $performedBy,
        ?string $reason,
        ?string $metadataJson,
        string $createdAt,
    ): void {
        $statement = $this->connection->prepare(
            'INSERT INTO booking_events ('
            . 'booking_id, event_type, from_status, to_status, performed_by, reason, metadata_json, created_at'
            . ') VALUES ('
            . ':booking_id, :event_type, :from_status, :to_status, :performed_by, :reason, :metadata_json, :created_at'
            . ')',
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => $performedBy,
            'reason' => $reason,
            'metadata_json' => $metadataJson,
            'created_at' => $createdAt,
        ]);
    }
}
