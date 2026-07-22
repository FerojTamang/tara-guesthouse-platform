<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\RoomCatalogException;
use App\Requests\RoomTypeUpsertRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoomTypeUpsertRequestTest extends TestCase
{
    public function testItNormalizesValidRoomTypeInputWithoutUsingFloatingPointMoney(): void
    {
        $request = RoomTypeUpsertRequest::fromInput(
            "\u{2003}Deluxe Suite\u{2003}",
            ' deluxe-suite ',
            " First line\r\nSecond line ",
            '6500.5',
            '02',
            1,
            '1',
        );

        self::assertSame('Deluxe Suite', $request->name());
        self::assertSame('deluxe-suite', $request->slug());
        self::assertSame("First line\nSecond line", $request->description());
        self::assertSame('6500.50', $request->basePrice());
        self::assertSame(2, $request->capacityAdults());
        self::assertSame(1, $request->capacityChildren());
        self::assertTrue($request->isActive());
    }

    public function testEmptyDescriptionBecomesNullAndExplicitInactiveStateIsPreserved(): void
    {
        $request = $this->request(description: '   ', active: '0');

        self::assertNull($request->description());
        self::assertFalse($request->isActive());
        self::assertSame('1.00', $this->request(basePrice: 1)->basePrice());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'empty name' => ['Name', ''];
        yield 'controlled name' => ['Name', "Deluxe\nSuite"];
        yield 'long name' => ['Name', str_repeat('a', 101)];
        yield 'uppercase slug' => ['Slug', 'Deluxe-Suite'];
        yield 'slug spaces' => ['Slug', 'deluxe suite'];
        yield 'long slug' => ['Slug', str_repeat('a', 121)];
        yield 'zero price' => ['Base price', '0'];
        yield 'negative price' => ['Base price', '-1.00'];
        yield 'too many decimals' => ['Base price', '1.001'];
        yield 'floating input' => ['Base price', 1.5];
        yield 'zero adults' => ['Adult capacity', '0'];
        yield 'negative adults' => ['Adult capacity', '-1'];
        yield 'negative children' => ['Child capacity', '-1'];
        yield 'integer overflow' => ['Adult capacity', '2147483648'];
        yield 'implicit active state' => ['Active state', null];
        yield 'unsupported active state' => ['Active state', 'yes'];
    }

    #[DataProvider('invalidValues')]
    public function testItRejectsInvalidRoomTypeInput(string $field, mixed $value): void
    {
        try {
            match ($field) {
                'Name' => $this->request(name: $value),
                'Slug' => $this->request(slug: $value),
                'Base price' => $this->request(basePrice: $value),
                'Adult capacity' => $this->request(capacityAdults: $value),
                'Child capacity' => $this->request(capacityChildren: $value),
                'Active state' => $this->request(active: $value),
            };
            self::fail('Invalid room-type input was accepted.');
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
                self::fail('Invalid room-type description was accepted.');
            } catch (RoomCatalogException $exception) {
                self::assertTrue($exception->isValidationFailure());
                self::assertStringContainsString('Description', $exception->getMessage());
            }
        }
    }

    private function request(
        mixed $name = 'Deluxe Suite',
        mixed $slug = 'deluxe-suite',
        mixed $description = 'A valid room type.',
        mixed $basePrice = '6500.00',
        mixed $capacityAdults = '2',
        mixed $capacityChildren = '1',
        mixed $active = '1',
    ): RoomTypeUpsertRequest {
        return RoomTypeUpsertRequest::fromInput(
            $name,
            $slug,
            $description,
            $basePrice,
            $capacityAdults,
            $capacityChildren,
            $active,
        );
    }
}
