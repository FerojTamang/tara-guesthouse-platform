<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Repositories\AmenityCatalogRepository;
use App\Requests\AmenityUpsertRequest;
use App\Services\AmenityCatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AmenityCatalogServiceTest extends TestCase
{
    public function testItListsAllAndActiveAmenities(): void
    {
        $all = [$this->amenity(1, false), $this->amenity(2, true)];
        $active = [$all[1]];
        $repository = $this->createMock(AmenityCatalogRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn($all);
        $repository->expects(self::once())->method('findAllActive')->willReturn($active);
        $service = new AmenityCatalogService($repository);

        self::assertSame($all, $service->listAll());
        self::assertSame($active, $service->listActive());
    }

    public function testItLoadsOneAmenityAndRejectsAMissingIdentifier(): void
    {
        $stored = $this->amenity();
        $repository = $this->createMock(AmenityCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('findById')
            ->willReturnMap([[5, $stored], [99, null]]);
        $service = new AmenityCatalogService($repository);

        self::assertSame($stored, $service->get(5));

        try {
            $service->get(99);
            self::fail('Expected a missing amenity failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The requested amenity was not found.', $exception->getMessage());
        }
    }

    public function testItCreatesUsingOnlyValidatedRequestValues(): void
    {
        $request = $this->request();
        $stored = $this->amenity();
        $repository = $this->createMock(AmenityCatalogRepository::class);
        $repository->expects(self::once())->method('create')
            ->with('Airport transfer', 'car-front', true)
            ->willReturn($stored);

        self::assertSame($stored, (new AmenityCatalogService($repository))->create($request));
    }

    public function testItUpdatesUsingOnlyValidatedRequestValues(): void
    {
        $request = $this->request(active: '0');
        $stored = $this->amenity(active: false);
        $repository = $this->createMock(AmenityCatalogRepository::class);
        $repository->expects(self::once())->method('update')
            ->with(5, 'Airport transfer', 'car-front', false)
            ->willReturn($stored);

        self::assertSame($stored, (new AmenityCatalogService($repository))->update(5, $request));
    }

    public function testMissingUpdateBecomesANotFoundFailure(): void
    {
        $repository = $this->createStub(AmenityCatalogRepository::class);
        $repository->method('update')->willReturn(null);

        try {
            (new AmenityCatalogService($repository))->update(99, $this->request());
            self::fail('Expected a missing amenity failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testItActivatesAndDeactivatesThroughExplicitOperations(): void
    {
        $active = $this->amenity(active: true);
        $inactive = $this->amenity(active: false);
        $repository = $this->createMock(AmenityCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('setActive')
            ->willReturnMap([[5, true, $active], [5, false, $inactive]]);
        $service = new AmenityCatalogService($repository);

        self::assertSame($active, $service->activate(5));
        self::assertSame($inactive, $service->deactivate(5));
    }

    public function testMissingStateTargetBecomesANotFoundFailure(): void
    {
        $repository = $this->createStub(AmenityCatalogRepository::class);
        $repository->method('setActive')->willReturn(null);

        try {
            (new AmenityCatalogService($repository))->deactivate(99);
            self::fail('Expected a missing amenity failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testExpectedCatalogueFailuresPassThroughUnchanged(): void
    {
        $conflict = RoomCatalogException::duplicateAmenityName();
        $repository = $this->createStub(AmenityCatalogRepository::class);
        $repository->method('create')->willThrowException($conflict);

        try {
            (new AmenityCatalogService($repository))->create($this->request());
            self::fail('Expected the amenity conflict.');
        } catch (RoomCatalogException $exception) {
            self::assertSame($conflict, $exception);
            self::assertTrue($exception->isConflict());
        }
    }

    public function testUnexpectedRepositoryFailuresBecomeOneSafeSystemFailure(): void
    {
        $repository = $this->createStub(AmenityCatalogRepository::class);
        $repository->method('findAll')->willThrowException(new RuntimeException('sensitive SQL details'));

        try {
            (new AmenityCatalogService($repository))->listAll();
            self::fail('Expected a safe room-catalogue failure.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive SQL details', $exception->getMessage());
        }
    }

    private function request(mixed $active = '1'): AmenityUpsertRequest
    {
        return AmenityUpsertRequest::fromInput(
            ' Airport transfer ',
            ' car-front ',
            $active,
        );
    }

    private function amenity(int $id = 5, bool $active = true): Amenity
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new Amenity(
            $id,
            'Airport transfer',
            'car-front',
            $active,
            $timestamp,
            $timestamp,
        );
    }
}
