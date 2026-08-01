<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GalleryException;
use App\Models\GalleryItem;
use App\Repositories\GalleryCatalogRepository;
use App\Services\PublicGalleryService;
use App\Storage\GalleryImageReader;
use App\Storage\RetrievedGalleryImage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicGalleryServiceTest extends TestCase
{
    public function testItReturnsOnlyApprovedPublicFieldsInRepositoryOrder(): void
    {
        $public = self::item(7, true, true, '<script>Courtyard</script>', "Quiet <b>space</b>.\nMorning light.");
        $draft = self::item(8, true, false, 'Draft', null);
        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findAllPublicItems')->willReturn([$public, $draft, new \stdClass()]);
        $service = new PublicGalleryService(
            $repository,
            $this->createStub(GalleryImageReader::class),
        );

        self::assertSame([[
            'gallery_id' => 7,
            'title' => '<script>Courtyard</script>',
            'caption' => "Quiet <b>space</b>.\nMorning light.",
            'alt_text' => 'Accessible gallery image 7',
            'image_url' => '/gallery?image=7',
        ]], $service->list());
    }

    public function testItReadsOnlyAnEligiblePublicItemByServerStoredPath(): void
    {
        $item = self::item(9);
        $image = new RetrievedGalleryImage('png-bytes', 'png', 'image/png', 1, 1);
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $repository->expects(self::once())->method('findPublicItemById')->with(9)->willReturn($item);
        $reader = $this->createMock(GalleryImageReader::class);
        $reader->expects(self::once())->method('read')->with($item->filePath())->willReturn($image);
        $service = new PublicGalleryService($repository, $reader);

        self::assertSame($image, $service->image(9));
    }

    public function testMissingIneligibleOrAbsentImageFailsAsNotFound(): void
    {
        foreach ([null, self::item(10, true, false)] as $item) {
            $repository = $this->createStub(GalleryCatalogRepository::class);
            $repository->method('findPublicItemById')->willReturn($item);
            $reader = $this->createStub(GalleryImageReader::class);
            $service = new PublicGalleryService($repository, $reader);

            try {
                $service->image(10);
                self::fail('Expected a public gallery not-found failure.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isNotFound());
            }
        }

        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findPublicItemById')->willReturn(self::item(11));
        $reader = $this->createStub(GalleryImageReader::class);
        $reader->method('read')->willReturn(null);

        try {
            (new PublicGalleryService($repository, $reader))->image(11);
            self::fail('Expected an absent image failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testUnexpectedRepositoryAndReaderFailuresAreSanitized(): void
    {
        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findAllPublicItems')->willThrowException(
            new RuntimeException('SQLSTATE private gallery detail'),
        );

        try {
            (new PublicGalleryService(
                $repository,
                $this->createStub(GalleryImageReader::class),
            ))->list();
            self::fail('Expected a safe gallery failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Gallery catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findPublicItemById')->willReturn(self::item(12));
        $reader = $this->createStub(GalleryImageReader::class);
        $reader->method('read')->willThrowException(new RuntimeException('private storage root'));

        try {
            (new PublicGalleryService($repository, $reader))->image(12);
            self::fail('Expected a safe image failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('storage root', $exception->getMessage());
        }
    }

    private static function item(
        int $id,
        bool $active = true,
        bool $published = true,
        string $title = 'Courtyard',
        ?string $caption = 'Morning light',
    ): GalleryItem {
        $timestamp = new DateTimeImmutable('2026-07-31 00:00:00 UTC');

        return new GalleryItem(
            $id,
            null,
            $title,
            $caption,
            'gallery/' . str_repeat(dechex($id % 16), 64) . '.png',
            'Accessible gallery image ' . $id,
            $id,
            $active,
            $published,
            $timestamp,
            $timestamp,
        );
    }
}
