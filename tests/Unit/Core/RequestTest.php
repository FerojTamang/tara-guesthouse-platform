<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use App\Core\UploadedFile;
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

    public function testItAcceptsInjectedFilesWithoutReadingGlobals(): void
    {
        $request = Request::create('POST', '/upload', files: [
            'image' => [
                'name' => 'room.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => 'temporary-file',
                'error' => UPLOAD_ERR_OK,
                'size' => 123,
            ],
        ]);

        $file = $request->file('image');

        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('room.jpg', $file->originalName());
        self::assertSame(['image' => $file], $request->file());
        self::assertNull($request->file('missing'));
    }

    public function testItNormalizesNestedPhpFileArrays(): void
    {
        $request = Request::create('POST', '/upload', files: [
            'images' => [
                'name' => ['room' => ['first' => 'one.png', 'second' => 'two.webp']],
                'type' => ['room' => ['first' => 'image/png', 'second' => 'image/webp']],
                'tmp_name' => ['room' => ['first' => 'tmp-one', 'second' => 'tmp-two']],
                'error' => ['room' => ['first' => UPLOAD_ERR_OK, 'second' => UPLOAD_ERR_OK]],
                'size' => ['room' => ['first' => 100, 'second' => 200]],
            ],
        ]);

        $images = $request->file('images');

        self::assertIsArray($images);
        self::assertInstanceOf(UploadedFile::class, $images['room']['first']);
        self::assertSame('two.webp', $images['room']['second']->originalName());
    }

    public function testItRejectsMalformedNestedPhpFileArrays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Request::create('POST', '/upload', files: [
            'images' => [
                'name' => ['first' => 'one.png'],
                'type' => ['different' => 'image/png'],
                'tmp_name' => ['first' => 'tmp-one'],
                'error' => ['first' => UPLOAD_ERR_OK],
                'size' => ['first' => 100],
            ],
        ]);
    }

    public function testItRejectsMixedLeafAndNestedUploadFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Request::create('POST', '/upload', files: [
            'image' => [
                'name' => ['first' => 'one.png'],
                'type' => 'image/png',
                'tmp_name' => ['first' => 'tmp-one'],
                'error' => ['first' => UPLOAD_ERR_OK],
                'size' => ['first' => 100],
            ],
        ]);
    }
}
