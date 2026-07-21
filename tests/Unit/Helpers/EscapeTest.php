<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\Escape;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use stdClass;

final class EscapeTest extends TestCase
{
    public function testItEscapesHtmlTextAndQuotes(): void
    {
        self::assertSame('Guest room', Escape::html('Guest room'));
        self::assertSame('Tom &amp; Tara', Escape::html('Tom & Tara'));
        self::assertSame('&quot;double&quot; and &apos;single&apos;', Escape::html('"double" and \'single\''));
        self::assertSame('&lt;strong&gt;not raw&lt;/strong&gt;', Escape::html('<strong>not raw</strong>'));
    }

    #[DataProvider('scalarValues')]
    public function testItAcceptsNullAndScalarValues(mixed $value, string $expected): void
    {
        self::assertSame($expected, Escape::html($value));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function scalarValues(): iterable
    {
        yield 'null' => [null, ''];
        yield 'integer' => [42, '42'];
        yield 'float' => [12.5, '12.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
    }

    public function testItSubstitutesInvalidUtf8(): void
    {
        $escaped = Escape::html("invalid-\xC3-value");

        self::assertSame('invalid-�-value', $escaped);
    }

    #[DataProvider('unsupportedValues')]
    public function testItRejectsUnsupportedValues(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Escape::html($value);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unsupportedValues(): iterable
    {
        yield 'array' => [['unsafe']];
        yield 'object' => [new stdClass()];
        yield 'stringable object' => [new class implements Stringable {
            public function __toString(): string
            {
                return 'unsafe';
            }
        }];
    }

    public function testItRejectsResources(): void
    {
        $resource = fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        try {
            $this->expectException(InvalidArgumentException::class);
            Escape::html($resource);
        } finally {
            fclose($resource);
        }
    }
}
