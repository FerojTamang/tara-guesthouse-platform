<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\UploadedFile;
use App\Exceptions\GalleryImageException;
use App\Requests\GalleryImageUploadRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GalleryImageUploadRequestTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }

        $this->temporaryPaths = [];
    }

    public function testItUsesServerObservedPngPropertiesAndIgnoresClientMime(): void
    {
        $file = $this->upload('nested/client/gallery.PNG', $this->png(32, 24), 'text/plain');

        if (!$this->decoderAvailable('image/png')) {
            $this->expectException(GalleryImageException::class);
            $this->expectExceptionMessage('Gallery image decoding is unavailable.');
        }

        $request = GalleryImageUploadRequest::fromInput($file, $this->policy());

        self::assertSame('gallery.PNG', $request->file()->originalName());
        self::assertSame('image/png', $request->detectedMediaType());
        self::assertSame('png', $request->extension());
        self::assertSame(32, $request->width());
        self::assertSame(24, $request->height());
        self::assertSame(hash_file('sha256', $file->temporaryPath()), $request->sha256());
    }

    public function testItValidatesAnApprovedJpegExtensionPair(): void
    {
        if (!$this->decoderAvailable('image/jpeg')) {
            $this->expectException(GalleryImageException::class);
            $this->expectExceptionMessage('Gallery image decoding is unavailable.');
        }

        $request = GalleryImageUploadRequest::fromInput(
            $this->upload('gallery.JPEG', $this->encodedGdImage('jpeg')),
            $this->policy(),
        );

        self::assertSame('image/jpeg', $request->detectedMediaType());
        self::assertSame('jpeg', $request->extension());
    }

    public function testItRejectsMissingAndEmptyUploads(): void
    {
        foreach ([null, new UploadedFile('', '', '', UPLOAD_ERR_NO_FILE, 0)] as $file) {
            try {
                GalleryImageUploadRequest::fromInput($file, $this->policy());
                self::fail('Missing gallery image was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }

        $this->expectValidationFailure();
        GalleryImageUploadRequest::fromInput($this->upload('gallery.png', ''), $this->policy());
    }

    #[DataProvider('uploadErrors')]
    public function testItRejectsPhpUploadErrors(int $error): void
    {
        $this->expectValidationFailure();
        GalleryImageUploadRequest::fromInput(
            new UploadedFile($error === UPLOAD_ERR_NO_FILE ? '' : 'gallery.png', '', '', $error, 0),
            $this->policy(),
        );
    }

    /** @return iterable<string, array{int}> */
    public static function uploadErrors(): iterable
    {
        yield 'ini limit' => [UPLOAD_ERR_INI_SIZE];
        yield 'form limit' => [UPLOAD_ERR_FORM_SIZE];
        yield 'partial' => [UPLOAD_ERR_PARTIAL];
        yield 'no file' => [UPLOAD_ERR_NO_FILE];
        yield 'no temporary directory' => [UPLOAD_ERR_NO_TMP_DIR];
        yield 'cannot write' => [UPLOAD_ERR_CANT_WRITE];
        yield 'extension stopped upload' => [UPLOAD_ERR_EXTENSION];
    }

    public function testItRejectsOversizedAndDeclaredSizeMismatch(): void
    {
        $bytes = $this->png(32, 24);
        $path = $this->temporary($bytes);
        $oversizedPolicy = $this->policy();
        $oversizedPolicy['maximum_file_size_bytes'] = strlen($bytes) - 1;

        foreach ([
            [$this->fromPath('gallery.png', $path), $oversizedPolicy],
            [new UploadedFile('gallery.png', '', $path, UPLOAD_ERR_OK, strlen($bytes) + 1), $this->policy()],
        ] as [$file, $policy]) {
            try {
                GalleryImageUploadRequest::fromInput($file, $policy);
                self::fail('Invalid gallery image size was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    public function testItRejectsMimeExtensionMismatchUnsupportedAndExecutableContent(): void
    {
        foreach ([
            ['gallery.jpg', $this->png(32, 24)],
            ['gallery.gif', $this->gif()],
            ['gallery.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'],
            ['gallery.php', '<?php echo "unsafe";'],
            ['gallery.png', "\x89PNG\r\n\x1a\nnot-an-image"],
        ] as [$name, $bytes]) {
            try {
                GalleryImageUploadRequest::fromInput($this->upload($name, $bytes), $this->policy());
                self::fail('Unsafe gallery image was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    public function testItRejectsDimensionsOutsideTheConfiguredGate(): void
    {
        foreach ([[15, 24], [32, 11], [101, 24], [32, 101]] as [$width, $height]) {
            try {
                GalleryImageUploadRequest::fromInput(
                    $this->upload('gallery.png', $this->png($width, $height)),
                    $this->policy(),
                );
                self::fail('Out-of-policy dimensions were accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    public function testItRejectsSymlinkTemporaryFilesWhenSupported(): void
    {
        $target = $this->temporary($this->png(32, 24));
        $link = $target . '.link';

        if (!@symlink($target, $link)) {
            self::markTestSkipped('Current Windows/filesystem permissions do not permit symlink creation.');
        }

        $this->temporaryPaths[] = $link;
        $size = filesize($target);
        self::assertIsInt($size);
        $this->expectValidationFailure();
        GalleryImageUploadRequest::fromInput(
            new UploadedFile('gallery.png', '', $link, UPLOAD_ERR_OK, $size),
            $this->policy(),
        );
    }

    public function testMalformedOrUnapprovedConfigurationFailsClosed(): void
    {
        $cases = [
            ['allowed_mime_extensions' => []],
            ['allowed_mime_extensions' => ['image/gif' => ['gif']]],
            ['allowed_mime_extensions' => ['image/png' => ['PNG']]],
            ['maximum_file_size_bytes' => 0],
            ['minimum_width_pixels' => 0],
            ['maximum_width_pixels' => 1],
            ['require_decodability' => false],
        ];

        foreach ($cases as $override) {
            try {
                GalleryImageUploadRequest::fromInput(
                    $this->upload('gallery.png', $this->png(32, 24)),
                    array_replace($this->policy(), $override),
                );
                self::fail('Unapproved gallery policy was accepted.');
            } catch (GalleryImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Gallery image operation failed.', $exception->getMessage());
            }
        }
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
            'maximum_file_size_bytes' => 1_000_000,
            'minimum_width_pixels' => 16,
            'minimum_height_pixels' => 12,
            'maximum_width_pixels' => 100,
            'maximum_height_pixels' => 100,
            'require_decodability' => true,
        ];
    }

    private function upload(string $name, string $bytes, string $clientType = 'client/claim'): UploadedFile
    {
        return $this->fromPath($name, $this->temporary($bytes), $clientType);
    }

    private function fromPath(string $name, string $path, string $clientType = 'client/claim'): UploadedFile
    {
        $size = filesize($path);
        self::assertIsInt($size);

        return new UploadedFile($name, $clientType, $path, UPLOAD_ERR_OK, $size);
    }

    private function temporary(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tgh-gallery-upload-');
        self::assertIsString($path);
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private function png(int $width, int $height): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $row = "\0" . str_repeat("\0", $width * 3);
        $pixels = gzcompress(str_repeat($row, $height), 9);
        self::assertIsString($pixels);

        return $signature
            . $this->pngChunk('IHDR', $ihdr)
            . $this->pngChunk('IDAT', $pixels)
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function gif(): string
    {
        return "GIF89a\x20\0\x18\0\x80\0\0\0\0\0\xff\xff\xff\x2c\0\0\0\0\x20\0\x18\0\0\x02\x02D\x01\0\x3b";
    }

    private function encodedGdImage(string $format): string
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'GD image creation support is required.');
        $image = imagecreatetruecolor(32, 24);
        self::assertInstanceOf(\GdImage::class, $image);
        $level = ob_get_level();

        try {
            self::assertTrue(ob_start());
            $encoded = match ($format) {
                'jpeg' => imagejpeg($image, null, 90),
                default => false,
            };
            $contents = ob_get_contents();
            self::assertTrue($encoded);
            self::assertIsString($contents);

            return $contents;
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            imagedestroy($image);
        }
    }

    private function decoderAvailable(string $mediaType): bool
    {
        if (!function_exists('imagetypes')) {
            return false;
        }

        $type = match ($mediaType) {
            'image/jpeg' => IMG_JPG,
            'image/png' => IMG_PNG,
            default => 0,
        };

        return $type !== 0 && (imagetypes() & $type) === $type;
    }

    private function expectValidationFailure(): void
    {
        $this->expectException(GalleryImageException::class);
    }
}
