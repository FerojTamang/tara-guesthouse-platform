<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\Config;
use App\Core\Environment;
use App\Exceptions\AdministratorBootstrapException;
use App\Requests\AdministratorBootstrapRequest;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorBootstrapRequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $policy;

    protected function setUp(): void
    {
        $configuration = Config::loadSecurity(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config',
            Environment::fromArray([]),
        );
        $this->policy = $configuration['administrator_bootstrap'];
    }

    public function testItNormalizesApprovedIdentityValuesAndPreservesPhoneFormatting(): void
    {
        $secret = $this->secret();
        $request = AdministratorBootstrapRequest::fromInput(
            "\u{2003}Tārā\u{00A0}  Administrator\u{2003}",
            '  Initial.Admin-1 ',
            ' INITIAL.ADMIN@EXAMPLE.TEST ',
            ' +977 (01) 555-0101 ',
            $secret,
            $secret,
            $this->policy,
        );

        self::assertSame('Tārā Administrator', $request->fullName());
        self::assertSame('initial.admin-1', $request->username());
        self::assertSame('initial.admin@example.test', $request->email());
        self::assertSame('+977 (01) 555-0101', $request->phone());
        self::assertSame($secret, $request->password());
    }

    public function testEmptyPhoneBecomesNull(): void
    {
        self::assertNull($this->request(phone: '   ')->phone());
        self::assertNull($this->request(phone: null)->phone());
    }

    public function testIdentityFieldsAcceptEveryExactLengthBoundary(): void
    {
        self::assertSame('ĀB', $this->request(fullName: 'ĀB')->fullName());
        self::assertSame(str_repeat('Ā', 100), $this->request(fullName: str_repeat('Ā', 100))->fullName());
        self::assertSame('a1b', $this->request(username: 'a1b')->username());
        self::assertSame(str_repeat('a', 50), $this->request(username: str_repeat('a', 50))->username());

        $maximumEmail = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 21);
        self::assertSame(150, strlen($maximumEmail));
        self::assertSame($maximumEmail, $this->request(email: $maximumEmail)->email());
        self::assertSame('1234567', $this->request(phone: '1234567')->phone());
        self::assertSame('+123 456 789 012 345', $this->request(phone: '+123 456 789 012 345')->phone());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFullNames(): iterable
    {
        yield 'empty' => [''];
        yield 'one character' => ['A'];
        yield 'no letter' => ['---'];
        yield 'control' => ["Admin\nName"];
        yield 'format control' => ["Admin\u{200B}Name"];
        yield 'too long' => [str_repeat('A', 101)];
        yield 'invalid utf8' => ["Admin\xFF"];
    }

    #[DataProvider('invalidFullNames')]
    public function testItRejectsInvalidFullNames(string $value): void
    {
        $this->expectUsageFailure(fn () => $this->request(fullName: $value), 'Full name');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUsernames(): iterable
    {
        yield 'too short' => ['ab'];
        yield 'too long' => [str_repeat('a', 51)];
        yield 'leading punctuation' => ['.admin'];
        yield 'trailing punctuation' => ['admin-'];
        yield 'space' => ['admin user'];
        yield 'unicode' => ['admín'];
        yield 'unsupported punctuation' => ['admin+user'];
    }

    #[DataProvider('invalidUsernames')]
    public function testItRejectsInvalidUsernames(string $value): void
    {
        $this->expectUsageFailure(fn () => $this->request(username: $value), 'Username');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEmails(): iterable
    {
        yield 'invalid structure' => ['not-an-email'];
        yield 'unicode domain' => ['admin@éxample.test'];
        yield 'too long' => [str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 22)];
        yield 'embedded control' => ["admin@\nexample.test"];
    }

    #[DataProvider('invalidEmails')]
    public function testItRejectsInvalidEmails(string $value): void
    {
        $this->expectUsageFailure(fn () => $this->request(email: $value), 'Email');
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidPhones(): iterable
    {
        yield 'too few digits' => ['+977 12'];
        yield 'too many digits' => ['+1234567890123456'];
        yield 'invalid plus position' => ['977+1234567'];
        yield 'letters' => ['+977 ADMIN01'];
        yield 'too long' => ['+977 (01) 555  - 0101'];
        yield 'control' => ["+9771234567\n"];
    }

    #[DataProvider('invalidPhones')]
    public function testItRejectsInvalidPhones(?string $value): void
    {
        $this->expectUsageFailure(fn () => $this->request(phone: $value), 'Phone');
    }

    public function testPasswordBoundariesUseCharactersAndBytesWithoutNormalization(): void
    {
        $minimum = str_repeat('ā', 15);
        self::assertSame($minimum, $this->request(password: $minimum, confirmation: $minimum)->password());

        $maximumBytes = str_repeat('€', 24);
        self::assertSame(72, strlen($maximumBytes));
        self::assertSame(
            $maximumBytes,
            $this->request(password: $maximumBytes, confirmation: $maximumBytes)->password(),
        );

        $this->expectUsageFailure(
            fn () => $this->request(password: str_repeat('x', 14), confirmation: str_repeat('x', 14)),
            'Password',
        );
        $this->expectUsageFailure(
            fn () => $this->request(password: str_repeat('€', 25), confirmation: str_repeat('€', 25)),
            'Password',
        );
    }

    public function testPasswordSpacesArePreservedAndConfirmationIsByteExact(): void
    {
        $secret = ' ' . str_repeat('x', 15) . ' ';
        self::assertSame($secret, $this->request(password: $secret, confirmation: $secret)->password());

        $composed = str_repeat('x', 14) . 'é';
        $decomposed = str_repeat('x', 14) . "e\u{0301}";
        $this->expectUsageFailure(
            fn () => $this->request(password: $composed, confirmation: $decomposed),
            'Password confirmation',
        );
    }

    /** @return iterable<string, array{string}> */
    public static function controlledPasswords(): iterable
    {
        yield 'nul' => [str_repeat('x', 15) . "\0"];
        yield 'line feed' => [str_repeat('x', 15) . "\n"];
        yield 'carriage return' => [str_repeat('x', 15) . "\r"];
        yield 'format control' => [str_repeat('x', 15) . "\u{200B}"];
        yield 'invalid utf8' => [str_repeat('x', 15) . "\xFF"];
    }

    #[DataProvider('controlledPasswords')]
    public function testItRejectsControlOrInvalidPasswordDataWithoutDisclosingIt(string $secret): void
    {
        $this->expectUsageFailure(
            fn () => $this->request(password: $secret, confirmation: $secret),
            'Password',
            $secret,
        );
    }

    public function testDebugAndSerializationCannotExposePasswordMaterial(): void
    {
        $secret = $this->secret();
        $request = $this->request(password: $secret, confirmation: $secret);
        $debug = print_r($request, true);

        self::assertStringNotContainsString($secret, $debug);
        self::assertStringNotContainsString('confirmation', strtolower($debug));

        try {
            serialize($request);
            self::fail('Bootstrap request serialization was accepted.');
        } catch (LogicException $exception) {
            self::assertStringNotContainsString($secret, $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $request->__unserialize([]);
    }

    private function request(
        string $fullName = 'Initial Administrator',
        string $username = 'initial-admin',
        string $email = 'initial-admin@example.test',
        ?string $phone = '+977 15550101',
        ?string $password = null,
        ?string $confirmation = null,
    ): AdministratorBootstrapRequest {
        $password ??= $this->secret();
        $confirmation ??= $password;

        return AdministratorBootstrapRequest::fromInput(
            $fullName,
            $username,
            $email,
            $phone,
            $password,
            $confirmation,
            $this->policy,
        );
    }

    private function secret(): string
    {
        return 'synthetic-' . str_repeat('q', 10);
    }

    private function expectUsageFailure(callable $operation, string $field, ?string $secret = null): void
    {
        try {
            $operation();
            self::fail('Invalid bootstrap input was accepted.');
        } catch (AdministratorBootstrapException $exception) {
            self::assertSame(2, $exception->exitCode());
            self::assertStringContainsString($field, $exception->getMessage());

            if ($secret !== null) {
                self::assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }
}
