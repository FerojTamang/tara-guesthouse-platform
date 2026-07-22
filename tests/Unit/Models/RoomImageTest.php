<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\RoomImage;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RoomImageTest extends TestCase
{
    public function testItExposesImmutableStoredImageMetadata(): void
    {
        $createdAt = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $image = new RoomImage(
            7,
            4,
            'rooms/4/garden-deluxe.webp',
            'Garden-facing deluxe room',
            2,
            true,
            $createdAt,
        );

        self::assertTrue((new ReflectionClass($image))->isReadOnly());
        self::assertSame(7, $image->id());
        self::assertSame(4, $image->roomId());
        self::assertSame('rooms/4/garden-deluxe.webp', $image->filePath());
        self::assertSame('Garden-facing deluxe room', $image->altText());
        self::assertSame(2, $image->sortOrder());
        self::assertTrue($image->isPrimary());
        self::assertSame($createdAt, $image->createdAt());

        $property = new \ReflectionProperty($image, 'filePath');
        $this->expectException(Error::class);
        $property->setValue($image, 'changed.webp');
    }

    public function testOptionalAltTextAndNonPrimaryStateRemainExplicit(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-22 01:02:03 UTC');
        $image = new RoomImage(1, 1, 'rooms/1/detail.webp', null, 0, false, $timestamp);

        self::assertNull($image->altText());
        self::assertFalse($image->isPrimary());
    }

    #[DataProvider('invalidDataProvider')]
    public function testItRejectsInvalidPersistenceData(
        int $id,
        int $roomId,
        string $path,
        int $sortOrder,
        bool $nonUtc,
    ): void {
        $timestamp = $nonUtc
            ? new DateTimeImmutable('2026-07-22 01:02:03 Asia/Kathmandu')
            : new DateTimeImmutable('2026-07-22 01:02:03 UTC');

        $this->expectException(InvalidArgumentException::class);
        new RoomImage($id, $roomId, $path, null, $sortOrder, false, $timestamp);
    }

    /**
     * @return iterable<string, array{int, int, string, int, bool}>
     */
    public static function invalidDataProvider(): iterable
    {
        yield 'invalid identifier' => [0, 1, 'rooms/1/image.webp', 0, false];
        yield 'invalid room identifier' => [1, 0, 'rooms/1/image.webp', 0, false];
        yield 'empty path' => [1, 1, '', 0, false];
        yield 'rooted slash path' => [1, 1, '/rooms/1/image.webp', 0, false];
        yield 'rooted backslash path' => [1, 1, '\\rooms\\1\\image.webp', 0, false];
        yield 'backslash path' => [1, 1, 'rooms\\1\\image.webp', 0, false];
        yield 'double slash path' => [1, 1, 'rooms//1/image.webp', 0, false];
        yield 'parent traversal path' => [1, 1, 'rooms/../secrets.txt', 0, false];
        yield 'current traversal path' => [1, 1, 'rooms/./image.webp', 0, false];
        yield 'negative sort order' => [1, 1, 'rooms/1/image.webp', -1, false];
        yield 'non UTC timestamp' => [1, 1, 'rooms/1/image.webp', 0, true];
    }
}
