<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Activity;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class ActivityTest extends TestCase
{
    public function testItExposesImmutableActivityDataAndVisibility(): void
    {
        $createdAt = self::timestamp('2026-07-30 07:00:00');
        $updatedAt = self::timestamp('2026-07-30 08:00:00');
        $activity = new Activity(
            7,
            'Jungle Walk',
            'jungle-walk',
            'Explore the surrounding forest with a local guide.',
            '2500.00',
            true,
            true,
            10,
            $createdAt,
            $updatedAt,
        );

        self::assertTrue((new ReflectionClass($activity))->isReadOnly());
        self::assertSame(7, $activity->id());
        self::assertSame('Jungle Walk', $activity->title());
        self::assertSame('jungle-walk', $activity->slug());
        self::assertSame(
            'Explore the surrounding forest with a local guide.',
            $activity->description(),
        );
        self::assertSame('2500.00', $activity->price());
        self::assertTrue($activity->isActive());
        self::assertTrue($activity->isPublished());
        self::assertTrue($activity->isPubliclyVisible());
        self::assertSame(10, $activity->sortOrder());
        self::assertSame($createdAt, $activity->createdAt());
        self::assertSame($updatedAt, $activity->updatedAt());

        $property = new ReflectionProperty($activity, 'title');
        $this->expectException(Error::class);
        $property->setValue($activity, 'Changed');
    }

    public function testFreeAndNonPublicActivitiesAreRepresentedExactly(): void
    {
        $activity = self::activity([
            'price' => '0.00',
            'active' => false,
            'published' => true,
            'sortOrder' => 0,
        ]);

        self::assertSame('0.00', $activity->price());
        self::assertFalse($activity->isActive());
        self::assertTrue($activity->isPublished());
        self::assertFalse($activity->isPubliclyVisible());
        self::assertSame(0, $activity->sortOrder());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidData')]
    public function testItRejectsInvalidPersistenceData(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Activity data is invalid.');

        self::activity($override);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidData(): iterable
    {
        yield 'invalid identifier' => [['id' => 0]];
        yield 'blank title' => [['title' => '']];
        yield 'untrimmed title' => [['title' => ' Jungle Walk ']];
        yield 'overlength title' => [['title' => str_repeat('a', 201)]];
        yield 'blank slug' => [['slug' => '']];
        yield 'uppercase slug' => [['slug' => 'Jungle-Walk']];
        yield 'spaced slug' => [['slug' => 'jungle walk']];
        yield 'double separator slug' => [['slug' => 'jungle--walk']];
        yield 'overlength slug' => [['slug' => str_repeat('a', 201)]];
        yield 'blank description' => [['description' => '']];
        yield 'untrimmed description' => [['description' => ' Description ']];
        yield 'negative money' => [['price' => '-1.00']];
        yield 'floating format' => [['price' => '1.0']];
        yield 'too many fractional digits' => [['price' => '1.001']];
        yield 'currency string' => [['price' => 'NPR 1.00']];
        yield 'too many integer digits' => [['price' => '10000000000.00']];
        yield 'negative order' => [['sortOrder' => -1]];
        yield 'non UTC created timestamp' => [[
            'createdAt' => new DateTimeImmutable('2026-07-30 07:00:00 +05:45'),
        ]];
        yield 'non UTC updated timestamp' => [[
            'updatedAt' => new DateTimeImmutable('2026-07-30 07:00:00 +05:45'),
        ]];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function activity(array $override = []): Activity
    {
        $timestamp = self::timestamp('2026-07-30 07:00:00');

        return new Activity(...array_replace([
            'id' => 1,
            'title' => 'Jungle Walk',
            'slug' => 'jungle-walk',
            'description' => 'Explore the surrounding forest with a local guide.',
            'price' => '2500.00',
            'active' => true,
            'published' => true,
            'sortOrder' => 10,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ], $override));
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
