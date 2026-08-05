<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Core\UploadedFile;
use App\Requests\SiteLogoUploadRequest;
use App\Storage\LocalSiteLogoStorage;
use PHPUnit\Framework\TestCase;

final class LocalSiteLogoStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-site-logo-storage-'
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

    public function testItStoresAndRemovesValidatedLogoUsingRandomPublicPath(): void
    {
        $source = $this->directory . DIRECTORY_SEPARATOR . 'source.png';
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAAmklEQVR4nO3QQRHAIADAMMAAvtCAfxtDRh5rFPQ69z3f+LGlA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0BqgA7QG6ACtATpAa4AO0B6+8gI008GpRAAAAABJRU5ErkJggg==',
            true,
        );
        self::assertIsString($bytes);
        self::assertSame(strlen($bytes), file_put_contents($source, $bytes));
        $request = SiteLogoUploadRequest::fromFile(
            new UploadedFile('logo.png', 'image/png', $source, UPLOAD_ERR_OK, strlen($bytes)),
        );

        $storage = new LocalSiteLogoStorage($this->directory);
        $relativePath = $storage->store($request);

        self::assertMatchesRegularExpression(
            '/^uploads\/branding\/[a-f0-9]{64}\.png$/D',
            $relativePath,
        );
        $filename = basename($relativePath);
        self::assertFileExists($this->directory . DIRECTORY_SEPARATOR . $filename);
        self::assertSame($bytes, file_get_contents($this->directory . DIRECTORY_SEPARATOR . $filename));

        $storage->remove($relativePath);
        self::assertFileDoesNotExist($this->directory . DIRECTORY_SEPARATOR . $filename);
    }
}
