<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\UploadedFile;
use App\Exceptions\CmsException;
use App\Requests\SiteLogoUploadRequest;
use PHPUnit\Framework\TestCase;

final class SiteLogoUploadRequestTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-site-logo-request-'
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

    public function testValidPngLogoIsAcceptedUsingServerDetectedMime(): void
    {
        $file = $this->pngUpload('brand-logo.png');
        $request = SiteLogoUploadRequest::fromFile($file);

        self::assertSame($file, $request->file());
        self::assertSame('image/png', $request->detectedMediaType());
    }

    public function testExtensionAndDetectedMimeMustMatch(): void
    {
        try {
            SiteLogoUploadRequest::fromFile($this->pngUpload('brand-logo.jpg'));
            self::fail('Expected a logo format validation failure.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringContainsString('valid JPG, PNG, or WebP', $exception->getMessage());
        }
    }

    private function pngUpload(string $name): UploadedFile
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAAmklEQVR4nO3QQRHAIADAMMAAvtCAfxtDRh5rFPQ69z3f+LGlA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0B6+8gI008GpRAAAAABJRU5ErkJggg==',
            true,
        );
        self::assertIsString($bytes);
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));

        return new UploadedFile($name, 'client/value', $path, UPLOAD_ERR_OK, strlen($bytes));
    }
}
