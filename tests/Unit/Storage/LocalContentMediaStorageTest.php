<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Core\UploadedFile;
use App\Requests\ContentMediaUploadRequest;
use App\Storage\LocalContentMediaStorage;
use PHPUnit\Framework\TestCase;

final class LocalContentMediaStorageTest extends TestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAUAAAAC0CAIAAABqhmJGAAABkUlEQVR42u3TsQkAAAgEMXX/Yb+xdw4hGeHgOpsCfhoJwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGDAwGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBAQODgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwICB4bcD4XcEPQdXsdAAAAAASUVORK5CYII=';

    private string $directory;
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-content-media-storage-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
        $this->source = $this->directory . DIRECTORY_SEPARATOR . 'source.tmp';
        $bytes = base64_decode(self::PNG_BASE64, true);
        self::assertIsString($bytes);
        self::assertSame(strlen($bytes), file_put_contents($this->source, $bytes));
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

    public function testItStoresAndRemovesValidatedImageUsingRandomPublicPath(): void
    {
        $size = (int) filesize($this->source);
        $request = ContentMediaUploadRequest::fromInput(
            new UploadedFile('garden.png', 'image/png', $this->source, UPLOAD_ERR_OK, $size),
            'Garden',
            'Garden terrace with mountain view',
            null,
            $this->policy(),
        );
        $storage = new LocalContentMediaStorage($this->directory);
        $stored = $storage->store($request);

        self::assertMatchesRegularExpression(
            '/^uploads\/content\/[a-f0-9]{64}\.png$/D',
            $stored->relativePath(),
        );
        self::assertSame(320, $stored->width());
        self::assertSame(180, $stored->height());
        self::assertSame($request->sha256(), $stored->sha256());

        $destination = $this->directory . DIRECTORY_SEPARATOR . $stored->filename();
        self::assertFileExists($destination);
        self::assertSame(file_get_contents($this->source), file_get_contents($destination));

        $storage->remove($stored->relativePath());
        self::assertFileDoesNotExist($destination);
    }

    /** @return array<string, mixed> */
    private function policy(): array
    {
        return [
            'allowed_mime_extensions' => [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
            ],
            'maximum_file_size_bytes' => 5 * 1024 * 1024,
            'minimum_width_pixels' => 320,
            'minimum_height_pixels' => 180,
            'maximum_width_pixels' => 6000,
            'maximum_height_pixels' => 6000,
            'require_decodability' => true,
            'filesystem_root' => 'public/uploads/content',
            'database_path_prefix' => 'uploads/content/',
        ];
    }
}
