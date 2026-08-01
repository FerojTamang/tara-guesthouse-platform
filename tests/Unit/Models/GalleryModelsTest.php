<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GalleryModelsTest extends TestCase
{
    public function testCategoryExposesValidatedImmutableData(): void
    {
        $created = self::timestamp('2026-07-30 08:00:00');
        $updated = self::timestamp('2026-07-30 09:00:00');
        $category = new GalleryCategory(7, 'Guest House', 'guest-house', true, $created, $updated);

        self::assertSame(7, $category->id());
        self::assertSame('Guest House', $category->name());
        self::assertSame('guest-house', $category->slug());
        self::assertTrue($category->isActive());
        self::assertSame($created, $category->createdAt());
        self::assertSame($updated, $category->updatedAt());
    }

    public function testItemExposesValidatedImmutableDataAndIndependentVisibility(): void
    {
        $created = self::timestamp('2026-07-30 08:00:00');
        $updated = self::timestamp('2026-07-30 09:00:00');
        $item = new GalleryItem(
            11,
            7,
            'Riverside Sunrise',
            'Morning light beside the river.',
            'gallery/0123456789abcdef.jpg',
            'Sunrise over the river',
            20,
            true,
            false,
            $created,
            $updated,
        );

        self::assertSame(11, $item->id());
        self::assertSame(7, $item->categoryId());
        self::assertSame('Riverside Sunrise', $item->title());
        self::assertSame('Morning light beside the river.', $item->caption());
        self::assertSame('gallery/0123456789abcdef.jpg', $item->filePath());
        self::assertSame('Sunrise over the river', $item->altText());
        self::assertSame(20, $item->sortOrder());
        self::assertTrue($item->isActive());
        self::assertFalse($item->isPublished());
        self::assertFalse($item->isPubliclyVisible());
        self::assertSame($created, $item->createdAt());
        self::assertSame($updated, $item->updatedAt());
    }

    #[DataProvider('invalidCategoryData')]
    public function testCategoryRejectsInvalidData(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gallery category data is invalid.');
        self::category($override);
    }

    public static function invalidCategoryData(): iterable
    {
        yield 'identifier' => [['id' => 0]];
        yield 'blank name' => [['name' => '']];
        yield 'untrimmed name' => [['name' => ' Nature ']];
        yield 'long name' => [['name' => str_repeat('a', 151)]];
        yield 'uppercase slug' => [['slug' => 'Nature']];
        yield 'double hyphen' => [['slug' => 'river--view']];
        yield 'long slug' => [['slug' => str_repeat('a', 201)]];
        yield 'created timezone' => [['createdAt' => new DateTimeImmutable('2026-07-30 +05:45')]];
    }

    #[DataProvider('invalidItemData')]
    public function testItemRejectsInvalidData(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gallery item data is invalid.');
        self::item($override);
    }

    public static function invalidItemData(): iterable
    {
        yield 'identifier' => [['id' => 0]];
        yield 'category identifier' => [['categoryId' => 0]];
        yield 'blank title' => [['title' => '']];
        yield 'untrimmed title' => [['title' => ' View ']];
        yield 'blank caption' => [['caption' => '']];
        yield 'untrimmed caption' => [['caption' => ' Caption ']];
        yield 'absolute path' => [['filePath' => '/gallery/image.jpg']];
        yield 'backslash path' => [['filePath' => 'gallery\\image.jpg']];
        yield 'traversal path' => [['filePath' => 'gallery/../image.jpg']];
        yield 'double slash' => [['filePath' => 'gallery//image.jpg']];
        yield 'space path' => [['filePath' => 'gallery/my image.jpg']];
        yield 'blank alt' => [['altText' => '']];
        yield 'untrimmed alt' => [['altText' => ' View ']];
        yield 'long alt' => [['altText' => str_repeat('a', 501)]];
        yield 'negative order' => [['sortOrder' => -1]];
        yield 'updated timezone' => [['updatedAt' => new DateTimeImmutable('2026-07-30 +05:45')]];
    }

    private static function category(array $override = []): GalleryCategory
    {
        $timestamp = self::timestamp('2026-07-30 08:00:00');

        return new GalleryCategory(...array_replace([
            'id' => 1,
            'name' => 'Nature',
            'slug' => 'nature',
            'active' => true,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ], $override));
    }

    private static function item(array $override = []): GalleryItem
    {
        $timestamp = self::timestamp('2026-07-30 08:00:00');

        return new GalleryItem(...array_replace([
            'id' => 1,
            'categoryId' => null,
            'title' => 'Riverside Sunrise',
            'caption' => null,
            'filePath' => 'gallery/0123456789abcdef.jpg',
            'altText' => 'Sunrise over the river',
            'sortOrder' => 10,
            'active' => true,
            'published' => true,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ], $override));
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
