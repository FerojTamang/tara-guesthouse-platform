<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ContactException;
use App\Services\PublicContactRateLimiter;
use PHPUnit\Framework\TestCase;

final class PublicContactRateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-public-contact-limit-'
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
        }

        $blocked = $limiter->consume('203.0.113.10', 10_006);
        self::assertTrue($blocked->isBlocked());
        self::assertSame(900, $blocked->retryAfterSeconds());

        $reset = $limiter->consume('203.0.113.10', 10_906);
        self::assertFalse($reset->isBlocked());
        self::assertSame(4, $reset->remainingAttempts());
    }

    public function testBucketsDoNotExposeRawAddresses(): void
    {
        $limiter = $this->limiter();
        $limiter->consume('192.0.2.10', 20_000);
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [];

        self::assertCount(1, $files);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}\.json$/D', basename($files[0]));
        self::assertStringNotContainsString('192.0.2.10', (string) file_get_contents($files[0]));
    }

    public function testInvalidAddressPolicyAndCorruptStateFailClosed(): void
    {
        try {
            $this->limiter()->consume('not-an-ip', 40_000);
            self::fail('Expected an invalid address failure.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        try {
            new PublicContactRateLimiter($this->directory . '-bad', [
                'max_attempts' => 0,
                'attempt_window_seconds' => 900,
                'block_duration_seconds' => 900,
            ]);
            self::fail('Expected an invalid policy failure.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        $limiter = $this->limiter();
        $limiter->consume('203.0.113.99', 40_000);
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        self::assertCount(1, $files);
        file_put_contents($files[0], '{corrupt');

        $this->expectException(ContactException::class);
        $this->expectExceptionMessage('Contact workflow operation failed.');
        $limiter->consume('203.0.113.99', 40_001);
    }

    private function limiter(): PublicContactRateLimiter
    {
        return new PublicContactRateLimiter($this->directory, [
            'max_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ]);
    }
}
