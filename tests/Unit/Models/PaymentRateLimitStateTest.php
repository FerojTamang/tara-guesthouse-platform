<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PaymentRateLimitState;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentRateLimitStateTest extends TestCase
{
    public function testItExposesAllowedAndBlockedStates(): void
    {
        $allowed = new PaymentRateLimitState(false, 4, 0);
        self::assertFalse($allowed->isBlocked());
        self::assertSame(4, $allowed->remainingAttempts());
        self::assertSame(0, $allowed->retryAfterSeconds());

        $blocked = new PaymentRateLimitState(true, 0, 900);
        self::assertTrue($blocked->isBlocked());
        self::assertSame(0, $blocked->remainingAttempts());
        self::assertSame(900, $blocked->retryAfterSeconds());
    }

    /** @param array{bool, int, int} $arguments */
    #[DataProvider('invalidStates')]
    public function testItRejectsInconsistentState(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment rate-limit state is invalid.');

        new PaymentRateLimitState(...$arguments);
    }

    /** @return iterable<string, array{array{bool, int, int}}> */
    public static function invalidStates(): iterable
    {
        yield 'negative remaining' => [[false, -1, 0]];
        yield 'negative retry' => [[true, 0, -1]];
        yield 'blocked without retry' => [[true, 0, 0]];
        yield 'blocked with remaining' => [[true, 1, 10]];
        yield 'allowed with retry' => [[false, 1, 10]];
    }
}
