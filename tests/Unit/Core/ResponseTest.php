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

    public function testHtmlResponseUsesUtf8HtmlDefaults(): void
    {
        $response = Response::html('<main>Ready</main>');

        self::assertSame(200, $response->statusCode());
        self::assertSame('<main>Ready</main>', $response->body());
        self::assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
    }


    public function testJsonResponseUsesSafeEncodingAndUtf8ContentType(): void
    {
        $response = Response::json(['message' => 'Namasté', 'path' => '/admin'], 201);

        self::assertSame(201, $response->statusCode());
        self::assertSame('{"message":"Namasté","path":"/admin"}', $response->body());
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
    }

    public function testRedirectResponseUsesAnInternalLocationAndEmptyBody(): void
    {
        $response = Response::redirect('/admin/login?expired=1', 303);

        self::assertSame(303, $response->statusCode());
        self::assertSame('', $response->body());
        self::assertSame('/admin/login?expired=1', $response->headers()['Location']);
    }

    public function testRedirectResponseRejectsExternalLocations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::redirect('https://example.com');
    }

    public function testRedirectResponseRejectsSchemeRelativeLocations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::redirect('//example.com');
    }

    public function testRedirectResponseRejectsInvalidStatusCodes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::redirect('/admin/login', 200);
    }

    public function testHtmlResponseSupportsCustomStatusAndSafeHeaders(): void
    {
        $response = Response::html('<p>Created</p>', 201, ['X-Content-Type-Options' => 'nosniff']);

        self::assertSame(201, $response->statusCode());
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
    }

    public function testHtmlResponsePreservesExplicitContentType(): void
    {
        $response = Response::html('<p>Ready</p>', headers: ['content-type' => 'application/xhtml+xml']);

        self::assertSame(['content-type' => 'application/xhtml+xml'], $response->headers());
    }

    public function testHtmlResponseRejectsHeaderInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::html('No', headers: ['X-Test' => "value\r\nInjected: true"]);
    }

    public function testHtmlResponseRemainsImmutable(): void
    {
        $original = Response::html('<p>Ready</p>');
        $updated = $original->withHeader('X-Frame-Options', 'DENY');

        self::assertArrayNotHasKey('X-Frame-Options', $original->headers());
        self::assertSame('DENY', $updated->headers()['X-Frame-Options']);
        self::assertSame('text/html; charset=UTF-8', $updated->headers()['Content-Type']);
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
