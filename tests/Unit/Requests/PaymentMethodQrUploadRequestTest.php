<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\UploadedFile;
use App\Exceptions\PaymentMethodException;
use App\Requests\PaymentMethodQrUploadRequest;
use PHPUnit\Framework\TestCase;

final class PaymentMethodQrUploadRequestTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-method-qr-request-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @rmdir($this->directory);
        parent::tearDown();
    }

    public function testMissingUploadIsOptional(): void
    {
        self::assertNull(PaymentMethodQrUploadRequest::fromFile(null));
        self::assertNull(PaymentMethodQrUploadRequest::fromFile(
            new UploadedFile('', '', '', UPLOAD_ERR_NO_FILE, 0),
        ));
    }

    public function testValidPngIsAcceptedUsingServerDetectedMime(): void
    {
        $file = $this->pngUpload('payment-qr.png');
        $request = PaymentMethodQrUploadRequest::fromFile($file);

        self::assertInstanceOf(PaymentMethodQrUploadRequest::class, $request);
        self::assertSame('image/png', $request->detectedMediaType());
        self::assertSame($file, $request->file());
    }

    public function testExtensionAndDetectedMimeMustMatch(): void
    {
        try {
            PaymentMethodQrUploadRequest::fromFile($this->pngUpload('payment-qr.jpg'));
            self::fail('Expected a QR format validation failure.');
        } catch (PaymentMethodException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('QR image format is invalid.', $exception->getMessage());
        }
    }

    private function pngUpload(string $name): UploadedFile
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));

        return new UploadedFile($name, 'client/value', $path, UPLOAD_ERR_OK, strlen($bytes));
    }
}
