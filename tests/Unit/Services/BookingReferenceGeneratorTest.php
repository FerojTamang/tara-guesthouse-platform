<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Services\BookingReferenceGenerator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BookingReferenceGeneratorTest extends TestCase
{
    public function testItGeneratesTheApprovedSecureNonSequentialShape(): void
    {
        $generator = new BookingReferenceGenerator(
            static fn (int $length): string => str_repeat("\x00", $length),
        );

        $reference = $generator->generate(
            new DateTimeImmutable('2026-08-01 12:00:00 UTC'),
        );

        self::assertSame('TGH-2026-' . str_repeat('0', 20), $reference);
        self::assertSame(29, strlen($reference));
        self::assertMatchesRegularExpression(
            '/^TGH-[0-9]{4}-[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{20}$/D',
            $reference,
        );
    }

    public function testDifferentEntropyProducesDifferentReferences(): void
    {
        $values = [str_repeat("\x00", 12), str_repeat("\x01", 12)];
        $generator = new BookingReferenceGenerator(
            static function (int $length) use (&$values): string {
                $value = array_shift($values);

                return is_string($value) ? $value : str_repeat("\x02", $length);
            },
        );
        $timestamp = new DateTimeImmutable('2026-08-01 12:00:00 UTC');

        self::assertNotSame(
            $generator->generate($timestamp),
            $generator->generate($timestamp),
        );
    }

    public function testInvalidEntropyIsSanitized(): void
    {
        $generator = new BookingReferenceGenerator(
            static fn (int $length): string => 'too-short',
        );

        try {
            $generator->generate(new DateTimeImmutable('2026-08-01 12:00:00 UTC'));
            self::fail('Expected invalid entropy to fail.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Booking operation failed.', $exception->getMessage());
        }
    }

    public function testEntropyFailureAndNonUtcClockAreSanitized(): void
    {
        $generator = new BookingReferenceGenerator(
            static function (int $length): never {
                throw new RuntimeException('private entropy failure');
            },
        );

        foreach ([
            new DateTimeImmutable('2026-08-01 12:00:00 UTC'),
            new DateTimeImmutable('2026-08-01 12:00:00 +05:45'),
        ] as $timestamp) {
            try {
                $generator->generate($timestamp);
                self::fail('Expected reference generation to fail.');
            } catch (BookingException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertStringNotContainsString('private', $exception->getMessage());
            }
        }
    }
}
