<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ReviewException;
use App\Services\ReviewTokenHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewTokenHasherTest extends TestCase
{
    public function testGeneratesSixtyFourHexCharactersAndDeterministicHash(): void
    {
        $hasher = new ReviewTokenHasher(static function (int $length): string {
            self::assertSame(32, $length);
            return str_repeat("\xAB", $length);
        });

        $token = $hasher->generate();

        self::assertSame(str_repeat('ab', 32), $token);
        self::assertSame(hash('sha256', $token), $hasher->hash($token));
        self::assertNotSame($token, $hasher->hash($token));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'short' => [str_repeat('a', 63)];
        yield 'uppercase' => [str_repeat('A', 64)];
        yield 'non hex' => [str_repeat('z', 64)];
        yield 'whitespace' => [str_repeat('a', 63) . ' '];
    }

    #[DataProvider('invalidTokens')]
    public function testInvalidTokensAreRejectedWithoutEchoingThem(string $token): void
    {
        $hasher = new ReviewTokenHasher();

        try {
            $hasher->hash($token);
            self::fail('An invalid review token was accepted.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isValidationFailure());
            if ($token !== '') {
                self::assertStringNotContainsString($token, $exception->getMessage());
            }
        }
    }

    public function testRandomSourceFailureIsSanitized(): void
    {
        foreach ([
            static fn (int $length): string => str_repeat('x', $length - 1),
            static fn (int $length): never => throw new \RuntimeException('PRIVATE_RANDOM_FAILURE'),
        ] as $source) {
            try {
                (new ReviewTokenHasher($source))->generate();
                self::fail('An invalid random source was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Review workflow operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('PRIVATE_RANDOM_FAILURE', $exception->getMessage());
            }
        }
    }
}
