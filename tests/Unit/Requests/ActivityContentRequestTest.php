<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\ActivityException;
use App\Requests\ActivityContentRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivityContentRequestTest extends TestCase
{
    public function testItNormalizesApprovedContentFields(): void
    {
        $request = ActivityContentRequest::fromInput(
            ' Jungle Walk ',
            ' jungle-walk ',
            ' A guided forest walk. ',
            " Explore the forest.\r\nBring water. ",
            ' 2500.00 ',
            ' per person ',
            ' 3 hours ',
            ' Dhulikhel ',
            '10',
            '1',
            '2',
        );

        self::assertSame('Jungle Walk', $request->title());
        self::assertSame('jungle-walk', $request->slug());
        self::assertSame('A guided forest walk.', $request->summary());
        self::assertSame("Explore the forest.\nBring water.", $request->description());
        self::assertSame('2500.00', $request->price());
        self::assertSame('per person', $request->priceUnit());
        self::assertSame('3 hours', $request->durationLabel());
        self::assertSame('Dhulikhel', $request->location());
        self::assertSame(10, $request->sortOrder());
        self::assertTrue($request->isHomepageFeatured());
        self::assertSame(2, $request->homepageSortOrder());
    }

    public function testItAcceptsFreeActivitiesAndIntegerSortOrder(): void
    {
        $request = ActivityContentRequest::fromInput(
            'Bird Watching',
            'bird-watching',
            'Observe local bird life.',
            'Observe local bird life.',
            null,
            null,
            '',
            '',
            0,
            false,
            0,
        );

        self::assertNull($request->price());
        self::assertNull($request->priceUnit());
        self::assertSame(0, $request->sortOrder());
    }

    #[DataProvider('invalidInputProvider')]
    public function testItRejectsInvalidContent(
        mixed $title,
        mixed $slug,
        mixed $summary,
        mixed $description,
        mixed $price,
        mixed $priceUnit,
        mixed $duration,
        mixed $location,
        mixed $sortOrder,
        mixed $homepageFeatured,
        mixed $homepageSortOrder,
    ): void {
        try {
            ActivityContentRequest::fromInput(
                $title,
                $slug,
                $summary,
                $description,
                $price,
                $priceUnit,
                $duration,
                $location,
                $sortOrder,
                $homepageFeatured,
                $homepageSortOrder,
            );
            self::fail('Expected activity content validation to fail.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed}> */
    public static function invalidInputProvider(): iterable
    {
        $valid = [
            'Jungle Walk',
            'jungle-walk',
            'Explore the forest.',
            'Explore the forest.',
            '2500.00',
            'per person',
            '3 hours',
            'Dhulikhel',
            10,
            true,
            1,
        ];

        yield 'empty title' => ['', ...array_slice($valid, 1)];
        yield 'multiline title' => ["Jungle\nWalk", ...array_slice($valid, 1)];
        yield 'uppercase slug' => [$valid[0], 'Jungle-Walk', ...array_slice($valid, 2)];
        yield 'double hyphen slug' => [$valid[0], 'jungle--walk', ...array_slice($valid, 2)];
        yield 'empty summary' => [$valid[0], $valid[1], '', ...array_slice($valid, 3)];
        yield 'empty description' => [...array_slice($valid, 0, 3), ' ', ...array_slice($valid, 4)];
        yield 'description control' => [...array_slice($valid, 0, 3), "Unsafe\0text", ...array_slice($valid, 4)];
        yield 'negative price' => [...array_slice($valid, 0, 4), '-1.00', ...array_slice($valid, 5)];
        yield 'one decimal price' => [...array_slice($valid, 0, 4), '1.0', ...array_slice($valid, 5)];
        yield 'missing price unit' => [...array_slice($valid, 0, 5), null, ...array_slice($valid, 6)];
        yield 'negative order' => [...array_slice($valid, 0, 8), -1, ...array_slice($valid, 9)];
        yield 'invalid homepage checkbox' => [...array_slice($valid, 0, 9), 'yes', $valid[10]];
        yield 'excess homepage order' => [...array_slice($valid, 0, 10), '4294967296'];
        yield 'array field' => [[], ...array_slice($valid, 1)];
    }
}
