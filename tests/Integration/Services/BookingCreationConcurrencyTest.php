<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\ConnectionFactory;
use App\Core\MigrationRunner;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Room;
use App\Repositories\PdoAvailabilityRepository;
use App\Repositories\PdoBookingWriteRepository;
use App\Repositories\RoomRepository;
use App\Requests\GuestBookingRequest;
use App\Services\BookingCreationService;
use App\Services\BookingService;
use App\Services\PdoBookingTransaction;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class BookingCreationConcurrencyTest extends DatabaseTestCase
{
    private int $roomId;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $roomTypeId = $this->insertRoomType();
        $this->roomId = $this->insertRoom($roomTypeId);
    }

    public function testCommittedBookingBlocksASecondOverlappingCreation(): void
    {
        $secondConnection = $this->secondConnection();

        try {
            $first = $this->service($this->connection);
            $second = $this->service($secondConnection);

            $created = $first->createPending(
                $this->request(),
                'TGH-CONCURRENT-0001',
                self::timestamp('2026-08-01 18:00:00'),
            );

            self::assertSame(Booking::STATUS_PENDING, $created->bookingStatus());

            try {
                $second->createPending(
                    $this->request(),
                    'TGH-CONCURRENT-0002',
                    self::timestamp('2026-08-01 18:00:00'),
                );
                self::fail('Expected the committed overlap to block the second booking.');
            } catch (BookingException $exception) {
                self::assertTrue($exception->isConflict());
                self::assertSame(
                    'The selected room is not available for this stay.',
                    $exception->getMessage(),
                );
            }

            self::assertFalse($secondConnection->inTransaction());
            self::assertSame(1, $this->countRows('bookings'));
            self::assertSame(1, $this->countRows('booking_rooms'));
            self::assertSame(1, $this->countRows('booking_events'));
        } finally {
            if ($secondConnection->inTransaction()) {
                $secondConnection->rollBack();
            }
        }
    }

    public function testCompetingCreationCannotPassTheRoomLock(): void
    {
        $secondConnection = $this->secondConnection();
        $secondConnection->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $primaryRooms = new RoomRepository($this->connection);
        $this->connection->beginTransaction();

        try {
            self::assertInstanceOf(
                Room::class,
                $primaryRooms->lockCurrentById($this->roomId),
            );

            try {
                $this->service($secondConnection)->createPending(
                    $this->request(),
                    'TGH-LOCKED-0001',
                    self::timestamp('2026-08-01 18:00:00'),
                );
                self::fail('Expected the competing booking creation to fail at the room lock.');
            } catch (BookingException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Booking operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }

            self::assertFalse($secondConnection->inTransaction());
            self::assertSame(0, $this->countRows('bookings'));
        } finally {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            if ($secondConnection->inTransaction()) {
                $secondConnection->rollBack();
            }
        }

        $created = $this->service($secondConnection)->createPending(
            $this->request(),
            'TGH-LOCKED-RETRY',
            self::timestamp('2026-08-01 18:00:00'),
        );

        self::assertSame('TGH-LOCKED-RETRY', $created->bookingReference());
        self::assertSame(1, $this->countRows('bookings'));
    }

    private function service(PDO $connection): BookingCreationService
    {
        $rooms = new RoomRepository($connection);

        return new BookingCreationService(
            $rooms,
            new BookingService(
                $rooms,
                new PdoAvailabilityRepository($connection),
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
            new PdoBookingWriteRepository($connection),
            new PdoBookingTransaction($connection),
        );
    }

    private function request(): GuestBookingRequest
    {
        return GuestBookingRequest::fromInput(
            $this->roomId,
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            '2026-08-10',
            '2026-08-12',
            2,
            0,
            null,
            true,
            'booking-policy-v1',
        );
    }

    private function secondConnection(): PDO
    {
        return (new ConnectionFactory($this->databaseConfiguration))->create();
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->query(
            'SELECT COUNT(*) FROM ' . $table,
        )->fetchColumn();
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
            'name' => 'Booking Concurrency Type',
            'slug' => 'booking-concurrency-type',
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
            'room_number' => 'BC-101',
            'name' => 'Booking Concurrency Room',
            'slug' => 'booking-concurrency-room',
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
