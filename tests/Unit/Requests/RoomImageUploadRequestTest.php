<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\UploadedFile;
use App\Exceptions\RoomCatalogException;
use App\Requests\RoomImageUploadRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoomImageUploadRequestTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var array<string, mixed> */
    private array $policy;

    protected function setUp(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        self::assertIsArray($configuration);
        self::assertIsArray($configuration['room_images']);
        $this->policy = $configuration['room_images'];
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];
    }

    public function testItValidatesARealJpegUsingServerObservedProperties(): void
    {
        $file = $this->upload('nested/client/room.JPEG', $this->jpeg(), 'text/plain');
        $request = RoomImageUploadRequest::fromInput(12, $file, '  Garden room  ', '15', '1', $this->policy);

        self::assertSame(12, $request->roomId());
        self::assertSame('image/jpeg', $request->detectedMediaType());
        self::assertSame('jpeg', $request->extension());
        self::assertSame(640, $request->width());
        self::assertSame(480, $request->height());
        self::assertSame('Garden room', $request->alternativeText());
        self::assertSame(15, $request->sortOrder());
        self::assertTrue($request->isPrimary());
        self::assertSame('room.JPEG', $request->file()->originalName());
    }

    public function testItValidatesARealPng(): void
    {
        if (!$this->decoderAvailable('image/png')) {
            $this->expectDecoderUnavailable();
            $this->request($this->upload('room.png', $this->png(640, 480)), alternativeText: ' ');

            return;
        }

        $request = $this->request($this->upload('room.png', $this->png(640, 480)), alternativeText: ' ');

        self::assertSame('image/png', $request->detectedMediaType());
        self::assertSame('png', $request->extension());
        self::assertSame(640, $request->width());
        self::assertSame(480, $request->height());
        self::assertNull($request->alternativeText());
        self::assertNull($request->sortOrder());
        self::assertFalse($request->isPrimary());
    }

    public function testItValidatesAGenuineWebpWithoutSkippingRequiredCoverage(): void
    {
        $file = $this->upload('room.webp', $this->webp());
        $request = RoomImageUploadRequest::fromInput(1, $file, null, null, false, $this->policy);

        self::assertSame('image/webp', $request->detectedMediaType());
        self::assertSame(640, $request->width());
        self::assertSame(480, $request->height());
    }

    public function testItRejectsAMissingUpload(): void
    {
        $this->expectValidationFailure();
        RoomImageUploadRequest::fromInput(1, null, null, null, false, $this->policy);
    }

    #[DataProvider('uploadErrorProvider')]
    public function testItRejectsEveryRelevantPhpUploadError(int $error): void
    {
        $this->expectValidationFailure();
        $file = new UploadedFile($error === UPLOAD_ERR_NO_FILE ? '' : 'room.png', '', '', $error, 0);
        RoomImageUploadRequest::fromInput(1, $file, null, null, false, $this->policy);
    }

    /** @return iterable<string, array{int}> */
    public static function uploadErrorProvider(): iterable
    {
        yield 'php ini size' => [UPLOAD_ERR_INI_SIZE];
        yield 'form size' => [UPLOAD_ERR_FORM_SIZE];
        yield 'partial' => [UPLOAD_ERR_PARTIAL];
        yield 'no file' => [UPLOAD_ERR_NO_FILE];
        yield 'no temporary directory' => [UPLOAD_ERR_NO_TMP_DIR];
        yield 'cannot write' => [UPLOAD_ERR_CANT_WRITE];
        yield 'extension stopped upload' => [UPLOAD_ERR_EXTENSION];
    }

    public function testItRejectsAnEmptyFile(): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload('room.png', ''));
    }

    public function testItRejectsAnOversizedFileBeforeImageInspection(): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload('room.png', str_repeat('x', (5 * 1024 * 1024) + 1)));
    }

    public function testItRejectsAValidMimeWithAnUnsupportedExtension(): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload('room.txt', $this->png(640, 480)));
    }

    public function testItRejectsAnUnsupportedMimeWithASupportedExtension(): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload('room.png', $this->gif()));
    }

    #[DataProvider('unsupportedImageProvider')]
    public function testItRejectsExplicitlyUnsupportedImageFormats(string $name, string $contents): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload($name, $contents));
    }

    /** @return iterable<string, array{string, string}> */
    public static function unsupportedImageProvider(): iterable
    {
        yield 'BMP' => ['room.bmp', "BM\x3a\0\0\0\0\0\0\0\x36\0\0\0"];
        yield 'TIFF' => ['room.tiff', "II\x2a\0\x08\0\0\0"];
        yield 'SVG' => ['room.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="480"/>'];
        yield 'PHP executable' => ['room.php', '<?php echo "unsafe";'];
    }

    public function testItRejectsAMimeExtensionMismatch(): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload('room.jpg', $this->png(640, 480)));
    }

    public function testItRejectsMalformedImageContent(): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload('room.png', "\x89PNG\r\n\x1a\nnot-an-image"));
    }

    public function testItRejectsAMissingTemporaryFile(): void
    {
        $this->expectValidationFailure();
        $file = new UploadedFile('room.png', 'image/png', __DIR__ . '/missing-room-image.tmp', UPLOAD_ERR_OK, 100);
        $this->request($file);
    }

    public function testItRejectsADeclaredSizeDifferentFromTheActualFileSize(): void
    {
        $this->expectValidationFailure();
        $path = $this->temporary($this->png(640, 480));
        $actualSize = filesize($path);
        self::assertIsInt($actualSize);
        $this->request(new UploadedFile('room.png', 'image/png', $path, UPLOAD_ERR_OK, $actualSize + 1));
    }

    public function testItRejectsAnUnreadableTemporaryPathShape(): void
    {
        $this->expectValidationFailure();
        $file = new UploadedFile('room.png', 'image/png', __DIR__, UPLOAD_ERR_OK, 100);
        $this->request($file);
    }

    public function testItRejectsATruncatedJpeg(): void
    {
        $this->expectValidationFailure();
        $bytes = $this->jpeg();
        $scanMarker = strpos($bytes, "\xFF\xDA");
        self::assertIsInt($scanMarker);
        $policy = $this->policyWithMinimumDimensions(1, 1);
        RoomImageUploadRequest::fromInput(
            1,
            $this->upload('room.jpg', substr($bytes, 0, $scanMarker + 4)),
            null,
            null,
            false,
            $policy,
        );
    }

    public function testItRejectsATruncatedPng(): void
    {
        $this->expectValidationFailure();
        $bytes = $this->png(640, 480);
        $this->request($this->upload('room.png', substr($bytes, 0, -12)));
    }

    public function testItRejectsAHeaderOnlyWebp(): void
    {
        $this->expectValidationFailure();
        $policy = $this->policyWithMinimumDimensions(1, 1);
        RoomImageUploadRequest::fromInput(
            1,
            $this->upload('room.webp', $this->headerOnlyWebp(1, 1)),
            null,
            null,
            false,
            $policy,
        );
    }

    public function testItRejectsMalformedContentThatPassesSuperficialImageInspection(): void
    {
        $file = $this->upload('room.png', $this->png(640, 480));
        $path = $file->temporaryPath();
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        self::assertGreaterThan(40, strlen($contents));
        $contents = substr_replace($contents, str_repeat("\xff", 16), 33, 16);
        self::assertSame(strlen($contents), file_put_contents($path, $contents));

        $superficial = @getimagesize($path);
        self::assertIsArray($superficial);
        $this->expectValidationFailure();
        $this->request(new UploadedFile('room.png', 'image/png', $path, UPLOAD_ERR_OK, strlen($contents)));
    }

    #[DataProvider('invalidDimensionProvider')]
    public function testItRejectsInvalidDimensions(int $width, int $height): void
    {
        $this->expectValidationFailure();
        $this->request($this->upload('room.png', $this->png($width, $height)));
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidDimensionProvider(): iterable
    {
        yield 'width below minimum' => [639, 480];
        yield 'height below minimum' => [640, 479];
        yield 'width above maximum' => [6001, 480];
        yield 'height above maximum' => [640, 6001];
    }

    public function testItRejectsAlternativeTextAboveTheLimitAndMarkup(): void
    {
        foreach ([str_repeat('a', 256), '<strong>Room</strong>'] as $alternativeText) {
            try {
                $this->request($this->upload('room.png', $this->png(640, 480)), alternativeText: $alternativeText);
                self::fail('Invalid alternative text was accepted.');
            } catch (RoomCatalogException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    #[DataProvider('sortOrderProvider')]
    public function testItValidatesSortOrder(mixed $value, ?int $expected, bool $valid): void
    {
        $file = $this->upload('room.png', $this->png(640, 480));

        if (!$valid) {
            $this->expectValidationFailure();
        } elseif (!$this->decoderAvailable('image/png')) {
            $this->expectDecoderUnavailable();
            $this->request($file, sortOrder: $value);

            return;
        }

        $request = $this->request($file, sortOrder: $value);

        if ($valid) {
            self::assertSame($expected, $request->sortOrder());
        }
    }

    /** @return iterable<string, array{mixed, ?int, bool}> */
    public static function sortOrderProvider(): iterable
    {
        yield 'omitted' => [null, null, true];
        yield 'empty' => ['', null, true];
        yield 'zero' => ['0', 0, true];
        yield 'maximum' => [65535, 65535, true];
        yield 'negative' => [-1, null, false];
        yield 'above maximum' => [65536, null, false];
        yield 'decimal' => ['1.5', null, false];
        yield 'array' => [[], null, false];
    }

    #[DataProvider('primaryProvider')]
    public function testItAcceptsOnlyExplicitPrimaryValues(mixed $value, ?bool $expected): void
    {
        $file = $this->upload('room.png', $this->png(640, 480));

        if ($expected === null) {
            $this->expectValidationFailure();
        } elseif (!$this->decoderAvailable('image/png')) {
            $this->expectDecoderUnavailable();
            $this->request($file, primary: $value);

            return;
        }

        $request = $this->request($file, primary: $value);

        if ($expected !== null) {
            self::assertSame($expected, $request->isPrimary());
        }
    }

    /** @return iterable<string, array{mixed, ?bool}> */
    public static function primaryProvider(): iterable
    {
        yield 'boolean true' => [true, true];
        yield 'boolean false' => [false, false];
        yield 'integer one' => [1, true];
        yield 'integer zero' => [0, false];
        yield 'string one' => ['1', true];
        yield 'string zero' => ['0', false];
        yield 'word true is ambiguous' => ['true', null];
        yield 'empty is ambiguous' => ['', null];
        yield 'two is ambiguous' => [2, null];
        yield 'array is invalid' => [[], null];
    }

    #[DataProvider('identifierProvider')]
    public function testItValidatesRoomAndImageIdentifiers(mixed $value, bool $valid): void
    {
        foreach (['Room', 'Image'] as $label) {
            try {
                $identifier = RoomImageUploadRequest::identifier($value, $label);

                if (!$valid) {
                    self::fail('Invalid identifier was accepted.');
                }

                self::assertGreaterThan(0, $identifier);
            } catch (RoomCatalogException $exception) {
                if ($valid) {
                    throw $exception;
                }

                self::assertTrue($exception->isValidationFailure());
            }
        }
    }

    /** @return iterable<string, array{mixed, bool}> */
    public static function identifierProvider(): iterable
    {
        yield 'integer' => [1, true];
        yield 'numeric string' => ['42', true];
        yield 'leading zero' => ['0007', true];
        yield 'zero' => [0, false];
        yield 'negative' => [-1, false];
        yield 'malformed' => ['1e2', false];
        yield 'array' => [[], false];
        yield 'object' => [new \stdClass(), false];
        yield 'overflow' => [(string) PHP_INT_MAX . '0', false];
    }

    private function request(
        UploadedFile $file,
        mixed $alternativeText = null,
        mixed $sortOrder = null,
        mixed $primary = false,
    ): RoomImageUploadRequest {
        return RoomImageUploadRequest::fromInput(
            1,
            $file,
            $alternativeText,
            $sortOrder,
            $primary,
            $this->policy,
        );
    }

    private function upload(string $name, string $bytes, string $clientMediaType = 'image/client-claim'): UploadedFile
    {
        return $this->uploadFromPath($name, $this->temporary($bytes), $clientMediaType);
    }

    private function uploadFromPath(
        string $name,
        string $path,
        string $clientMediaType = 'image/client-claim',
    ): UploadedFile {
        $size = filesize($path);
        self::assertIsInt($size);

        return new UploadedFile($name, $clientMediaType, $path, UPLOAD_ERR_OK, $size);
    }

    private function temporary(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tara-room-image-test-');
        self::assertIsString($path);
        $this->temporaryFiles[] = $path;
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));

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

    private function jpeg(): string
    {
        return $this->encodedGdImage('jpeg');
    }

    private function webp(): string
    {
        return $this->encodedGdImage('webp');
    }

    private function headerOnlyWebp(int $width, int $height): string
    {
        $canvas = substr(pack('V', $width - 1), 0, 3) . substr(pack('V', $height - 1), 0, 3);
        $chunk = 'VP8X' . pack('V', 10) . "\0\0\0\0" . $canvas;

        return 'RIFF' . pack('V', 4 + strlen($chunk)) . 'WEBP' . $chunk;
    }

    private function gif(): string
    {
        return "GIF89a\x80\x02\xe0\x01\x80\0\0\0\0\0\xff\xff\xff\x2c\0\0\0\0\x80\x02\xe0\x01\0\x02\x02D\x01\0\x3b";
    }

    /** @return array<string, mixed> */
    private function policyWithMinimumDimensions(int $width, int $height): array
    {
        $policy = $this->policy;
        $policy['minimum_width_pixels'] = $width;
        $policy['minimum_height_pixels'] = $height;

        return $policy;
    }

    private function decoderAvailable(string $mediaType): bool
    {
        if (
            !function_exists('imagecreatefromstring')
            || !function_exists('imagedestroy')
            || !function_exists('imagetypes')
        ) {
            return false;
        }

        $requiredType = match ($mediaType) {
            'image/jpeg' => IMG_JPG,
            'image/png' => IMG_PNG,
            'image/webp' => IMG_WEBP,
            default => 0,
        };

        return $requiredType !== 0 && (imagetypes() & $requiredType) === $requiredType;
    }

    private function expectDecoderUnavailable(): void
    {
        $this->expectException(RoomCatalogException::class);
        $this->expectExceptionMessage('Room image decoding is unavailable.');
    }

    private function encodedGdImage(string $format): string
    {
        self::assertTrue(function_exists('imagecreatetruecolor'), 'GD image creation support is required.');
        self::assertTrue(function_exists('imagecolorallocate'), 'GD colour support is required.');
        self::assertTrue(function_exists('imagefilledrectangle'), 'GD drawing support is required.');
        self::assertTrue(function_exists('imageline'), 'GD drawing support is required.');

        $image = imagecreatetruecolor(640, 480);
        self::assertInstanceOf(\GdImage::class, $image);
        $bufferLevel = ob_get_level();

        try {
            $background = imagecolorallocate($image, 32, 82, 120);
            $accent = imagecolorallocate($image, 244, 184, 96);
            self::assertNotFalse($background);
            self::assertNotFalse($accent);
            self::assertTrue(imagefilledrectangle($image, 0, 0, 639, 479, $background));
            self::assertTrue(imageline($image, 0, 0, 639, 479, $accent));
            self::assertTrue(imageline($image, 639, 0, 0, 479, $accent));
            self::assertTrue(ob_start());

            $encoded = match ($format) {
                'jpeg' => imagejpeg($image, null, 90),
                'webp' => imagewebp($image, null, 90),
                default => false,
            };
            $contents = ob_get_contents();
            self::assertTrue($encoded, strtoupper($format) . ' encoding support is required.');
            self::assertIsString($contents);
            self::assertNotSame('', $contents);

            return $contents;
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            imagedestroy($image);
        }
    }

    private function expectValidationFailure(): void
    {
        $this->expectException(RoomCatalogException::class);
    }
}
