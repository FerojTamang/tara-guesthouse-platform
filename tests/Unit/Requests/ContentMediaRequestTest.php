<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\UploadedFile;
use App\Exceptions\ContentMediaException;
use App\Requests\ContentMediaMetadataRequest;
use App\Requests\ContentMediaUploadRequest;
use PHPUnit\Framework\TestCase;

final class ContentMediaRequestTest extends TestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAUAAAAC0CAIAAABqhmJGAAABkUlEQVR42u3TsQkAAAgEMXX/Yb+xdw4hGeHgOpsCfhoJwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGDAwGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBAQODgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwICB4bcD4XcEPQdXsdAAAAAASUVORK5CYII=';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-content-media-request-' . bin2hex(random_bytes(8));
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

    public function testMetadataIsTrimmedAndAlternativeTextIsRequired(): void
    {
        $metadata = ContentMediaMetadataRequest::fromInput(
            '  Garden view  ',
            '  Garden terrace with mountain view  ',
            '  Morning at Tara Guest House.  ',
        );

        self::assertSame('Garden view', $metadata->title());
        self::assertSame('Garden terrace with mountain view', $metadata->altText());
        self::assertSame('Morning at Tara Guest House.', $metadata->caption());

        try {
            ContentMediaMetadataRequest::fromInput('', '   ', '');
            self::fail('Expected alternative-text validation to fail.');
        } catch (ContentMediaException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Alternative text is invalid.', $exception->getMessage());
        }
    }

    public function testValidServerDetectedPngUploadIsAccepted(): void
    {
        $file = $this->pngUpload('garden.png');
        $request = ContentMediaUploadRequest::fromInput(
            $file,
            'Garden',
            'Garden terrace and surrounding hills',
            '',
            $this->policy(),
        );

        self::assertSame('image/png', $request->detectedMediaType());
        self::assertSame('png', $request->extension());
        self::assertSame(320, $request->width());
        self::assertSame(180, $request->height());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $request->sha256());
        self::assertSame('Garden', $request->metadata()->title());
    }

    public function testSubmittedExtensionMustMatchDetectedImageFormat(): void
    {
        try {
            ContentMediaUploadRequest::fromInput(
                $this->pngUpload('garden.jpg'),
                null,
                'Garden terrace',
                null,
                $this->policy(),
            );
            self::fail('Expected a format validation failure.');
        } catch (ContentMediaException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Content image format is invalid.', $exception->getMessage());
        }
    }

    private function pngUpload(string $name): UploadedFile
    {
        $bytes = base64_decode(self::PNG_BASE64, true);
        self::assertIsString($bytes);
        $path = $this->directory . DIRECTORY_SEPARATOR . bin2hex(random_bytes(4)) . '.tmp';
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));

        return new UploadedFile($name, 'client/value', $path, UPLOAD_ERR_OK, strlen($bytes));
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
