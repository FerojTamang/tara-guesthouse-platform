<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RoomCatalogException;
use App\Models\RoomType;
use App\Repositories\RoomTypeCatalogRepository;
use App\Requests\RoomTypeUpsertRequest;
use App\Services\RoomTypeCatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoomTypeCatalogServiceTest extends TestCase
{
    public function testItListsAllAndActiveRoomTypes(): void
    {
        $all = [$this->roomType(1, false), $this->roomType(2, true)];
        $active = [$all[1]];
        $repository = $this->createMock(RoomTypeCatalogRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn($all);
        $repository->expects(self::once())->method('findAllActive')->willReturn($active);
        $service = new RoomTypeCatalogService($repository);

        self::assertSame($all, $service->listAll());
        self::assertSame($active, $service->listActive());
    }

    public function testItLoadsOneRoomTypeAndRejectsAMissingIdentifier(): void
    {
        $stored = $this->roomType();
        $repository = $this->createMock(RoomTypeCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('findById')
            ->willReturnMap([[7, $stored], [99, null]]);
        $service = new RoomTypeCatalogService($repository);

        self::assertSame($stored, $service->get(7));

        try {
            $service->get(99);
            self::fail('Expected a missing room-type failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The requested room type was not found.', $exception->getMessage());
        }
    }

    public function testItCreatesUsingOnlyValidatedRequestValues(): void
    {
        $request = $this->request();
        $stored = $this->roomType();
        $repository = $this->createMock(RoomTypeCatalogRepository::class);
        $repository->expects(self::once())->method('create')->with(
            'Deluxe Suite',
            'deluxe-suite',
            "First line\nSecond line",
            '6500.50',
            2,
            1,
            true,
        )->willReturn($stored);

        self::assertSame($stored, (new RoomTypeCatalogService($repository))->create($request));
    }

    public function testItUpdatesUsingOnlyValidatedRequestValues(): void
    {
        $request = $this->request(active: '0');
        $stored = $this->roomType(active: false);
        $repository = $this->createMock(RoomTypeCatalogRepository::class);
        $repository->expects(self::once())->method('update')->with(
            7,
            'Deluxe Suite',
            'deluxe-suite',
            "First line\nSecond line",
            '6500.50',
            2,
            1,
            false,
        )->willReturn($stored);

        self::assertSame($stored, (new RoomTypeCatalogService($repository))->update(7, $request));
    }

    public function testMissingUpdateBecomesANotFoundFailure(): void
    {
        $repository = $this->createStub(RoomTypeCatalogRepository::class);
        $repository->method('update')->willReturn(null);

        try {
            (new RoomTypeCatalogService($repository))->update(99, $this->request());
            self::fail('Expected a missing room-type failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testItActivatesAndDeactivatesThroughExplicitOperations(): void
    {
        $active = $this->roomType(active: true);
        $inactive = $this->roomType(active: false);
        $repository = $this->createMock(RoomTypeCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('setActive')
            ->willReturnMap([[7, true, $active], [7, false, $inactive]]);
        $service = new RoomTypeCatalogService($repository);

        self::assertSame($active, $service->activate(7));
        self::assertSame($inactive, $service->deactivate(7));
    }

    public function testMissingStateTargetBecomesANotFoundFailure(): void
    {
        $repository = $this->createStub(RoomTypeCatalogRepository::class);
        $repository->method('setActive')->willReturn(null);

        try {
            (new RoomTypeCatalogService($repository))->deactivate(99);
            self::fail('Expected a missing room-type failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testExpectedCatalogueFailuresPassThroughUnchanged(): void
    {
        $conflict = RoomCatalogException::duplicateRoomTypeSlug();
        $repository = $this->createStub(RoomTypeCatalogRepository::class);
        $repository->method('create')->willThrowException($conflict);

        try {
            (new RoomTypeCatalogService($repository))->create($this->request());
            self::fail('Expected the room-type conflict.');
        } catch (RoomCatalogException $exception) {
            self::assertSame($conflict, $exception);
            self::assertTrue($exception->isConflict());
        }
    }

    public function testUnexpectedRepositoryFailuresBecomeOneSafeSystemFailure(): void
    {
        $repository = $this->createStub(RoomTypeCatalogRepository::class);
        $repository->method('findAll')->willThrowException(new RuntimeException('sensitive SQL details'));

        try {
            (new RoomTypeCatalogService($repository))->listAll();
            self::fail('Expected a safe room-catalogue failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive SQL details', $exception->getMessage());
        }
    }

    private function request(mixed $active = '1'): RoomTypeUpsertRequest
    {
        return RoomTypeUpsertRequest::fromInput(
            ' Deluxe Suite ',
            ' deluxe-suite ',
            " First line\r\nSecond line ",
            '6500.5',
            '2',
            '1',
            $active,
        );
    }

    private function roomType(int $id = 7, bool $active = true): RoomType
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new RoomType(
            $id,
            'Deluxe Suite',
            'deluxe-suite',
            "First line\nSecond line",
            '6500.50',
            2,
            1,
            $active,
            $timestamp,
            $timestamp,
        );
    }
}
