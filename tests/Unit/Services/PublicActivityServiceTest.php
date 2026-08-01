<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Repositories\ActivityCatalogRepository;
use App\Services\PublicActivityService;
use App\Storage\GalleryImageReader;
use App\Storage\RetrievedGalleryImage;
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
                'summary' => 'Explore nearby paths.',
                'description' => 'Explore nearby paths.',
                'image_url' => null,
                'price' => '0.00',
                'price_unit' => null,
                'duration' => null,
                'location' => null,
            ],
            [
                'activity_id' => 9,
                'title' => 'Sunrise Hike',
                'slug' => 'sunrise-hike',
                'summary' => 'Start the day outdoors.',
                'description' => 'Start the day outdoors.',
                'image_url' => null,
                'price' => '1200.00',
                'price_unit' => null,
                'duration' => null,
                'location' => null,
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

    public function testHomepageUsesCuratedProjectionAndDoesNotFabricateANullPrice(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-30 12:00:00 UTC');
        $activity = new Activity(
            7,
            'Village Walk',
            'village-walk',
            'Full description.',
            null,
            true,
            true,
            10,
            $timestamp,
            $timestamp,
            'Short card summary.',
            'gallery/' . str_repeat('a', 64) . '.jpg',
            null,
            '2 hours',
            'Dhulikhel',
            true,
            1,
        );
        $repository = $this->createMock(ActivityCatalogRepository::class);
        $repository->expects(self::once())->method('findHomepageActivities')->with(4)->willReturn([$activity]);

        self::assertSame([[
            'activity_id' => 7,
            'title' => 'Village Walk',
            'slug' => 'village-walk',
            'summary' => 'Short card summary.',
            'description' => 'Full description.',
            'image_url' => '/activities?image=7',
            'price' => null,
            'price_unit' => null,
            'duration' => '2 hours',
            'location' => 'Dhulikhel',
        ]], (new PublicActivityService($repository))->homepage());
    }

    public function testImageReadUsesOnlyAnEligiblePersistedPrivatePath(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-30 12:00:00 UTC');
        $path = 'gallery/' . str_repeat('a', 64) . '.png';
        $activity = new Activity(
            7, 'Village Walk', 'village-walk', 'Description.', null, true, true, 1,
            $timestamp, $timestamp, 'Summary.', $path,
        );
        $image = new RetrievedGalleryImage('safe image bytes', 'png', 'image/png', 1, 1);
        $repository = $this->createMock(ActivityCatalogRepository::class);
        $repository->expects(self::once())->method('findPublicById')->with(7)->willReturn($activity);
        $reader = $this->createMock(GalleryImageReader::class);
        $reader->expects(self::once())->method('read')->with($path)->willReturn($image);

        self::assertSame($image, (new PublicActivityService($repository, $reader))->image(7));
    }

    public function testIneligibleOrMissingImageRecordIsRejectedBeforeStorageRead(): void
    {
        $repository = $this->createMock(ActivityCatalogRepository::class);
        $repository->expects(self::once())->method('findPublicById')->with(7)->willReturn(null);
        $reader = $this->createMock(GalleryImageReader::class);
        $reader->expects(self::never())->method('read');

        try {
            (new PublicActivityService($repository, $reader))->image(7);
            self::fail('Expected an ineligible activity image to be hidden.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The requested activity was not found.', $exception->getMessage());
        }
    }

    public function testMissingOrCorruptStoredImageIsAControlledNotFound(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-30 12:00:00 UTC');
        $path = 'gallery/' . str_repeat('b', 64) . '.jpg';
        $activity = new Activity(
            8, 'Sunrise Hike', 'sunrise-hike', 'Description.', null, true, true, 2,
            $timestamp, $timestamp, 'Summary.', $path,
        );
        $repository = $this->createStub(ActivityCatalogRepository::class);
        $repository->method('findPublicById')->willReturn($activity);
        $reader = $this->createStub(GalleryImageReader::class);
        $reader->method('read')->willReturn(null);

        try {
            (new PublicActivityService($repository, $reader))->image(8);
            self::fail('Expected a missing stored image to be hidden.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame('The requested activity was not found.', $exception->getMessage());
        }
    }

    private static function activity(
        int $id,
        string $title,
        string $slug,
        string $description,
        ?string $price,
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
