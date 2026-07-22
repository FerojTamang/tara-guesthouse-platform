<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\RoomType;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RoomTypeTest extends TestCase
{
    public function testItExposesImmutableStoredCatalogueDataAndExactMoney(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $updatedAt = new DateTimeImmutable('2026-07-22 02:03:04 UTC');
        $roomType = new RoomType(
            4,
            'Deluxe Double',
            'deluxe-double',
            'A public description.',
            '4500.00',
            2,
            1,
            true,
            $createdAt,
            $updatedAt,
        );

        self::assertTrue((new ReflectionClass($roomType))->isReadOnly());
        self::assertSame(4, $roomType->id());
        self::assertSame('Deluxe Double', $roomType->name());
        self::assertSame('deluxe-double', $roomType->slug());
        self::assertSame('A public description.', $roomType->description());
        self::assertSame('4500.00', $roomType->basePrice());
        self::assertIsString($roomType->basePrice());
        self::assertSame(2, $roomType->capacityAdults());
        self::assertSame(1, $roomType->capacityChildren());
        self::assertTrue($roomType->isActive());
        self::assertSame($createdAt, $roomType->createdAt());
        self::assertSame($updatedAt, $roomType->updatedAt());

        $property = new \ReflectionProperty($roomType, 'name');
        $this->expectException(Error::class);
        $property->setValue($roomType, 'Changed');
    }

    public function testInactiveAndNullableDescriptionStateRemainExplicit(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $roomType = new RoomType(1, 'Standard', 'standard', null, '2500.00', 1, 0, false, $timestamp, $timestamp);

        self::assertNull($roomType->description());
        self::assertFalse($roomType->isActive());
    }

    #[DataProvider('invalidDataProvider')]
    public function testItRejectsInvalidPersistenceData(
        int $id,
        string $name,
        string $slug,
        string $price,
        int $adults,
        int $children,
        bool $nonUtc,
    ): void {
        $utc = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $timestamp = $nonUtc ? new DateTimeImmutable('2026-07-22 01:02:03 Asia/Kathmandu') : $utc;

        $this->expectException(InvalidArgumentException::class);
        new RoomType($id, $name, $slug, null, $price, $adults, $children, true, $timestamp, $utc);
    }

    /**
     * @return iterable<string, array{int, string, string, string, int, int, bool}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'invalid identifier' => [0, 'Standard', 'standard', '2500.00', 1, 0, false];
        yield 'empty name' => [1, '', 'standard', '2500.00', 1, 0, false];
        yield 'empty slug' => [1, 'Standard', '', '2500.00', 1, 0, false];
        yield 'zero price' => [1, 'Standard', 'standard', '0.00', 1, 0, false];
        yield 'floating-style price' => [1, 'Standard', 'standard', '2500.0', 1, 0, false];
        yield 'negative price' => [1, 'Standard', 'standard', '-1.00', 1, 0, false];
        yield 'zero adult capacity' => [1, 'Standard', 'standard', '2500.00', 0, 0, false];
        yield 'negative child capacity' => [1, 'Standard', 'standard', '2500.00', 1, -1, false];
        yield 'non UTC timestamp' => [1, 'Standard', 'standard', '2500.00', 1, 0, true];
    }
}
