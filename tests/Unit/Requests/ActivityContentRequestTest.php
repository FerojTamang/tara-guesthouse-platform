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
            " Explore the forest.\r\nBring water. ",
            ' 2500.00 ',
            '10',
        );

        self::assertSame('Jungle Walk', $request->title());
        self::assertSame('jungle-walk', $request->slug());
        self::assertSame("Explore the forest.\nBring water.", $request->description());
        self::assertSame('2500.00', $request->price());
        self::assertSame(10, $request->sortOrder());
    }

    public function testItAcceptsFreeActivitiesAndIntegerSortOrder(): void
    {
        $request = ActivityContentRequest::fromInput(
            'Bird Watching',
            'bird-watching',
            'Observe local bird life.',
            '0.00',
            0,
        );

        self::assertSame('0.00', $request->price());
        self::assertSame(0, $request->sortOrder());
    }

    #[DataProvider('invalidInputProvider')]
    public function testItRejectsInvalidContent(
        mixed $title,
        mixed $slug,
        mixed $description,
        mixed $price,
        mixed $sortOrder,
    ): void {
        try {
            ActivityContentRequest::fromInput(
                $title,
                $slug,
                $description,
                $price,
                $sortOrder,
            );
            self::fail('Expected activity content validation to fail.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed, mixed, mixed, mixed, mixed}> */
    public static function invalidInputProvider(): iterable
    {
        $valid = [
            'Jungle Walk',
            'jungle-walk',
            'Explore the forest.',
            '2500.00',
            10,
        ];

        yield 'empty title' => ['', ...array_slice($valid, 1)];
        yield 'multiline title' => ["Jungle\nWalk", ...array_slice($valid, 1)];
        yield 'uppercase slug' => [$valid[0], 'Jungle-Walk', ...array_slice($valid, 2)];
        yield 'double hyphen slug' => [$valid[0], 'jungle--walk', ...array_slice($valid, 2)];
        yield 'empty description' => [$valid[0], $valid[1], ' ', $valid[3], $valid[4]];
        yield 'description control' => [$valid[0], $valid[1], "Unsafe\0text", $valid[3], $valid[4]];
        yield 'negative price' => [$valid[0], $valid[1], $valid[2], '-1.00', $valid[4]];
        yield 'one decimal price' => [$valid[0], $valid[1], $valid[2], '1.0', $valid[4]];
        yield 'three decimal price' => [$valid[0], $valid[1], $valid[2], '1.001', $valid[4]];
        yield 'currency-prefixed price' => [$valid[0], $valid[1], $valid[2], 'NPR 1.00', $valid[4]];
        yield 'negative order' => [$valid[0], $valid[1], $valid[2], $valid[3], -1];
        yield 'fractional order' => [$valid[0], $valid[1], $valid[2], $valid[3], '1.5'];
        yield 'excess order' => [$valid[0], $valid[1], $valid[2], $valid[3], '4294967296'];
        yield 'array field' => [[], $valid[1], $valid[2], $valid[3], $valid[4]];
    }
}
