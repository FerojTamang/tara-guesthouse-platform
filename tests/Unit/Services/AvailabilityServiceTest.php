<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AvailabilityException;
use App\Models\AvailabilityResult;
use App\Models\StayPeriod;
use App\Repositories\AvailabilityRepository;
use App\Requests\AvailabilityRequest;
use App\Services\AvailabilityService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AvailabilityServiceTest extends TestCase
{
    public function testItCoordinatesOneSearchUsingOneUtcClockSnapshot(): void
    {
        $repository = new AvailabilityServiceRepositoryFake();
        $repository->roomIds = [4, 8];
        $clockCalls = 0;
        $checkedAt = self::timestamp('2026-08-01 12:00:00');
        $request = AvailabilityRequest::fromInput('2026-08-10', '2026-08-13', 2, 1);
        $service = new AvailabilityService(
            $repository,
            static function () use (&$clockCalls, $checkedAt): DateTimeImmutable {
                $clockCalls++;

                return $checkedAt;
            },
        );

        $result = $service->search($request);

        self::assertInstanceOf(AvailabilityResult::class, $result);
        self::assertSame(1, $clockCalls);
        self::assertSame(1, $repository->searchCalls);
        self::assertSame($request->stayPeriod(), $repository->stayPeriod);
        self::assertSame(2, $repository->adults);
        self::assertSame(1, $repository->children);
        self::assertSame($checkedAt, $repository->asOf);
        self::assertSame([4, 8], $result->roomIds());
        self::assertSame($checkedAt, $result->checkedAt());
        self::assertSame(3, $result->nights());
    }

    public function testItPreservesAnEmptyRepositoryResult(): void
    {
        $repository = new AvailabilityServiceRepositoryFake();
        $service = new AvailabilityService(
            $repository,
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );

        $result = $service->search(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-11', 1, 0),
        );

        self::assertSame([], $result->roomIds());
        self::assertFalse($result->hasAvailableRooms());
    }

    public function testRepositoryFailuresBecomeSanitizedSystemFailures(): void
    {
        $repository = new AvailabilityServiceRepositoryFake();
        $repository->failure = new RuntimeException(
            'SQLSTATE[HY000] sensitive database details',
        );
        $service = new AvailabilityService(
            $repository,
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );

        try {
            $service->search(
                AvailabilityRequest::fromInput('2026-08-10', '2026-08-11', 1, 0),
            );
            self::fail('Expected a sanitized availability failure.');
        } catch (AvailabilityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertFalse($exception->isValidationFailure());
            self::assertSame('Availability operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    public function testInvalidRepositoryIdentifiersFailClosed(): void
    {
        $repository = new AvailabilityServiceRepositoryFake();
        $repository->roomIds = [5, 5];
        $service = new AvailabilityService(
            $repository,
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );

        $this->expectException(AvailabilityException::class);
        $this->expectExceptionMessage('Availability operation failed.');

        $service->search(
            AvailabilityRequest::fromInput('2026-08-10', '2026-08-11', 1, 0),
        );
    }

    public function testInvalidClockResultsFailBeforeRepositoryAccess(): void
    {
        $repository = new AvailabilityServiceRepositoryFake();
        $service = new AvailabilityService(
            $repository,
            static fn (): string => 'not-a-date',
        );

        try {
            $service->search(
                AvailabilityRequest::fromInput('2026-08-10', '2026-08-11', 1, 0),
            );
            self::fail('Expected an invalid clock failure.');
        } catch (AvailabilityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Availability operation failed.', $exception->getMessage());
            self::assertSame(0, $repository->searchCalls);
        }
    }

    public function testNonUtcClockResultsFailBeforeRepositoryAccess(): void
    {
        $repository = new AvailabilityServiceRepositoryFake();
        $service = new AvailabilityService(
            $repository,
            static fn (): DateTimeImmutable => new DateTimeImmutable(
                '2026-08-01 12:00:00 +05:45',
            ),
        );

        $this->expectException(AvailabilityException::class);
        $this->expectExceptionMessage('Availability operation failed.');

        try {
            $service->search(
                AvailabilityRequest::fromInput('2026-08-10', '2026-08-11', 1, 0),
            );
        } finally {
            self::assertSame(0, $repository->searchCalls);
        }
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}

final class AvailabilityServiceRepositoryFake implements AvailabilityRepository
{
    /** @var list<int> */
    public array $roomIds = [];

    public ?RuntimeException $failure = null;

    public int $searchCalls = 0;

    public ?StayPeriod $stayPeriod = null;

    public ?int $adults = null;

    public ?int $children = null;

    public ?DateTimeImmutable $asOf = null;

    /**
     * @return list<int>
     */
    public function findAvailableRoomIds(
        StayPeriod $stayPeriod,
        int $adults,
        int $children,
        DateTimeImmutable $asOf,
    ): array {
        $this->searchCalls++;
        $this->stayPeriod = $stayPeriod;
        $this->adults = $adults;
        $this->children = $children;
        $this->asOf = $asOf;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->roomIds;
    }

    public function hasBlockingOverlap(
        int $roomId,
        StayPeriod $stayPeriod,
        DateTimeImmutable $asOf,
    ): bool {
        return false;
    }

    public function findBlockingStayPeriods(
        int $roomId,
        StayPeriod $window,
        DateTimeImmutable $asOf,
    ): array {
        return [];
    }
}
