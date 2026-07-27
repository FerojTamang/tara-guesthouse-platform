<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Services\BookingAccessToken;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingAccessTokenTest extends TestCase
{
    public function testItIssuesAnOpaqueSignedToken(): void
    {
        $tokens = new BookingAccessToken(str_repeat('k', 32));
        $deadline = self::utc('2026-08-01 18:00:00');
        $reference = 'TGH-2026-REFERENCE00000000001';

        $token = $tokens->issue($reference, $deadline);

        self::assertMatchesRegularExpression('/^v1\.[A-Za-z0-9_-]{43}$/D', $token);
        self::assertStringNotContainsString('TGH', $token);
        self::assertTrue($tokens->validates(
            $token,
            $reference,
            $deadline,
            self::utc('2026-08-01 12:00:00'),
        ));
    }

    public function testItBindsKeyReferenceAndDeadline(): void
    {
        $deadline = self::utc('2026-08-01 18:00:00');
        $reference = 'TGH-2026-REFERENCE00000000001';
        $token = (new BookingAccessToken(str_repeat('a', 32)))
            ->issue($reference, $deadline);

        self::assertFalse((new BookingAccessToken(str_repeat('b', 32)))->validates(
            $token,
            $reference,
            $deadline,
            self::utc('2026-08-01 12:00:00'),
        ));
        self::assertFalse((new BookingAccessToken(str_repeat('a', 32)))->validates(
            $token,
            'TGH-2026-REFERENCE00000000002',
            $deadline,
            self::utc('2026-08-01 12:00:00'),
        ));
    }

    public function testExpiredAndMalformedTokensFailClosed(): void
    {
        $tokens = new BookingAccessToken(str_repeat('k', 32));
        $deadline = self::utc('2026-08-01 18:00:00');
        $reference = 'TGH-2026-REFERENCE00000000001';
        $token = $tokens->issue($reference, $deadline);

        self::assertFalse($tokens->validates($token, $reference, $deadline, $deadline));
        self::assertFalse($tokens->validates(
            'v1.invalid',
            $reference,
            $deadline,
            self::utc('2026-08-01 12:00:00'),
        ));
    }

    public function testShortKeyFailsSafely(): void
    {
        try {
            new BookingAccessToken('short');
            self::fail('Expected a short-key failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
        }
    }

    private static function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
