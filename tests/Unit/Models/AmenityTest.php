<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Amenity;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AmenityTest extends TestCase
{
    public function testItExposesImmutableStoredAmenityData(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $updatedAt = new DateTimeImmutable('2026-07-22 02:03:04 UTC');
        $amenity = new Amenity(3, 'Wi-Fi', 'wifi', true, $createdAt, $updatedAt);

        self::assertTrue((new ReflectionClass($amenity))->isReadOnly());
        self::assertSame(3, $amenity->id());
        self::assertSame('Wi-Fi', $amenity->name());
        self::assertSame('wifi', $amenity->icon());
        self::assertTrue($amenity->isActive());
        self::assertSame($createdAt, $amenity->createdAt());
        self::assertSame($updatedAt, $amenity->updatedAt());

        $property = new \ReflectionProperty($amenity, 'name');
        $this->expectException(Error::class);
        $property->setValue($amenity, 'Changed');
    }

    public function testInactiveAmenityCanHaveNoIcon(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $amenity = new Amenity(1, 'Archived fixture', null, false, $timestamp, $timestamp);

        self::assertNull($amenity->icon());
        self::assertFalse($amenity->isActive());
    }

    #[DataProvider('invalidDataProvider')]
    public function testItRejectsInvalidPersistenceData(int $id, string $name, bool $nonUtc): void
    {
        $utc = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $timestamp = $nonUtc ? new DateTimeImmutable('2026-07-22 01:02:03 Asia/Kathmandu') : $utc;

        $this->expectException(InvalidArgumentException::class);
        new Amenity($id, $name, null, true, $timestamp, $utc);
    }

    /**
     * @return iterable<string, array{int, string, bool}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'invalid identifier' => [0, 'Wi-Fi', false];
        yield 'empty name' => [1, '', false];
        yield 'non UTC timestamp' => [1, 'Wi-Fi', true];
    }
}
