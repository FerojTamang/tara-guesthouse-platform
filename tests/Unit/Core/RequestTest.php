<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testItNormalizesMethodPathQueryAndHeaders(): void
    {
        $request = Request::create(
            'get',
            '/status?source=browser',
            null,
            ['X-Request-ID' => 'request-1'],
        );

        self::assertSame('GET', $request->method());
        self::assertSame('/status', $request->path());
        self::assertSame('browser', $request->query('source'));
        self::assertSame('request-1', $request->header('x-request-id'));
    }


    public function testItProvidesParsedInputAndRawBody(): void
    {
        $request = Request::create(
            'POST',
            '/bookings',
            [],
            ['Content-Type' => 'application/json'],
            ['guest_name' => 'Maya'],
            '{"guest_name":"Maya"}',
        );

        self::assertSame(['guest_name' => 'Maya'], $request->input());
        self::assertSame('Maya', $request->input('guest_name'));
        self::assertSame('fallback', $request->input('missing', 'fallback'));
        self::assertSame('{"guest_name":"Maya"}', $request->body());
    }


    public function testItStoresOnlyAValidatedDirectRemoteAddress(): void
    {
        $request = Request::create('POST', '/admin/login', remoteAddress: '2001:db8::10');

        self::assertSame('2001:db8::10', $request->remoteAddress());
        self::assertNull(Request::create('GET', '/')->remoteAddress());
    }

    public function testItRejectsAnInvalidRemoteAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Request::create('POST', '/admin/login', remoteAddress: 'not-an-ip');
    }

    public function testItRejectsEncodedNullBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Request::create('GET', '/safe/%00/file');
    }

    public function testItRejectsEncodedTraversalSegments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Request::create('GET', '/safe/%2e%2e/secret');
    }

    public function testItRejectsMalformedPercentEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Request::create('GET', '/bad/%ZZ');
    }

    public function testItRejectsHeaderValueInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Request::create('GET', '/', [], ['X-Test' => "safe\r\nInjected: true"]);
    }

    public function testItRejectsHeaderNamesInsteadOfSilentlyTrimmingThem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Request::create('GET', '/', [], [' X-Test' => 'value']);
    }
}
