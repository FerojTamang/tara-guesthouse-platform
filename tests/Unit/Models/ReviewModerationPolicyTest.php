<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ReviewModerationPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewModerationPolicyTest extends TestCase
{
    public function testApprovedPolicyExposesExactRules(): void
    {
        $policy = new ReviewModerationPolicy(1000, true);

        self::assertSame(1000, $policy->maximumNoteCharacters());
        self::assertTrue($policy->rejectionNoteRequired());
    }

    /** @return iterable<string, array{int, bool}> */
    public static function invalidPolicies(): iterable
    {
        yield 'zero maximum' => [0, true];
        yield 'negative maximum' => [-1, true];
        yield 'text column overflow' => [65536, true];
        yield 'rejection note not required' => [1000, false];
    }

    #[DataProvider('invalidPolicies')]
    public function testInvalidPolicyFailsClosed(int $maximum, bool $required): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Review moderation policy is invalid.');

        new ReviewModerationPolicy($maximum, $required);
    }
}
