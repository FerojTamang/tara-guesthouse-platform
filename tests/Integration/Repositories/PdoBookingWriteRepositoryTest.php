<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\BookingException;
use App\Exceptions\DatabaseException;
use App\Models\Booking;
use App\Models\BookingQuote;
use App\Models\Room;
use App\Repositories\PdoBookingWriteRepository;
use App\Repositories\RoomRepository;
use App\Requests\GuestBookingRequest;
use App\Services\PdoBookingTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PdoBookingWriteRepositoryTest extends DatabaseTestCase
{
    private PdoBookingWriteRepository $writer;

    private PdoBookingTransaction $transaction;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $roomTypeId = $this->insertRoomType();
        $roomId = $this->insertRoom($roomTypeId);
        $room = (new RoomRepository($this->connection))->findById($roomId);

        self::assertInstanceOf(Room::class, $room);

        $this->room = $room;
        $this->writer = new PdoBookingWriteRepository($this->connection);
        $this->transaction = new PdoBookingTransaction($this->connection);
    }

    public function testItCreatesThePendingBookingRoomAndAppendOnlyEventTogether(): void
    {
        $this->transaction->begin();

        $booking = $this->writer->createPending(
            $this->quote(),
            'TGH-WRITE-0001',
            self::timestamp('2026-08-01 18:00:00'),
        );

        $this->transaction->commit();

        self::assertSame('TGH-WRITE-0001', $booking->bookingReference());
        self::assertNull($booking->userId());
        self::assertSame(Booking::STATUS_PENDING, $booking->bookingStatus());
        self::assertSame('Asha Sharma', $booking->guestFullName());
        self::assertSame('asha@example.test', $booking->guestEmail());
        self::assertSame('+9779800000000', $booking->guestPhone());
        self::assertSame('2026-08-10', $booking->checkIn()->format('Y-m-d'));
        self::assertSame('2026-08-12', $booking->checkOut()->format('Y-m-d'));
        self::assertSame('9000.00', $booking->roomAmount());
        self::assertSame('9000.00', $booking->totalAmount());
        self::assertSame(
            '2026-08-01 18:00:00',
            $booking->confirmationDeadlineAt()->format('Y-m-d H:i:s'),
        );

        $roomRow = $this->connection->query(
            'SELECT * FROM booking_rooms WHERE booking_id = ' . $booking->id(),
        )->fetch();
        self::assertIsArray($roomRow);
        self::assertSame($this->room->id(), (int) $roomRow['room_id']);
        self::assertSame('4500.00', $roomRow['price_per_night']);
        self::assertSame(2, (int) $roomRow['nights']);
        self::assertSame('9000.00', $roomRow['subtotal']);

        $event = $this->connection->query(
            'SELECT * FROM booking_events WHERE booking_id = ' . $booking->id(),
        )->fetch();
        self::assertIsArray($event);
        self::assertSame('booking_created', $event['event_type']);
        self::assertNull($event['from_status']);
        self::assertSame(Booking::STATUS_PENDING, $event['to_status']);
        self::assertNull($event['performed_by']);
        self::assertNull($event['reason']);
        self::assertNull($event['metadata_json']);
    }

    public function testRollbackRemovesAllThreeCreationRecords(): void
    {
        $this->transaction->begin();

        $booking = $this->writer->createPending(
            $this->quote(),
            'TGH-WRITE-ROLLBACK',
            self::timestamp('2026-08-01 18:00:00'),
        );

        self::assertGreaterThan(0, $booking->id());
        $this->transaction->rollBack();

        self::assertSame(
            0,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM bookings WHERE booking_reference = 'TGH-WRITE-ROLLBACK'",
            )->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COUNT(*) FROM booking_rooms')->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COUNT(*) FROM booking_events')->fetchColumn(),
        );
    }

    public function testAnActiveTransactionIsRequiredBeforeAnyWrite(): void
    {
        try {
            $this->writer->createPending(
                $this->quote(),
                'TGH-NO-TRANSACTION',
                self::timestamp('2026-08-01 18:00:00'),
            );
            self::fail('Expected a caller-owned transaction requirement.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking write failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
        );
    }

    public function testDuplicateReferenceIsAStableRetryableDomainFailure(): void
    {
        $this->transaction->begin();
        $this->writer->createPending(
            $this->quote(),
            'TGH-DUPLICATE-0001',
            self::timestamp('2026-08-01 18:00:00'),
        );
        $this->transaction->commit();

        $this->transaction->begin();

        try {
            $this->writer->createPending(
                $this->quote(),
                'TGH-DUPLICATE-0001',
                self::timestamp('2026-08-01 18:00:00'),
            );
            self::fail('Expected a duplicate booking-reference failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isDuplicateReference());
            self::assertSame(
                'The booking reference is already in use.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('Duplicate entry', $exception->getMessage());
        } finally {
            if ($this->transaction->inProgress()) {
                $this->transaction->rollBack();
            }
        }

        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM bookings WHERE booking_reference = 'TGH-DUPLICATE-0001'",
            )->fetchColumn(),
        );
    }

    public function testInvalidDeadlineFailsBeforeWriting(): void
    {
        $this->transaction->begin();

        try {
            $this->writer->createPending(
                $this->quote(),
                'TGH-BAD-DEADLINE',
                self::timestamp('2026-08-01 12:00:00'),
            );
            self::fail('Expected an invalid deadline failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking write failed.', $exception->getMessage());
        } finally {
            $this->transaction->rollBack();
        }

        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
        );
    }

    private function quote(): BookingQuote
    {
        $request = GuestBookingRequest::fromInput(
            $this->room->id(),
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            '2026-08-10',
            '2026-08-12',
            2,
            0,
            'Late arrival.',
            true,
            'booking-policy-v1',
        );

        return new BookingQuote(
            $request,
            $this->room,
            Booking::CURRENCY_NPR,
            '4500.00',
            2,
            '9000.00',
            '9000.00',
            self::timestamp('2026-08-01 12:00:00'),
        );
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
            'name' => 'Booking Write Type',
            'slug' => 'booking-write-type',
            'base_price' => '4500.00',
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
            'room_number' => 'BW-101',
            'name' => 'Booking Write Room',
            'slug' => 'booking-write-room',
            'price_per_night' => '4500.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'operational_status' => Room::STATUS_ACTIVE,
            'is_published' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
