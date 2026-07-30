<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Exceptions\GalleryException;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Repositories\GalleryRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\GalleryFixture;

#[Group('database')]
final class GalleryRepositoryTest extends DatabaseTestCase
{
    private GalleryRepository $repository;

    /** @var array<string, int> */
    private array $categoryIds;

    /** @var array<string, int> */
    private array $itemIds;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR . 'database'
                . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->repository = new GalleryRepository($this->connection);
        $this->categoryIds = [
            'active' => GalleryFixture::insertCategory($this->connection, [
                'name' => 'Nature',
                'slug' => 'nature',
                'is_active' => 1,
            ]),
            'inactive' => GalleryFixture::insertCategory($this->connection, [
                'name' => 'Rooms',
                'slug' => 'rooms',
                'is_active' => 0,
            ]),
        ];
        $this->itemIds = [
            'public-late' => GalleryFixture::insertItem($this->connection, [
                'gallery_category_id' => $this->categoryIds['active'],
                'title' => 'Riverside Sunset',
                'file_path' => 'gallery/' . str_repeat('a', 64) . '.jpg',
                'alt_text' => 'Sunset beside the river',
                'sort_order' => 20,
            ]),
            'inactive-category' => GalleryFixture::insertItem($this->connection, [
                'gallery_category_id' => $this->categoryIds['inactive'],
                'title' => 'Guest Room',
                'file_path' => 'gallery/' . str_repeat('b', 64) . '.jpg',
                'alt_text' => 'Guest room interior',
                'sort_order' => 5,
            ]),
            'draft' => GalleryFixture::insertItem($this->connection, [
                'title' => 'Garden Draft',
                'file_path' => 'gallery/' . str_repeat('c', 64) . '.png',
                'alt_text' => 'Garden beside the guest house',
                'sort_order' => 1,
                'is_published' => 0,
            ]),
            'public-early' => GalleryFixture::insertItem($this->connection, [
                'title' => 'Courtyard',
                'caption' => null,
                'file_path' => 'gallery/' . str_repeat('d', 64) . '.webp',
                'alt_text' => 'Guest house courtyard',
                'sort_order' => 10,
            ]),
        ];
    }

    public function testItFindsMapsAndLocksCategoriesAndItems(): void
    {
        $category = $this->repository->findCategoryById($this->categoryIds['active']);
        $item = $this->repository->findItemById($this->itemIds['public-late']);

        self::assertInstanceOf(GalleryCategory::class, $category);
        self::assertSame('Nature', $category->name());
        self::assertSame('nature', $category->slug());
        self::assertTrue($category->isActive());
        self::assertSame($category->id(), $this->repository->findCategoryBySlug('nature')?->id());
        self::assertInstanceOf(GalleryItem::class, $item);
        self::assertSame($category->id(), $item->categoryId());
        self::assertSame('Riverside Sunset', $item->title());
        self::assertSame('gallery/' . str_repeat('a', 64) . '.jpg', $item->filePath());
        self::assertTrue($item->isPubliclyVisible());
        self::assertEquals(new DateTimeImmutable('2026-07-30 08:00:00 UTC'), $item->createdAt());

        self::assertTrue($this->connection->beginTransaction());

        try {
            self::assertSame($category->id(), $this->repository->lockCategoryById($category->id())?->id());
            self::assertSame($item->id(), $this->repository->lockItemById($item->id())?->id());
            self::assertNull($this->repository->lockCategoryById(999999));
            self::assertNull($this->repository->lockItemById(999999));
        } finally {
            self::assertTrue($this->connection->rollBack());
        }
    }

    public function testItListsCategoriesAndItemsInApprovedOrderAndFiltersPublicEligibility(): void
    {
        self::assertSame(
            ['Nature', 'Rooms'],
            array_map(
                static fn (GalleryCategory $category): string => $category->name(),
                $this->repository->findAllCategories(),
            ),
        );
        self::assertSame(
            ['Nature'],
            array_map(
                static fn (GalleryCategory $category): string => $category->name(),
                $this->repository->findAllActiveCategories(),
            ),
        );
        self::assertSame(
            ['Garden Draft', 'Guest Room', 'Courtyard', 'Riverside Sunset'],
            array_map(
                static fn (GalleryItem $item): string => $item->title(),
                $this->repository->findAllItems(),
            ),
        );
        self::assertSame(
            ['Courtyard', 'Riverside Sunset'],
            array_map(
                static fn (GalleryItem $item): string => $item->title(),
                $this->repository->findAllPublicItems(),
            ),
        );
    }

    public function testItCreatesAndUpdatesCategoriesAndItemsWithoutOverwritingVisibility(): void
    {
        $category = $this->repository->createCategory('Guest House', 'guest-house', false);
        self::assertFalse($category->isActive());

        $category = $this->repository->updateCategory($category->id(), 'Property', 'property');
        self::assertInstanceOf(GalleryCategory::class, $category);
        self::assertSame('Property', $category->name());
        self::assertFalse($category->isActive());

        $item = $this->repository->createItem(
            $category->id(),
            'Reception Area',
            null,
            'gallery/' . str_repeat('e', 64) . '.jpg',
            'Reception area inside Tara Guest House',
            30,
            false,
            true,
        );
        self::assertFalse($item->isActive());
        self::assertTrue($item->isPublished());

        $item = $this->repository->updateItem(
            $item->id(),
            null,
            'Welcome Area',
            'A welcoming common area.',
            'gallery/' . str_repeat('f', 64) . '.png',
            'Welcome area inside Tara Guest House',
            25,
        );
        self::assertInstanceOf(GalleryItem::class, $item);
        self::assertNull($item->categoryId());
        self::assertSame('Welcome Area', $item->title());
        self::assertSame(25, $item->sortOrder());
        self::assertFalse($item->isActive());
        self::assertTrue($item->isPublished());
    }

    public function testItChangesStateAndOrderIndependentlyAndRetainsRemovedItems(): void
    {
        $category = $this->repository->setCategoryActive($this->categoryIds['inactive'], true);
        self::assertInstanceOf(GalleryCategory::class, $category);
        self::assertTrue($category->isActive());

        $id = $this->itemIds['draft'];
        self::assertFalse($this->repository->setItemActive($id, false)?->isActive());
        self::assertTrue($this->repository->setItemPublished($id, true)?->isPublished());
        self::assertSame(99, $this->repository->setItemSortOrder($id, 99)?->sortOrder());
        $removed = $this->repository->deactivateAndUnpublishItem($id);

        self::assertInstanceOf(GalleryItem::class, $removed);
        self::assertFalse($removed->isActive());
        self::assertFalse($removed->isPublished());
        self::assertSame(1, (int) $this->connection->query(
            'SELECT COUNT(*) FROM gallery WHERE id = ' . $id,
        )->fetchColumn());
    }

    public function testItRejectsUnsafeInputsAndDuplicateCategoryIdentitySafely(): void
    {
        foreach ([
            fn () => $this->repository->createItem(
                null,
                'Unsafe path',
                null,
                '../gallery/image.jpg',
                'Unsafe path',
                0,
                true,
                true,
            ),
            fn () => $this->repository->setItemSortOrder($this->itemIds['draft'], -1),
            fn () => $this->repository->createItem(
                0,
                'Invalid category',
                null,
                'gallery/image.jpg',
                'Invalid category',
                0,
                true,
                true,
            ),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected gallery input validation to fail.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isValidationFailure());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }

        foreach ([
            ['Nature', 'another-slug', 'name'],
            ['Another Name', 'nature', 'slug'],
        ] as [$name, $slug, $field]) {
            try {
                $this->repository->createCategory($name, $slug, true);
                self::fail('Expected a duplicate gallery category conflict.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isConflict());
                self::assertStringContainsString($field, strtolower($exception->getMessage()));
                self::assertStringNotContainsString('uq_gallery', $exception->getMessage());
            }
        }
    }

    public function testRepositoryFailuresAreSanitized(): void
    {
        $this->connection->exec('DROP TABLE gallery');

        try {
            $this->repository->findAllItems();
            self::fail('Expected a gallery repository failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The gallery item listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }
}
