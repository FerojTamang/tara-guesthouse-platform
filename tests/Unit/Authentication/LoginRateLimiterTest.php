<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication;

use App\Authentication\LoginRateLimiter;
use App\Exceptions\AdministratorAuthenticationException;
use PHPUnit\Framework\TestCase;

final class LoginRateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-login-limiter-'
            . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }


    public function testInspectingAnUntouchedAddressDoesNotCreateAStateBucket(): void
    {
        $limiter = $this->limiter();
        $state = $limiter->inspect('203.0.113.55', 500_000);

        self::assertFalse($state->isBlocked());
        self::assertSame(5, $state->remainingAttempts());
        self::assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: []);
    }

    public function testItBlocksTheFifthFailureForFifteenMinutes(): void
    {
        $limiter = $this->limiter();
        $address = '203.0.113.10';
        $now = 1_000_000;

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $state = $limiter->recordFailure($address, $now + $attempt);
            self::assertFalse($state->isBlocked());
            self::assertSame(5 - $attempt, $state->remainingAttempts());
            self::assertSame(0, $state->retryAfterSeconds());
        }

        $blocked = $limiter->recordFailure($address, $now + 5);
        self::assertTrue($blocked->isBlocked());
        self::assertSame(0, $blocked->remainingAttempts());
        self::assertSame(900, $blocked->retryAfterSeconds());

        $inspected = $limiter->inspect($address, $now + 65);
        self::assertTrue($inspected->isBlocked());
        self::assertSame(840, $inspected->retryAfterSeconds());
    }

    public function testAttemptsResetAfterTheWindowWhenNoBlockWasReached(): void
    {
        $limiter = $this->limiter();
        $address = '198.51.100.20';

        $limiter->recordFailure($address, 10_000);
        $limiter->recordFailure($address, 10_100);

        $state = $limiter->inspect($address, 10_900);
        self::assertFalse($state->isBlocked());
        self::assertSame(5, $state->remainingAttempts());
    }

    public function testBlockExpiresAndSuccessfulAuthenticationCanClearTheBucket(): void
    {
        $limiter = $this->limiter();
        $address = '2001:db8::1';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $limiter->recordFailure($address, 20_000 + $attempt);
        }

        $expired = $limiter->inspect($address, 20_904);
        self::assertFalse($expired->isBlocked());
        self::assertSame(5, $expired->remainingAttempts());
        self::assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: []);

        $limiter->recordFailure($address, 21_000);
        self::assertCount(1, glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: []);
        $limiter->clear($address);
        self::assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: []);
        $cleared = $limiter->inspect($address, 21_001);

        self::assertFalse($cleared->isBlocked());
        self::assertSame(5, $cleared->remainingAttempts());
    }

    public function testDifferentRemoteAddressesUseIndependentHashedBuckets(): void
    {
        $limiter = $this->limiter();

        $limiter->recordFailure('192.0.2.10', 30_000);
        $limiter->recordFailure('192.0.2.11', 30_000);

        self::assertSame(4, $limiter->inspect('192.0.2.10', 30_001)->remainingAttempts());
        self::assertSame(4, $limiter->inspect('192.0.2.11', 30_001)->remainingAttempts());

        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.json');
        self::assertIsArray($files);
        self::assertCount(2, $files);

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringNotContainsString('192.0.2.', $file);
            self::assertStringNotContainsString('192.0.2.', $contents);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}\.json$/D', basename($file));
        }
    }

    public function testInvalidRemoteAddressAndCorruptStateFailClosedSafely(): void
    {
        $limiter = $this->limiter();

        try {
            $limiter->inspect('not-an-ip-address', 40_000);
            self::fail('Expected invalid address failure.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Administrator authentication failed.', $exception->getMessage());
            self::assertStringNotContainsString('not-an-ip-address', $exception->getMessage());
        }

        $limiter->recordFailure('203.0.113.99', 40_000);
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.json');
        self::assertIsArray($files);
        self::assertCount(1, $files);
        file_put_contents($files[0], '{corrupt');

        $this->expectException(AdministratorAuthenticationException::class);
        $limiter->inspect('203.0.113.99', 40_001);
    }

    public function testMalformedPolicyFailsBeforeStateCanBeWritten(): void
    {
        try {
            new LoginRateLimiter($this->directory, []);
            self::fail('Expected malformed policy failure.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertDirectoryDoesNotExist($this->directory);
        }
    }

    private function limiter(): LoginRateLimiter
    {
        return new LoginRateLimiter($this->directory, [
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'identifier_max_bytes' => 150,
            'password_max_bytes' => 72,
            'max_failed_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ]);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*');

        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($directory);
    }
}
