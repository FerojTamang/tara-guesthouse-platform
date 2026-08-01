<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GalleryException;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Repositories\GalleryCatalogRepository;
use App\Services\GalleryCatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GalleryCatalogServiceTest extends TestCase
{
    public function testItListsAdministratorAndEligiblePublicCatalogues(): void
    {
        $activeCategory = self::category(1, true);
        $inactiveCategory = self::category(2, false);
        $publicItem = self::item(1, true, true);
        $draftItem = self::item(2, true, false);
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $repository->expects(self::once())->method('findAllCategories')
            ->willReturn([$activeCategory, $inactiveCategory]);
        $repository->expects(self::once())->method('findAllActiveCategories')
            ->willReturn([$activeCategory, $inactiveCategory]);
        $repository->expects(self::once())->method('findAllItems')
            ->willReturn([$publicItem, $draftItem]);
        $repository->expects(self::once())->method('findAllPublicItems')
            ->willReturn([$publicItem, $draftItem]);
        $service = new GalleryCatalogService($repository);

        self::assertSame([$activeCategory, $inactiveCategory], $service->listCategories());
        self::assertSame([$activeCategory], $service->listActiveCategories());
        self::assertSame([$publicItem, $draftItem], $service->listItems());
        self::assertSame([$publicItem], $service->listPublicItems());
    }

    public function testItLoadsCategoriesAndItemsAndRejectsMissingRecords(): void
    {
        $category = self::category();
        $item = self::item();
        $repository = $this->createMock(GalleryCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('findCategoryById')
            ->willReturnMap([[7, $category], [99, null]]);
        $repository->expects(self::exactly(2))->method('findCategoryBySlug')
            ->willReturnMap([['nature-7', $category], ['missing', null]]);
        $repository->expects(self::exactly(2))->method('findItemById')
            ->willReturnMap([[8, $item], [99, null]]);
        $service = new GalleryCatalogService($repository);

        self::assertSame($category, $service->getCategory(7));
        self::assertSame($category, $service->getCategoryBySlug('nature-7'));
        self::assertSame($item, $service->getItem(8));

        foreach ([
            static fn () => $service->getCategory(99),
            static fn () => $service->getCategoryBySlug('missing'),
            static fn () => $service->getItem(99),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected a missing gallery record failure.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isNotFound());
            }
        }
    }

    public function testExpectedFailuresPassThroughAndUnexpectedFailuresAreSanitized(): void
    {
        $conflict = GalleryException::duplicateCategorySlug();
        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findAllCategories')->willThrowException($conflict);
        $service = new GalleryCatalogService($repository);

        try {
            $service->listCategories();
            self::fail('Expected the gallery conflict.');
        } catch (GalleryException $exception) {
            self::assertSame($conflict, $exception);
        }

        $repository = $this->createStub(GalleryCatalogRepository::class);
        $repository->method('findAllItems')->willThrowException(
            new RuntimeException('SQLSTATE private gallery path'),
        );

        try {
            (new GalleryCatalogService($repository))->listItems();
            self::fail('Expected a safe gallery failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Gallery catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('path', $exception->getMessage());
        }
    }

    private static function category(int $id = 7, bool $active = true): GalleryCategory
    {
        $timestamp = new DateTimeImmutable('2026-07-30 08:00:00 UTC');

        return new GalleryCategory($id, 'Nature ' . $id, 'nature-' . $id, $active, $timestamp, $timestamp);
    }

    private static function item(int $id = 8, bool $active = true, bool $published = true): GalleryItem
    {
        $timestamp = new DateTimeImmutable('2026-07-30 08:00:00 UTC');

        return new GalleryItem(
            $id,
            null,
            'Riverside Sunrise ' . $id,
            null,
            'gallery/' . str_pad((string) $id, 16, '0', STR_PAD_LEFT) . '.jpg',
            'Sunrise over the river ' . $id,
            10,
            $active,
            $published,
            $timestamp,
            $timestamp,
        );
    }
}
