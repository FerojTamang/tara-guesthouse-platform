<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Core\UploadedFile;
use App\Requests\PaymentMethodQrUploadRequest;
use App\Storage\LocalPaymentMethodQrStorage;
use PHPUnit\Framework\TestCase;

final class LocalPaymentMethodQrStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-method-qr-storage-'
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

    public function testItStoresAndRemovesAValidatedQrUsingRandomPublicPath(): void
    {
        $source = $this->directory . DIRECTORY_SEPARATOR . 'source.png';
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);
        self::assertSame(strlen($bytes), file_put_contents($source, $bytes));
        $request = PaymentMethodQrUploadRequest::fromFile(
            new UploadedFile('payment.png', 'image/png', $source, UPLOAD_ERR_OK, strlen($bytes)),
        );
        self::assertInstanceOf(PaymentMethodQrUploadRequest::class, $request);

        $storage = new LocalPaymentMethodQrStorage($this->directory);
        $relativePath = $storage->store($request);

        self::assertMatchesRegularExpression(
            '/^payment-methods\/[a-f0-9]{64}\.png$/D',
            $relativePath,
        );
        $filename = basename($relativePath);
        self::assertFileExists($this->directory . DIRECTORY_SEPARATOR . $filename);
        self::assertSame($bytes, file_get_contents($this->directory . DIRECTORY_SEPARATOR . $filename));

        $storage->remove($relativePath);
        self::assertFileDoesNotExist($this->directory . DIRECTORY_SEPARATOR . $filename);
    }
}
