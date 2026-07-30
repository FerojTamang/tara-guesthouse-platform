<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GuestPaymentException;
use App\Services\PublicPaymentRateLimiter;
use PHPUnit\Framework\TestCase;

final class PublicPaymentRateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-public-payment-limit-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->directory);
    }

    public function testItAllowsFiveAttemptsThenBlocksForFifteenMinutes(): void
    {
        $limiter = $this->limiter();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $state = $limiter->consume('203.0.113.10', 10_000 + $attempt);

            self::assertFalse($state->isBlocked());
            self::assertSame(5 - $attempt, $state->remainingAttempts());
            self::assertSame(0, $state->retryAfterSeconds());
        }

        $blocked = $limiter->consume('203.0.113.10', 10_006);
        self::assertTrue($blocked->isBlocked());
        self::assertSame(900, $blocked->retryAfterSeconds());

        $stillBlocked = $limiter->consume('203.0.113.10', 10_100);
        self::assertTrue($stillBlocked->isBlocked());
        self::assertSame(806, $stillBlocked->retryAfterSeconds());

        $reset = $limiter->consume('203.0.113.10', 10_906);
        self::assertFalse($reset->isBlocked());
        self::assertSame(4, $reset->remainingAttempts());
    }

    public function testBucketsAreSeparateAndNeverStoreRawAddresses(): void
    {
        $limiter = $this->limiter();
        $limiter->consume('192.0.2.10', 20_000);
        $limiter->consume('192.0.2.11', 20_000);
        $files = array_values(array_filter(
            glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [],
            'is_file',
        ));

        self::assertCount(2, $files);

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}\.json$/D', basename($file));
            self::assertStringNotContainsString('192.0.2.', $file);
            self::assertStringNotContainsString('192.0.2.', $contents);
        }
    }

    public function testAttemptWindowExpiryStartsASeparateAllowance(): void
    {
        $limiter = $this->limiter();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $limiter->consume('198.51.100.5', 30_000 + $attempt);
        }

        $state = $limiter->consume('198.51.100.5', 30_900);
        self::assertFalse($state->isBlocked());
        self::assertSame(4, $state->remainingAttempts());
    }

    public function testInvalidAddressPolicyAndCorruptStateFailClosed(): void
    {
        try {
            $this->limiter()->consume('not-an-ip', 40_000);
            self::fail('Expected invalid address failure.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        try {
            new PublicPaymentRateLimiter($this->directory . '-bad', [
                'max_attempts' => 6,
                'attempt_window_seconds' => 900,
                'block_duration_seconds' => 900,
            ]);
            self::fail('Expected changed policy failure.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        $limiter = $this->limiter();
        $limiter->consume('203.0.113.99', 40_000);
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        self::assertCount(1, $files);
        file_put_contents($files[0], '{corrupt');

        $this->expectException(GuestPaymentException::class);
        $this->expectExceptionMessage('Payment operation failed.');
        $limiter->consume('203.0.113.99', 40_001);
    }

    private function limiter(): PublicPaymentRateLimiter
    {
        return new PublicPaymentRateLimiter($this->directory, [
            'max_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ]);
    }
}
