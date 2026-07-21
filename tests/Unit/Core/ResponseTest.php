<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testTextResponseUsesUtf8PlainTextDefaults(): void
    {
        $response = Response::text('Ready', 202);

        self::assertSame(202, $response->statusCode());
        self::assertSame('Ready', $response->body());
        self::assertSame('text/plain; charset=UTF-8', $response->headers()['Content-Type']);
    }

    public function testItIsImmutableWhenAddingHeaders(): void
    {
        $original = Response::text('Ready');
        $updated = $original->withHeader('X-Frame-Options', 'DENY');

        self::assertArrayNotHasKey('X-Frame-Options', $original->headers());
        self::assertSame('DENY', $updated->headers()['X-Frame-Options']);
    }

    public function testItRejectsInvalidStatusCodes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Response('', 99);
    }

    public function testItRejectsHeaderNameInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::text('No', headers: ["X-Test\r\nBad" => 'value']);
    }

    public function testItRejectsHeaderValueInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::text('No', headers: ['X-Test' => "value\r\nInjected: true"]);
    }

    public function testItRejectsCaseInsensitiveDuplicateHeaders(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Response('', headers: [
            'Content-Type' => 'text/plain',
            'content-type' => 'text/html',
        ]);
    }
}
