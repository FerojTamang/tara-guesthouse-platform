<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Environment;
use App\Exceptions\ConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function booleanValues(): iterable
    {
        yield 'true' => ['true', true];
        yield 'false' => ['false', false];
        yield 'one' => ['1', true];
        yield 'zero' => ['0', false];
        yield 'yes' => ['yes', true];
        yield 'no' => ['no', false];
        yield 'on' => ['on', true];
        yield 'off' => ['off', false];
    }

    #[DataProvider('booleanValues')]
    public function testItStrictlyParsesConventionalBooleanValues(string $value, bool $expected): void
    {
        $environment = Environment::fromArray(['FLAG' => $value]);

        self::assertSame($expected, $environment->bool('FLAG'));
    }

    public function testItRejectsInvalidBooleanValues(): void
    {
        $environment = Environment::fromArray(['FLAG' => 'sometimes']);

        $this->expectException(ConfigurationException::class);
        $environment->bool('FLAG');
    }

    public function testItStrictlyParsesIntegers(): void
    {
        $environment = Environment::fromArray(['COUNT' => '42']);

        self::assertSame(42, $environment->int('COUNT'));
    }

    public function testItRejectsInvalidIntegers(): void
    {
        $environment = Environment::fromArray(['COUNT' => '42 rooms']);

        $this->expectException(ConfigurationException::class);
        $environment->int('COUNT');
    }

    public function testItRejectsMissingRequiredValues(): void
    {
        $environment = Environment::fromArray([]);

        $this->expectException(ConfigurationException::class);
        $environment->string('REQUIRED');
    }

    public function testNullableStringsReturnNullForMissingAndEmptyValues(): void
    {
        $environment = Environment::fromArray(['EMPTY' => '']);

        self::assertNull($environment->nullableString('MISSING'));
        self::assertNull($environment->nullableString('EMPTY'));
    }

    public function testLiteralStringsAreReturnedWithoutNormalization(): void
    {
        $environment = Environment::fromArray([
            'SPACED' => ' value ',
            'EMPTY' => '',
        ]);

        self::assertSame(' value ', $environment->literalString('SPACED'));
        self::assertSame('', $environment->literalString('EMPTY'));
        self::assertSame('fallback', $environment->literalString('MISSING', 'fallback'));
    }

    public function testExistingProcessValuesTakePrecedenceOverDotenvValues(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-env-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        file_put_contents($directory . DIRECTORY_SEPARATOR . '.env', "TGH_PRECEDENCE=dotenv\n");

        $originalProcess = getenv('TGH_PRECEDENCE');
        $originalServer = $_SERVER['TGH_PRECEDENCE'] ?? null;
        $originalEnvironment = $_ENV['TGH_PRECEDENCE'] ?? null;

        unset($_SERVER['TGH_PRECEDENCE'], $_ENV['TGH_PRECEDENCE']);
        putenv('TGH_PRECEDENCE=process');

        try {
            $environment = Environment::load($directory);

            self::assertSame('process', $environment->string('TGH_PRECEDENCE'));
        } finally {
            $originalProcess === false
                ? putenv('TGH_PRECEDENCE')
                : putenv('TGH_PRECEDENCE=' . $originalProcess);

            if ($originalServer === null) {
                unset($_SERVER['TGH_PRECEDENCE']);
            } else {
                $_SERVER['TGH_PRECEDENCE'] = $originalServer;
            }

            if ($originalEnvironment === null) {
                unset($_ENV['TGH_PRECEDENCE']);
            } else {
                $_ENV['TGH_PRECEDENCE'] = $originalEnvironment;
            }

            unlink($directory . DIRECTORY_SEPARATOR . '.env');
            rmdir($directory);
        }
    }

    public function testMissingDotenvFileIsAllowed(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-env-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));

        try {
            self::assertInstanceOf(Environment::class, Environment::load($directory));
        } finally {
            rmdir($directory);
        }
    }
}
