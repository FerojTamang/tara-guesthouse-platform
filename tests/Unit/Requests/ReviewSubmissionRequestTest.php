<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\ReviewException;
use App\Requests\ReviewSubmissionRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewSubmissionRequestTest extends TestCase
{
    public function testItNormalizesApprovedReviewFields(): void
    {
        $request = ReviewSubmissionRequest::fromInput(
            '5',
            '  A warm stay  ',
            "  Clean room.\r\nHelpful team.  ",
        );

        self::assertSame(5, $request->rating());
        self::assertSame('A warm stay', $request->title());
        self::assertSame("Clean room.\nHelpful team.", $request->comment());
        self::assertNull(ReviewSubmissionRequest::fromInput(1, '', 'Good stay.')->title());
        self::assertNull(ReviewSubmissionRequest::fromInput(1, '   ', 'Good stay.')->title());
    }

    /** @return iterable<string, array{mixed, mixed, mixed}> */
    public static function invalidInput(): iterable
    {
        yield 'rating zero' => [0, null, 'Good stay.'];
        yield 'rating six' => [6, null, 'Good stay.'];
        yield 'rating float' => [4.5, null, 'Good stay.'];
        yield 'rating padded' => [' 5 ', null, 'Good stay.'];
        yield 'title newline' => [5, "Bad\nTitle", 'Good stay.'];
        yield 'title too long' => [5, str_repeat('a', 151), 'Good stay.'];
        yield 'empty comment' => [5, null, '   '];
        yield 'comment control' => [5, null, "Good\0stay"];
        yield 'comment too large' => [5, null, str_repeat('a', 65536)];
        yield 'comment not string' => [5, null, ['not', 'text']];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputFailsClosed(mixed $rating, mixed $title, mixed $comment): void
    {
        $this->expectException(ReviewException::class);
        ReviewSubmissionRequest::fromInput($rating, $title, $comment);
    }
}
