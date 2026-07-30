<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Repositories\ActivityCatalogRepository;
use App\Services\ActivityCatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ActivityCatalogServiceTest extends TestCase
{
    public function testItListsAdministratorAndPublicCatalogues(): void
    {
        $public = self::activity(1, true, true);
        $inactive = self::activity(2, false, true);
        $repository = $this->createMock(ActivityCatalogRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn([$public, $inactive]);
        $repository->expects(self::once())->method('findAllPublic')->willReturn([$public, $inactive]);
        $service = new ActivityCatalogService($repository);

        self::assertSame([$public, $inactive], $service->listAll());
        self::assertSame([$public], $service->listPublic());
    }

    public function testItLoadsByIdentifierAndSlugAndRejectsMissingRecords(): void
    {
        $stored = self::activity();
        $repository = $this->createMock(ActivityCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('findById')
            ->willReturnMap([[7, $stored], [99, null]]);
        $repository->expects(self::exactly(2))->method('findBySlug')
            ->willReturnMap([['jungle-walk', $stored], ['missing', null]]);
        $service = new ActivityCatalogService($repository);

        self::assertSame($stored, $service->get(7));
        self::assertSame($stored, $service->getBySlug('jungle-walk'));

        foreach ([
            static fn (): Activity => $service->get(99),
            static fn (): Activity => $service->getBySlug('missing'),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected a missing activity failure.');
            } catch (ActivityException $exception) {
                self::assertTrue($exception->isNotFound());
                self::assertSame('The requested activity was not found.', $exception->getMessage());
            }
        }
    }

    public function testExpectedActivityFailuresPassThroughUnchanged(): void
    {
        $failure = ActivityException::duplicateSlug();
        $repository = $this->createStub(ActivityCatalogRepository::class);
        $repository->method('findAll')->willThrowException($failure);

        try {
            (new ActivityCatalogService($repository))->listAll();
            self::fail('Expected the activity conflict.');
        } catch (ActivityException $exception) {
            self::assertSame($failure, $exception);
            self::assertTrue($exception->isConflict());
        }
    }

    public function testUnexpectedRepositoryFailuresBecomeSafeSystemFailures(): void
    {
        $repository = $this->createStub(ActivityCatalogRepository::class);
        $repository->method('findAll')->willThrowException(
            new RuntimeException('SQLSTATE private activity data'),
        );

        try {
            (new ActivityCatalogService($repository))->listAll();
            self::fail('Expected a safe activity failure.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }
    }

    private static function activity(
        int $id = 7,
        bool $active = true,
        bool $published = true,
    ): Activity {
        $timestamp = new DateTimeImmutable('2026-07-30 07:00:00 UTC');

        return new Activity(
            $id,
            'Jungle Walk ' . $id,
            'jungle-walk-' . $id,
            'Explore the surrounding forest with a local guide.',
            '2500.00',
            $active,
            $published,
            10,
            $timestamp,
            $timestamp,
        );
    }
}
