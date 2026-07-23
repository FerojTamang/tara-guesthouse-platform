<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\RoomCatalogException;
use App\Requests\RoomUpsertRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoomUpsertRequestTest extends TestCase
{
    public function testItNormalizesACompleteRoomRequestWithoutFloatingPointMoney(): void
    {
        $request = RoomUpsertRequest::fromInput(
            '0007',
            "\u{2003}A-101\u{2003}",
            "\u{2003}Garden Deluxe\u{2003}",
            ' garden-deluxe ',
            " First line\r\nSecond line ",
            '7250.5',
            '02',
            1,
            ' maintenance ',
            '1',
            ['9', 3, '12'],
        );

        self::assertSame(7, $request->roomTypeId());
        self::assertSame('A-101', $request->roomNumber());
        self::assertSame('Garden Deluxe', $request->name());
        self::assertSame('garden-deluxe', $request->slug());
        self::assertSame("First line\nSecond line", $request->description());
        self::assertSame('7250.50', $request->pricePerNight());
        self::assertSame(2, $request->capacityAdults());
        self::assertSame(1, $request->capacityChildren());
        self::assertSame('maintenance', $request->operationalStatus());
        self::assertTrue($request->isPublished());
        self::assertSame([3, 9, 12], $request->amenityIds());
    }

    public function testItPreservesExplicitInactiveUnpublishedAndNoAmenityState(): void
    {
        $request = $this->request(
            description: '   ',
            operationalStatus: 'inactive',
            published: 0,
            amenityIds: null,
        );

        self::assertNull($request->description());
        self::assertSame('inactive', $request->operationalStatus());
        self::assertFalse($request->isPublished());
        self::assertSame([], $request->amenityIds());
        self::assertSame('1.00', $this->request(pricePerNight: 1)->pricePerNight());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidScalarValues(): iterable
    {
        yield 'zero room type' => ['Room type', '0'];
        yield 'negative room type' => ['Room type', '-1'];
        yield 'overflow room type' => ['Room type', str_repeat('9', strlen((string) PHP_INT_MAX) + 1)];
        yield 'empty room number' => ['Room number', ''];
        yield 'controlled room number' => ['Room number', "A\n101"];
        yield 'long room number' => ['Room number', str_repeat('a', 31)];
        yield 'empty name' => ['Name', ''];
        yield 'controlled name' => ['Name', "Garden\nDeluxe"];
        yield 'long name' => ['Name', str_repeat('a', 101)];
        yield 'uppercase slug' => ['Slug', 'Garden-Deluxe'];
        yield 'slug spaces' => ['Slug', 'garden deluxe'];
        yield 'long slug' => ['Slug', str_repeat('a', 121)];
        yield 'zero price' => ['Price per night', '0'];
        yield 'negative price' => ['Price per night', '-1.00'];
        yield 'too many decimals' => ['Price per night', '1.001'];
        yield 'floating price' => ['Price per night', 1.5];
        yield 'zero adults' => ['Adult capacity', '0'];
        yield 'negative children' => ['Child capacity', '-1'];
        yield 'unknown status' => ['Operational status', 'available'];
        yield 'uppercase status' => ['Operational status', 'Active'];
        yield 'missing published state' => ['Published state', null];
        yield 'unsupported published state' => ['Published state', 'published'];
    }

    #[DataProvider('invalidScalarValues')]
    public function testItRejectsInvalidScalarInput(string $field, mixed $value): void
    {
        try {
            match ($field) {
                'Room type' => $this->request(roomTypeId: $value),
                'Room number' => $this->request(roomNumber: $value),
                'Name' => $this->request(name: $value),
                'Slug' => $this->request(slug: $value),
                'Price per night' => $this->request(pricePerNight: $value),
                'Adult capacity' => $this->request(capacityAdults: $value),
                'Child capacity' => $this->request(capacityChildren: $value),
                'Operational status' => $this->request(operationalStatus: $value),
                'Published state' => $this->request(published: $value),
            };
            self::fail('Invalid room input was accepted.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    public function testItRejectsUnsafeOrOversizedDescriptions(): void
    {
        foreach (["Safe\0unsafe", "Visible\u{200B}hidden", str_repeat('a', 5001)] as $description) {
            try {
                $this->request(description: $description);
                self::fail('Invalid room description was accepted.');
            } catch (RoomCatalogException $exception) {
                self::assertTrue($exception->isValidationFailure());
                self::assertStringContainsString('Description', $exception->getMessage());
            }
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidAmenityValues(): iterable
    {
        yield 'scalar' => ['1'];
        yield 'zero identifier' => [['0']];
        yield 'negative identifier' => [['-1']];
        yield 'nested input' => [[['1']]];
        yield 'duplicate identifiers' => [['2', 2]];
        yield 'overflow identifier' => [[str_repeat('9', strlen((string) PHP_INT_MAX) + 1)]];
    }

    #[DataProvider('invalidAmenityValues')]
    public function testItRejectsInvalidAmenityAssignments(mixed $amenityIds): void
    {
        try {
            $this->request(amenityIds: $amenityIds);
            self::fail('Invalid amenity assignments were accepted.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringContainsString('Amenit', $exception->getMessage());
        }
    }

    private function request(
        mixed $roomTypeId = '1',
        mixed $roomNumber = '101',
        mixed $name = 'Garden Deluxe',
        mixed $slug = 'garden-deluxe',
        mixed $description = 'A valid physical room.',
        mixed $pricePerNight = '7250.00',
        mixed $capacityAdults = '2',
        mixed $capacityChildren = '1',
        mixed $operationalStatus = 'active',
        mixed $published = '1',
        mixed $amenityIds = ['2', '5'],
    ): RoomUpsertRequest {
        return RoomUpsertRequest::fromInput(
            $roomTypeId,
            $roomNumber,
            $name,
            $slug,
            $description,
            $pricePerNight,
            $capacityAdults,
            $capacityChildren,
            $operationalStatus,
            $published,
            $amenityIds,
        );
    }
}
