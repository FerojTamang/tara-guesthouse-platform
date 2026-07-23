<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\RoomCatalogException;
use App\Requests\AmenityUpsertRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmenityUpsertRequestTest extends TestCase
{
    public function testItNormalizesValidAmenityInput(): void
    {
        $request = AmenityUpsertRequest::fromInput(
            "\u{2003}Air conditioning\u{2003}",
            ' air-conditioning ',
            1,
        );

        self::assertSame('Air conditioning', $request->name());
        self::assertSame('air-conditioning', $request->icon());
        self::assertTrue($request->isActive());
    }

    public function testEmptyIconBecomesNullAndInactiveStateIsExplicit(): void
    {
        $request = AmenityUpsertRequest::fromInput('Airport shuttle', '   ', '0');

        self::assertNull($request->icon());
        self::assertFalse($request->isActive());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'empty name' => ['Name', ''];
        yield 'controlled name' => ['Name', "Wi\nFi"];
        yield 'long name' => ['Name', str_repeat('a', 101)];
        yield 'icon begins with digit' => ['Icon identifier', '1-wifi'];
        yield 'icon uppercase' => ['Icon identifier', 'WiFi'];
        yield 'icon whitespace' => ['Icon identifier', 'air conditioning'];
        yield 'long icon' => ['Icon identifier', 'a' . str_repeat('b', 100)];
        yield 'missing active state' => ['Active state', null];
        yield 'unsupported active state' => ['Active state', 'active'];
    }

    #[DataProvider('invalidValues')]
    public function testItRejectsInvalidAmenityInput(string $field, mixed $value): void
    {
        try {
            match ($field) {
                'Name' => AmenityUpsertRequest::fromInput($value, 'wifi', '1'),
                'Icon identifier' => AmenityUpsertRequest::fromInput('Wi-Fi', $value, '1'),
                'Active state' => AmenityUpsertRequest::fromInput('Wi-Fi', 'wifi', $value),
            };
            self::fail('Invalid amenity input was accepted.');
        } catch (RoomCatalogException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }
}
