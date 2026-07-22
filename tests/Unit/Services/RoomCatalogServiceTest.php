<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Repositories\AmenityCatalogRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomTypeCatalogRepository;
use App\Requests\RoomUpsertRequest;
use App\Services\RoomCatalogService;
use App\Services\RoomCatalogTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoomCatalogServiceTest extends TestCase
{
    public function testItListsLoadsAndReturnsAmenityAssignments(): void
    {
        $stored = $this->room();
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('findAll')->willReturn([$stored]);
        $rooms->expects(self::exactly(2))->method('findById')->with(7)->willReturn($stored);
        $rooms->expects(self::once())->method('findAmenityIds')->with(7)->willReturn([3, 5]);
        $service = $this->service($rooms);

        self::assertSame([$stored], $service->listAll());
        self::assertSame($stored, $service->get(7));
        self::assertSame([3, 5], $service->amenityIds(7));
    }

    public function testMissingRoomReadsBecomeNotFoundFailures(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findById')->willReturn(null);
        $service = $this->service($rooms);

        foreach ([
            static fn () => $service->get(99),
            static fn () => $service->amenityIds(99),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected a missing room failure.');
            } catch (RoomCatalogException $exception) {
                self::assertTrue($exception->isNotFound());
                self::assertSame('The requested room was not found.', $exception->getMessage());
            }
        }
    }

    public function testItCreatesUsingValidatedValuesAndCommitsAmenityAssignments(): void
    {
        $request = $this->request();
        $stored = $this->room();
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('create')->with(
            2,
            '101',
            'Garden Deluxe',
            'garden-deluxe',
            "First line\nSecond line",
            '6200.50',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
        )->willReturn($stored);
        $rooms->expects(self::once())->method('replaceAmenities')->with(7, [3, 5]);
        $roomTypes = $this->createMock(RoomTypeCatalogRepository::class);
        $roomTypes->expects(self::once())->method('findById')->with(2)->willReturn($this->roomType());
        $amenities = $this->createMock(AmenityCatalogRepository::class);
        $amenities->expects(self::exactly(2))->method('findById')
            ->willReturnMap([[3, $this->amenity(3)], [5, $this->amenity(5)]]);
        $transaction = $this->successfulTransaction();

        $service = new RoomCatalogService($rooms, $roomTypes, $amenities, $transaction);

        self::assertSame($stored, $service->create($request));
    }

    public function testItUpdatesOnlyACurrentRoomAndSynchronizesAmenities(): void
    {
        $request = $this->request(status: Room::STATUS_MAINTENANCE, published: '0', amenityIds: ['5']);
        $current = $this->room();
        $updated = $this->room(status: Room::STATUS_MAINTENANCE, published: false);
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('findById')->with(7)->willReturn($current);
        $rooms->expects(self::once())->method('update')->with(
            7,
            2,
            '101',
            'Garden Deluxe',
            'garden-deluxe',
            "First line\nSecond line",
            '6200.50',
            2,
            1,
            Room::STATUS_MAINTENANCE,
            false,
        )->willReturn($updated);
        $rooms->expects(self::once())->method('replaceAmenities')->with(7, [5]);
        $roomTypes = $this->createStub(RoomTypeCatalogRepository::class);
        $roomTypes->method('findById')->willReturn($this->roomType());
        $amenities = $this->createStub(AmenityCatalogRepository::class);
        $amenities->method('findById')->willReturn($this->amenity(5));
        $transaction = $this->successfulTransaction();

        $service = new RoomCatalogService($rooms, $roomTypes, $amenities, $transaction);

        self::assertSame($updated, $service->update(7, $request));
    }

    public function testMissingRoomTypeOrAmenityRollsBackWithoutWriting(): void
    {
        $cases = [
            'room type' => [null, $this->amenity(3), 'The requested room type was not found.'],
            'amenity' => [$this->roomType(), null, 'The requested amenity was not found.'],
        ];

        foreach ($cases as [$roomType, $amenity, $message]) {
            $rooms = $this->createMock(RoomCatalogRepository::class);
            $rooms->expects(self::never())->method('create');
            $rooms->expects(self::never())->method('replaceAmenities');
            $roomTypes = $this->createStub(RoomTypeCatalogRepository::class);
            $roomTypes->method('findById')->willReturn($roomType);
            $amenities = $this->createStub(AmenityCatalogRepository::class);
            $amenities->method('findById')->willReturn($amenity);
            $transaction = $this->failingTransaction();
            $service = new RoomCatalogService($rooms, $roomTypes, $amenities, $transaction);

            try {
                $service->create($this->request(amenityIds: ['3']));
                self::fail('Expected a missing room reference failure.');
            } catch (RoomCatalogException $exception) {
                self::assertTrue($exception->isNotFound());
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testExpectedRoomConflictsRollBackAndPassThroughUnchanged(): void
    {
        $conflict = RoomCatalogException::duplicateRoomNumber();
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('create')->willThrowException($conflict);
        $roomTypes = $this->createStub(RoomTypeCatalogRepository::class);
        $roomTypes->method('findById')->willReturn($this->roomType());
        $amenities = $this->createStub(AmenityCatalogRepository::class);
        $amenities->method('findById')->willReturn($this->amenity(3));
        $service = new RoomCatalogService(
            $rooms,
            $roomTypes,
            $amenities,
            $this->failingTransaction(),
        );

        try {
            $service->create($this->request(amenityIds: ['3']));
            self::fail('Expected the room conflict.');
        } catch (RoomCatalogException $exception) {
            self::assertSame($conflict, $exception);
            self::assertTrue($exception->isConflict());
        }
    }

    public function testUnexpectedWriteFailuresRollBackAndBecomeSafeSystemFailures(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('create')->willThrowException(new RuntimeException('sensitive SQL details'));
        $roomTypes = $this->createStub(RoomTypeCatalogRepository::class);
        $roomTypes->method('findById')->willReturn($this->roomType());
        $amenities = $this->createStub(AmenityCatalogRepository::class);
        $amenities->method('findById')->willReturn($this->amenity(3));
        $service = new RoomCatalogService(
            $rooms,
            $roomTypes,
            $amenities,
            $this->failingTransaction(),
        );

        try {
            $service->create($this->request(amenityIds: ['3']));
            self::fail('Expected a safe room-catalogue failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive SQL details', $exception->getMessage());
        }
    }

    public function testItSoftDeletesInsideATransaction(): void
    {
        $deleted = $this->room(deleted: true);
        $rooms = $this->createMock(RoomCatalogRepository::class);
        $rooms->expects(self::once())->method('softDelete')->with(7)->willReturn($deleted);
        $service = new RoomCatalogService(
            $rooms,
            $this->createStub(RoomTypeCatalogRepository::class),
            $this->createStub(AmenityCatalogRepository::class),
            $this->successfulTransaction(),
        );

        self::assertSame($deleted, $service->softDelete(7));
    }

    public function testMissingSoftDeleteTargetRollsBackAsNotFound(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('softDelete')->willReturn(null);
        $service = new RoomCatalogService(
            $rooms,
            $this->createStub(RoomTypeCatalogRepository::class),
            $this->createStub(AmenityCatalogRepository::class),
            $this->failingTransaction(),
        );

        try {
            $service->softDelete(99);
            self::fail('Expected a missing room failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testCommitFailureRollsBackAndBecomesASafeSystemFailure(): void
    {
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('softDelete')->willReturn($this->room(deleted: true));
        $transaction = $this->createMock(RoomCatalogTransaction::class);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::once())->method('commit')
            ->willThrowException(new RuntimeException('commit internals'));
        $transaction->expects(self::once())->method('inProgress')->willReturn(true);
        $transaction->expects(self::once())->method('rollBack');
        $service = new RoomCatalogService(
            $rooms,
            $this->createStub(RoomTypeCatalogRepository::class),
            $this->createStub(AmenityCatalogRepository::class),
            $transaction,
        );

        try {
            $service->softDelete(7);
            self::fail('Expected a safe transaction failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('commit internals', $exception->getMessage());
        }
    }

    private function service(RoomCatalogRepository $rooms): RoomCatalogService
    {
        return new RoomCatalogService(
            $rooms,
            $this->createStub(RoomTypeCatalogRepository::class),
            $this->createStub(AmenityCatalogRepository::class),
            $this->createStub(RoomCatalogTransaction::class),
        );
    }

    private function successfulTransaction(): RoomCatalogTransaction
    {
        $transaction = $this->createMock(RoomCatalogTransaction::class);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::once())->method('commit');
        $transaction->expects(self::never())->method('rollBack');

        return $transaction;
    }

    private function failingTransaction(): RoomCatalogTransaction
    {
        $transaction = $this->createMock(RoomCatalogTransaction::class);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::never())->method('commit');
        $transaction->expects(self::once())->method('inProgress')->willReturn(true);
        $transaction->expects(self::once())->method('rollBack');

        return $transaction;
    }

    /** @param list<mixed> $amenityIds */
    private function request(
        string $status = Room::STATUS_ACTIVE,
        mixed $published = '1',
        array $amenityIds = ['5', '3'],
    ): RoomUpsertRequest {
        return RoomUpsertRequest::fromInput(
            '2',
            ' 101 ',
            ' Garden Deluxe ',
            ' garden-deluxe ',
            " First line\r\nSecond line ",
            '6200.5',
            '2',
            '1',
            $status,
            $published,
            $amenityIds,
        );
    }

    private function roomType(): RoomType
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new RoomType(
            2,
            'Deluxe Room',
            'deluxe-room',
            null,
            '5000.00',
            2,
            1,
            true,
            $timestamp,
            $timestamp,
        );
    }

    private function amenity(int $id): Amenity
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new Amenity($id, 'Amenity ' . $id, null, true, $timestamp, $timestamp);
    }

    private function room(
        string $status = Room::STATUS_ACTIVE,
        bool $published = true,
        bool $deleted = false,
    ): Room {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new Room(
            7,
            $this->roomType(),
            '101',
            'Garden Deluxe',
            'garden-deluxe',
            "First line\nSecond line",
            '6200.50',
            2,
            1,
            $status,
            $published,
            $timestamp,
            $timestamp,
            $deleted ? new DateTimeImmutable('2026-07-22 12:13:14 UTC') : null,
        );
    }
}
