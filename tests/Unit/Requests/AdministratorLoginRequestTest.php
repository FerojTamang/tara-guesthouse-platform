<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\AdministratorAuthenticationException;
use App\Requests\AdministratorLoginRequest;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorLoginRequestTest extends TestCase
{
    public function testItNormalizesAUsernameAndPreservesPasswordBytes(): void
    {
        $request = AdministratorLoginRequest::fromInput(
            "  ADMIN.User  ",
            "  test-only-password  ",
            self::policy(),
        );

        self::assertSame('admin.user', $request->identifier());
        self::assertSame(AdministratorLoginRequest::IDENTIFIER_USERNAME, $request->identifierType());
        self::assertTrue($request->isUsernameIdentifier());
        self::assertFalse($request->isEmailIdentifier());
        self::assertSame("  test-only-password  ", $request->password());
    }

    public function testItNormalizesAnEmailIdentifier(): void
    {
        $request = AdministratorLoginRequest::fromInput(
            '  ADMINISTRATOR@EXAMPLE.TEST  ',
            'test-only-password',
            self::policy(),
        );

        self::assertSame('administrator@example.test', $request->identifier());
        self::assertSame(AdministratorLoginRequest::IDENTIFIER_EMAIL, $request->identifierType());
        self::assertTrue($request->isEmailIdentifier());
        self::assertFalse($request->isUsernameIdentifier());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidIdentifiers(): iterable
    {
        yield 'not a string' => [17];
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'too short username' => ['ab'];
        yield 'username begins punctuation' => ['.admin'];
        yield 'username ends punctuation' => ['admin-'];
        yield 'username has spaces' => ['admin user'];
        yield 'username unicode' => ['admín'];
        yield 'invalid email' => ['administrator@'];
        yield 'email unicode' => ['admin@éxample.test'];
        yield 'control character' => ["admin\nuser"];
        yield 'over database maximum' => [str_repeat('a', 151) . '@example.test'];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testItRejectsInvalidIdentifiersWithoutEchoingValues(mixed $identifier): void
    {
        try {
            AdministratorLoginRequest::fromInput(
                $identifier,
                'test-only-password',
                self::policy(),
            );
            self::fail('Expected login identifier rejection.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Login identifier is invalid.', $exception->getMessage());
            self::assertStringNotContainsString('administrator@', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidPasswords(): iterable
    {
        yield 'not a string' => [null];
        yield 'empty' => [''];
        yield 'over bcrypt boundary' => [str_repeat('x', 73)];
        yield 'line feed' => ["password\n"];
        yield 'nul byte' => ["password\0"];
        yield 'invalid UTF-8' => ["password\xFF"];
    }

    #[DataProvider('invalidPasswords')]
    public function testItRejectsInvalidPasswordsWithoutDisclosure(mixed $password): void
    {
        try {
            AdministratorLoginRequest::fromInput('administrator', $password, self::policy());
            self::fail('Expected password rejection.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Password is invalid.', $exception->getMessage());
            if (is_string($password) && $password !== '') {
                self::assertStringNotContainsString($password, $exception->getMessage());
            }
        }
    }

    public function testDebugOutputAndSerializationCannotExposeThePassword(): void
    {
        $secret = 'test-only-password-sentinel';
        $request = AdministratorLoginRequest::fromInput('administrator', $secret, self::policy());
        $debug = print_r($request, true);

        self::assertStringNotContainsString($secret, $debug);
        self::assertSame([
            'identifier' => 'administrator',
            'identifierType' => AdministratorLoginRequest::IDENTIFIER_USERNAME,
        ], $request->__debugInfo());

        $this->expectException(LogicException::class);
        serialize($request);
    }

    public function testMalformedPolicyFailsSafely(): void
    {
        try {
            AdministratorLoginRequest::fromInput('administrator', 'test-only-password', []);
            self::fail('Expected malformed policy failure.');
        } catch (AdministratorAuthenticationException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Administrator authentication failed.', $exception->getMessage());
        }
    }

    /** @return array<string, int> */
    private static function policy(): array
    {
        return [
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'identifier_max_bytes' => 150,
            'password_max_bytes' => 72,
            'max_failed_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ];
    }
}
