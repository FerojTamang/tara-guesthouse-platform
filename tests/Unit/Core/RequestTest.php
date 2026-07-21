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
