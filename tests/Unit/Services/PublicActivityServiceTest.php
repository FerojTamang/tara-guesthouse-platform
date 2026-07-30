<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Repositories\ActivityCatalogRepository;
use App\Services\PublicActivityService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicActivityServiceTest extends TestCase
{
    public function testItMapsOnlyPublicActivitiesToTheApprovedFieldsInRepositoryOrder(): void
    {
        $repository = $this->createMock(ActivityCatalogRepository::class);
        $repository->expects(self::once())
            ->method('findAllPublic')
            ->willReturn([
                self::activity(7, 'Village Walk', 'village-walk', 'Explore nearby paths.', '0.00', true, true),
                self::activity(8, 'Hidden Walk', 'hidden-walk', 'Not public.', '500.00', true, false),
                self::activity(9, 'Sunrise Hike', 'sunrise-hike', 'Start the day outdoors.', '1200.00', true, true),
            ]);
        $repository->expects(self::never())->method('findAll');
        $service = new PublicActivityService($repository);

        self::assertSame([
            [
                'activity_id' => 7,
                'title' => 'Village Walk',
                'slug' => 'village-walk',
                'description' => 'Explore nearby paths.',
                'price' => '0.00',
            ],
            [
                'activity_id' => 9,
                'title' => 'Sunrise Hike',
                'slug' => 'sunrise-hike',
                'description' => 'Start the day outdoors.',
                'price' => '1200.00',
            ],
        ], $service->list());
    }

    public function testEmptyPublicCatalogueProducesAnEmptyList(): void
    {
        $repository = $this->createStub(ActivityCatalogRepository::class);
        $repository->method('findAllPublic')->willReturn([]);

        self::assertSame([], (new PublicActivityService($repository))->list());
    }

    public function testRepositoryFailureBecomesASafeActivityFailure(): void
    {
        $repository = $this->createStub(ActivityCatalogRepository::class);
        $repository->method('findAllPublic')->willThrowException(
            new RuntimeException('SQLSTATE private activity details'),
        );
        $service = new PublicActivityService($repository);

        try {
            $service->list();
            self::fail('Expected a safe public activity failure.');
        } catch (ActivityException $exception) {
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }
    }

    private static function activity(
        int $id,
        string $title,
        string $slug,
        string $description,
        string $price,
        bool $active,
        bool $published,
    ): Activity {
        $timestamp = new DateTimeImmutable('2026-07-30 12:00:00 UTC');

        return new Activity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $active,
            $published,
            $id,
            $timestamp,
            $timestamp,
        );
    }
}
