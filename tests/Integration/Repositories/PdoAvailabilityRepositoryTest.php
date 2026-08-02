<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\StayPeriod;
use App\Repositories\PdoAvailabilityRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PdoAvailabilityRepositoryTest extends DatabaseTestCase
{
    private PdoAvailabilityRepository $repository;

    private int $roomTypeId;

    private int $referenceSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $this->roomTypeId = $this->insertRoomType();
        $this->repository = new PdoAvailabilityRepository($this->connection);
    }

    public function testItUsesStrictOverlapBoundariesAndOnlyUnexpiredBlockingStatuses(): void
    {
        $requested = self::period('2026-08-10', '2026-08-13');
        $asOf = self::timestamp('2026-08-01 12:00:00');

        $activePending = $this->insertRoom('101');
        $this->insertBookingForRoom(
            $activePending,
            Booking::STATUS_PENDING,
            '2026-08-11',
            '2026-08-12',
            '2026-08-01 12:00:01',
        );

        $expiredPending = $this->insertRoom('102');
        $this->insertBookingForRoom(
            $expiredPending,
            Booking::STATUS_PENDING,
            '2026-08-11',
            '2026-08-12',
            '2026-08-01 12:00:00',
        );

        $confirmed = $this->insertRoom('103');
        $this->insertBookingForRoom(
            $confirmed,
            Booking::STATUS_CONFIRMED,
            '2026-08-11',
            '2026-08-12',
            '2026-07-01 00:00:00',
        );

        $checkedIn = $this->insertRoom('104');
        $this->insertBookingForRoom(
            $checkedIn,
            Booking::STATUS_CHECKED_IN,
            '2026-08-10',
            '2026-08-13',
            '2026-07-01 00:00:00',
        );

        $checkedOut = $this->insertRoom('105');
        $this->insertBookingForRoom(
            $checkedOut,
            Booking::STATUS_CHECKED_OUT,
            '2026-08-11',
            '2026-08-12',
            '2026-09-01 00:00:00',
        );

        $cancelled = $this->insertRoom('106');
        $this->insertBookingForRoom(
            $cancelled,
            Booking::STATUS_CANCELLED,
            '2026-08-11',
            '2026-08-12',
            '2026-09-01 00:00:00',
        );

        $rejected = $this->insertRoom('107');
        $this->insertBookingForRoom(
            $rejected,
            Booking::STATUS_REJECTED,
            '2026-08-11',
            '2026-08-12',
            '2026-09-01 00:00:00',
        );

        $adjacentBefore = $this->insertRoom('108');
        $this->insertBookingForRoom(
            $adjacentBefore,
            Booking::STATUS_CONFIRMED,
            '2026-08-08',
            '2026-08-10',
            '2026-07-01 00:00:00',
        );

        $adjacentAfter = $this->insertRoom('109');
        $this->insertBookingForRoom(
            $adjacentAfter,
            Booking::STATUS_CONFIRMED,
            '2026-08-13',
            '2026-08-15',
            '2026-07-01 00:00:00',
        );

        $deletedBooking = $this->insertRoom('110');
        $this->insertBookingForRoom(
            $deletedBooking,
            Booking::STATUS_CONFIRMED,
            '2026-08-11',
            '2026-08-12',
            '2026-07-01 00:00:00',
            '2026-08-01 11:00:00',
        );

        self::assertTrue($this->repository->hasBlockingOverlap($activePending, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap($expiredPending, $requested, $asOf));
        self::assertTrue($this->repository->hasBlockingOverlap($confirmed, $requested, $asOf));
        self::assertTrue($this->repository->hasBlockingOverlap($checkedIn, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap($checkedOut, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap($cancelled, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap($rejected, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap($adjacentBefore, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap($adjacentAfter, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap($deletedBooking, $requested, $asOf));
        self::assertFalse($this->repository->hasBlockingOverlap(0, $requested, $asOf));
    }

    public function testAvailableRoomIdsApplyVisibilityCapacityAndOverlapFilters(): void
    {
        $available = $this->insertRoom('201');
        $expiredPending = $this->insertRoom('202');
        $this->insertBookingForRoom(
            $expiredPending,
            Booking::STATUS_PENDING,
            '2026-08-11',
            '2026-08-12',
            '2026-08-01 11:59:59',
        );

        $adjacent = $this->insertRoom('203');
        $this->insertBookingForRoom(
            $adjacent,
            Booking::STATUS_CONFIRMED,
            '2026-08-08',
            '2026-08-10',
            '2026-07-01 00:00:00',
        );

        $nonBlocking = $this->insertRoom('204');
        $this->insertBookingForRoom(
            $nonBlocking,
            Booking::STATUS_CANCELLED,
            '2026-08-11',
            '2026-08-12',
            '2026-09-01 00:00:00',
        );

        $blocked = $this->insertRoom('205');
        $this->insertBookingForRoom(
            $blocked,
            Booking::STATUS_CONFIRMED,
            '2026-08-11',
            '2026-08-12',
            '2026-07-01 00:00:00',
        );

        $this->insertRoom('206', 1, 1);
        $this->insertRoom('207', 2, 0);
        $this->insertRoom('208', 2, 1, Room::STATUS_MAINTENANCE);
        $this->insertRoom('209', 2, 1, Room::STATUS_ACTIVE, false);
        $this->insertRoom('210', 2, 1, Room::STATUS_ACTIVE, true, '2026-08-01 10:00:00');

        self::assertSame(
            [$available, $expiredPending, $adjacent, $nonBlocking],
            $this->repository->findAvailableRoomIds(
                self::period('2026-08-10', '2026-08-13'),
                2,
                1,
                self::timestamp('2026-08-01 12:00:00'),
            ),
        );
    }

    public function testInvalidOccupancyReturnsNoRoomsWithoutWeakeningValidQueries(): void
    {
        $this->insertRoom('301');
        $period = self::period('2026-08-10', '2026-08-13');
        $asOf = self::timestamp('2026-08-01 12:00:00');

        self::assertSame([], $this->repository->findAvailableRoomIds($period, 0, 0, $asOf));
        self::assertSame([], $this->repository->findAvailableRoomIds($period, 1, -1, $asOf));
        self::assertCount(1, $this->repository->findAvailableRoomIds($period, 1, 0, $asOf));
    }

    public function testCalendarRangesUseTheCanonicalBlockingStatusesAndExclusiveBoundaries(): void
    {
        $roomId = $this->insertRoom('350');
        $asOf = self::timestamp('2026-08-01 12:00:00');
        $window = self::period('2026-08-10', '2026-08-20');

        $this->insertBookingForRoom($roomId, Booking::STATUS_CONFIRMED, '2026-08-09', '2026-08-11', '2026-07-01 00:00:00');
        $this->insertBookingForRoom($roomId, Booking::STATUS_CHECKED_IN, '2026-08-12', '2026-08-14', '2026-07-01 00:00:00');
        $this->insertBookingForRoom($roomId, Booking::STATUS_PENDING, '2026-08-15', '2026-08-16', '2026-08-01 12:00:01');
        $this->insertBookingForRoom($roomId, Booking::STATUS_PENDING, '2026-08-16', '2026-08-17', '2026-08-01 12:00:00');
        $this->insertBookingForRoom($roomId, Booking::STATUS_CHECKED_OUT, '2026-08-17', '2026-08-18', '2026-09-01 00:00:00');
        $this->insertBookingForRoom($roomId, Booking::STATUS_CANCELLED, '2026-08-18', '2026-08-19', '2026-09-01 00:00:00');
        $this->insertBookingForRoom($roomId, Booking::STATUS_REJECTED, '2026-08-19', '2026-08-20', '2026-09-01 00:00:00');
        $this->insertBookingForRoom($roomId, Booking::STATUS_CONFIRMED, '2026-08-08', '2026-08-10', '2026-07-01 00:00:00');
        $this->insertBookingForRoom($roomId, Booking::STATUS_CONFIRMED, '2026-08-20', '2026-08-21', '2026-07-01 00:00:00');

        $periods = $this->repository->findBlockingStayPeriods($roomId, $window, $asOf);

        self::assertSame([
            ['2026-08-09', '2026-08-11'],
            ['2026-08-12', '2026-08-14'],
            ['2026-08-15', '2026-08-16'],
        ], array_map(
            static fn (StayPeriod $period): array => [
                $period->checkIn()->format('Y-m-d'),
                $period->checkOut()->format('Y-m-d'),
            ],
            $periods,
        ));
        self::assertSame([], $this->repository->findBlockingStayPeriods(0, $window, $asOf));
    }

    public function testNonUtcClockAndDatabaseFailuresExposeOnlyStableMessages(): void
    {
        $roomId = $this->insertRoom('401');
        $period = self::period('2026-08-10', '2026-08-13');
        $nonUtc = new DateTimeImmutable('2026-08-01 12:00:00 +05:45');

        try {
            $this->repository->findAvailableRoomIds($period, 1, 0, $nonUtc);
            self::fail('Expected a sanitized non-UTC availability failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The availability lookup failed.', $exception->getMessage());
        }

        try {
            $this->repository->hasBlockingOverlap($roomId, $period, $nonUtc);
            self::fail('Expected a sanitized non-UTC overlap failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking overlap lookup failed.', $exception->getMessage());
        }

        try {
            $this->repository->findBlockingStayPeriods($roomId, $period, $nonUtc);
            self::fail('Expected a sanitized non-UTC calendar failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The availability calendar lookup failed.', $exception->getMessage());
        }

        $this->dropTables(['booking_rooms']);

        try {
            $this->repository->findAvailableRoomIds(
                $period,
                1,
                0,
                self::timestamp('2026-08-01 12:00:00'),
            );
            self::fail('Expected a sanitized availability database failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The availability lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->hasBlockingOverlap(
                $roomId,
                $period,
                self::timestamp('2026-08-01 12:00:00'),
            );
            self::fail('Expected a sanitized overlap database failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking overlap lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }


        try {
            $this->repository->findBlockingStayPeriods(
                $roomId,
                $period,
                self::timestamp('2026-08-01 12:00:00'),
            );
            self::fail('Expected a sanitized calendar database failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The availability calendar lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
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
            'name' => 'Availability Type',
            'slug' => 'availability-type',
            'base_price' => '6000.00',
            'capacity_adults' => 4,
            'capacity_children' => 3,
            'is_active' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(
        string $roomNumber,
        int $capacityAdults = 2,
        int $capacityChildren = 1,
        string $operationalStatus = Room::STATUS_ACTIVE,
        bool $published = true,
        ?string $deletedAt = null,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms ('
            . 'room_type_id, room_number, name, slug, description, price_per_night, '
            . 'capacity_adults, capacity_children, operational_status, is_published, deleted_at'
            . ') VALUES ('
            . ':room_type_id, :room_number, :name, :slug, NULL, :price_per_night, '
            . ':capacity_adults, :capacity_children, :operational_status, :is_published, :deleted_at'
            . ')',
        );
        $statement->execute([
            'room_type_id' => $this->roomTypeId,
            'room_number' => $roomNumber,
            'name' => 'Availability Room ' . $roomNumber,
            'slug' => 'availability-room-' . $roomNumber,
            'price_per_night' => '6000.00',
            'capacity_adults' => $capacityAdults,
            'capacity_children' => $capacityChildren,
            'operational_status' => $operationalStatus,
            'is_published' => $published ? 1 : 0,
            'deleted_at' => $deletedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBookingForRoom(
        int $roomId,
        string $status,
        string $checkIn,
        string $checkOut,
        string $confirmationDeadlineAt,
        ?string $deletedAt = null,
    ): int {
        $this->referenceSequence++;
        $reference = sprintf('TGH-AVAIL-%04d', $this->referenceSequence);
        $nights = (int) self::date($checkIn)->diff(self::date($checkOut))->format('%a');
        $subtotal = number_format($nights * 6000, 2, '.', '');

        $statement = $this->connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, '
            . 'check_in, check_out, adults, children, currency, room_amount, total_amount, '
            . 'booking_status, special_request, terms_accepted_at, policy_version, '
            . 'confirmation_deadline_at, created_at, updated_at, deleted_at'
            . ') VALUES ('
            . ':booking_reference, NULL, :guest_full_name, :guest_email, :guest_phone, '
            . ':check_in, :check_out, :adults, :children, :currency, :room_amount, :total_amount, '
            . ':booking_status, NULL, :terms_accepted_at, :policy_version, '
            . ':confirmation_deadline_at, :created_at, :updated_at, :deleted_at'
            . ')',
        );
        $statement->execute([
            'booking_reference' => $reference,
            'guest_full_name' => 'Availability Guest',
            'guest_email' => 'availability@example.test',
            'guest_phone' => '+9779812345678',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 1,
            'children' => 0,
            'currency' => Booking::CURRENCY_NPR,
            'room_amount' => $subtotal,
            'total_amount' => $subtotal,
            'booking_status' => $status,
            'terms_accepted_at' => '2026-07-01 00:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => $confirmationDeadlineAt,
            'created_at' => '2026-07-01 00:00:00',
            'updated_at' => '2026-07-01 00:00:00',
            'deleted_at' => $deletedAt,
        ]);
        $bookingId = (int) $this->connection->lastInsertId();

        $roomStatement = $this->connection->prepare(
            'INSERT INTO booking_rooms ('
            . 'booking_id, room_id, price_per_night, nights, subtotal, created_at'
            . ') VALUES ('
            . ':booking_id, :room_id, :price_per_night, :nights, :subtotal, :created_at'
            . ')',
        );
        $roomStatement->execute([
            'booking_id' => $bookingId,
            'room_id' => $roomId,
            'price_per_night' => '6000.00',
            'nights' => $nights,
            'subtotal' => $subtotal,
            'created_at' => '2026-07-01 00:00:00',
        ]);

        return $bookingId;
    }

    private static function period(string $checkIn, string $checkOut): StayPeriod
    {
        return new StayPeriod(self::date($checkIn), self::date($checkOut));
    }

    private static function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00 UTC');
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
