<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation;

use App\Presentation\RoomImageUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoomImageUrlTest extends TestCase
{
    public function testCanonicalFormatsProduceRootRelativeUrls(): void
    {
        $presenter = new RoomImageUrl();

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $path = 'uploads/rooms/' . str_repeat('a', 64) . '.' . $extension;
            self::assertSame('/' . $path, $presenter->fromStoredPath($path));
            self::assertStringNotContainsString('C:', $presenter->fromStoredPath($path));
        }
    }

    #[DataProvider('unsafePaths')]
    public function testMalformedPathsAreRejected(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RoomImageUrl())->fromStoredPath($path);
    }

    public static function unsafePaths(): iterable
    {
        $hash = str_repeat('a', 64);

        yield 'absolute' => ['/uploads/rooms/' . $hash . '.png'];
        yield 'windows absolute' => ['C:\\uploads\\rooms\\' . $hash . '.png'];
        yield 'traversal' => ['uploads/rooms/../' . $hash . '.png'];
        yield 'encoded traversal' => ['uploads/rooms/%2e%2e%2f' . $hash . '.png'];
        yield 'NUL' => ["uploads/rooms/{$hash}\0.png"];
        yield 'uppercase hash' => ['uploads/rooms/' . strtoupper($hash) . '.png'];
        yield 'unsupported extension' => ['uploads/rooms/' . $hash . '.gif'];
        yield 'query' => ['uploads/rooms/' . $hash . '.png?x=1'];
        yield 'fragment' => ['uploads/rooms/' . $hash . '.png#x'];
        yield 'duplicate slash' => ['uploads//rooms/' . $hash . '.png'];
        yield 'backslash' => ['uploads\\rooms\\' . $hash . '.png'];
        yield 'missing segment' => ['rooms/' . $hash . '.png'];
        yield 'extra segment' => ['uploads/rooms/nested/' . $hash . '.png'];
        yield 'short hash' => ['uploads/rooms/' . str_repeat('a', 63) . '.png'];
        yield 'uppercase extension' => ['uploads/rooms/' . $hash . '.PNG'];
    }
}
