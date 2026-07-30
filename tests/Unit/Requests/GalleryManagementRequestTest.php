<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\GalleryException;
use App\Requests\GalleryCategoryRequest;
use App\Requests\GalleryItemMetadataRequest;
use App\Requests\GalleryReorderRequest;
use App\Requests\GalleryStateRequest;
use PHPUnit\Framework\TestCase;

final class GalleryManagementRequestTest extends TestCase
{
    public function testCategoryRequestNormalizesApprovedFields(): void
    {
        $request = GalleryCategoryRequest::fromInput(' Nature ', ' nature ', true);

        self::assertSame('Nature', $request->name());
        self::assertSame('nature', $request->slug());
        self::assertTrue($request->isActive());
    }

    public function testCategoryRequestRejectsInvalidSlugAndLooseState(): void
    {
        foreach ([
            ['Nature', 'Nature', true],
            ['Nature', 'nature--walks', true],
            ['Nature', 'nature', 1],
        ] as $input) {
            try {
                GalleryCategoryRequest::fromInput(...$input);
                self::fail('Expected gallery category validation to fail.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    public function testItemMetadataNormalizesNullableCategoryCaptionAndOrder(): void
    {
        $request = GalleryItemMetadataRequest::fromInput(
            '',
            ' Riverside Sunrise ',
            " Morning light.\r\n Calm water. ",
            ' Sunrise over the river ',
            '10',
        );

        self::assertNull($request->categoryId());
        self::assertSame('Riverside Sunrise', $request->title());
        self::assertSame("Morning light.\n Calm water.", $request->caption());
        self::assertSame('Sunrise over the river', $request->alternativeText());
        self::assertSame(10, $request->sortOrder());
    }

    public function testItemMetadataRejectsUnsafeOrUnapprovedValues(): void
    {
        foreach ([
            [0, 'Title', null, 'Alt text', 0],
            [null, "Two\nLines", null, 'Alt text', 0],
            [null, 'Title', "Unsafe\0caption", 'Alt text', 0],
            [null, 'Title', null, '', 0],
            [null, 'Title', null, 'Alt text', -1],
        ] as $input) {
            try {
                GalleryItemMetadataRequest::fromInput(...$input);
                self::fail('Expected gallery item metadata validation to fail.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    public function testReorderAcceptsExactDistinctItemsRegardlessOfObjectKeyOrder(): void
    {
        $request = GalleryReorderRequest::fromInput([
            ['sort_order' => 20, 'id' => 7],
            ['id' => '8', 'sort_order' => '10'],
        ]);

        self::assertSame([
            ['id' => 7, 'sort_order' => 20],
            ['id' => 8, 'sort_order' => 10],
        ], $request->items());
    }

    public function testReorderRejectsDuplicatesUnknownFieldsAndEmptyLists(): void
    {
        foreach ([
            [],
            [['id' => 7, 'sort_order' => 10], ['id' => 7, 'sort_order' => 20]],
            [['id' => 7, 'sort_order' => 10, 'title' => 'No']],
        ] as $items) {
            try {
                GalleryReorderRequest::fromInput($items);
                self::fail('Expected gallery reorder validation to fail.');
            } catch (GalleryException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    public function testStateRequestRequiresAnActualBoolean(): void
    {
        self::assertTrue(GalleryStateRequest::fromInput(true, 'State')->enabled());

        $this->expectException(GalleryException::class);
        GalleryStateRequest::fromInput('true', 'State');
    }
}
