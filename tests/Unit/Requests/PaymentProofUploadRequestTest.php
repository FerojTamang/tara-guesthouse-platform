<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Core\UploadedFile;
use App\Exceptions\PaymentProofException;
use App\Requests\PaymentProofUploadRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentProofUploadRequestTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    /** @var array<string, mixed> */
    private array $policy;

    protected function setUp(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $this->policy = $configuration['payment_proofs'];
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryPaths) as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            }
        }

        $this->temporaryPaths = [];
    }

    #[DataProvider('validProofs')]
    public function testItAcceptsApprovedContentAndIgnoresClientMediaType(
        string $name,
        string $bytes,
        string $mediaType,
        string $extension,
    ): void {
        $upload = $this->upload($name, $bytes, 'application/x-client-claim');
        $request = PaymentProofUploadRequest::fromInput($upload, $this->policy);

        self::assertSame($upload, $request->file());
        self::assertSame($mediaType, $request->detectedMediaType());
        self::assertSame($extension, $request->extension());
        self::assertSame(strlen($bytes), $request->byteSize());
        self::assertSame($this->policy['allowed_mime_extensions'], $request->allowedMimeExtensions());
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function validProofs(): iterable
    {
        yield 'jpeg jpg' => ['guest-proof.jpg', self::jpeg(), 'image/jpeg', 'jpg'];
        yield 'jpeg jpeg' => ['guest-proof.jpeg', self::jpeg(), 'image/jpeg', 'jpeg'];
        yield 'png double extension is harmless' => ['attack.php.png', self::png(), 'image/png', 'png'];
        yield 'pdf' => ['receipt.pdf', self::pdf(), 'application/pdf', 'pdf'];
    }

    public function testItRejectsMissingFileAndEveryPhpUploadFailure(): void
    {
        try {
            PaymentProofUploadRequest::fromInput(null, $this->policy);
            self::fail('Expected missing proof rejection.');
        } catch (PaymentProofException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Payment proof is required.', $exception->getMessage());
        }

        foreach ([
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE,
            UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_FILE,
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION,
        ] as $error) {
            $file = new UploadedFile('proof.pdf', 'application/pdf', '', $error, 0);

            try {
                PaymentProofUploadRequest::fromInput($file, $this->policy);
                self::fail('Expected upload error rejection.');
            } catch (PaymentProofException $exception) {
                self::assertTrue($exception->isValidationFailure());
                self::assertStringStartsWith('Payment proof', $exception->getMessage());
            }
        }
    }

    #[DataProvider('invalidFormatProofs')]
    public function testItRejectsExtensionMimeAndContentMismatches(string $name, string $bytes): void
    {
        try {
            PaymentProofUploadRequest::fromInput($this->upload($name, $bytes), $this->policy);
            self::fail('Expected invalid proof rejection.');
        } catch (PaymentProofException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringStartsWith('Payment proof', $exception->getMessage());
            self::assertStringNotContainsString($this->temporaryPaths[0], $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidFormatProofs(): iterable
    {
        yield 'unsupported gif extension' => ['proof.gif', "GIF89a\x01\x00\x01\x00"];
        yield 'jpeg bytes with png extension' => ['proof.png', self::jpeg()];
        yield 'png bytes with pdf extension' => ['proof.pdf', self::png()];
        yield 'text with allowed extension' => ['proof.pdf', 'not a real document'];
        yield 'truncated jpeg' => ['proof.jpg', substr(self::jpeg(), 0, -2)];
        yield 'truncated png' => ['proof.png', substr(self::png(), 0, -12)];
        yield 'pdf without eof' => ['proof.pdf', "%PDF-1.4\nobject only\n"];
        yield 'pdf with invalid version' => ['proof.pdf', "%PDF-9.9\n%%EOF\n"];
    }

    public function testItRejectsEmptyOversizedChangedAndSymbolicLinkSources(): void
    {
        $empty = $this->upload('proof.pdf', '');
        $this->assertValidationFailure($empty, 'Payment proof file is invalid.');

        $this->assertValidationFailure(
            $this->upload('proof.pdf', str_repeat('A', 5_242_881)),
            'Payment proof file is invalid.',
        );

        $path = $this->temporary(self::pdf());
        $size = filesize($path);
        self::assertIsInt($size);
        $changed = new UploadedFile('proof.pdf', 'application/pdf', $path, UPLOAD_ERR_OK, $size + 1);
        $this->assertValidationFailure($changed, 'Payment proof file is invalid.');

        $target = $this->temporary(self::pdf());
        $link = $target . '-link';

        if (@symlink($target, $link)) {
            $this->temporaryPaths[] = $link;
            $linked = new UploadedFile('proof.pdf', 'application/pdf', $link, UPLOAD_ERR_OK, filesize($target));
            $this->assertValidationFailure($linked, 'Payment proof file is invalid.');
        }
    }

    #[DataProvider('malformedPolicies')]
    public function testMalformedConfigurationFailsClosed(array $policy): void
    {
        try {
            PaymentProofUploadRequest::fromInput($this->upload('proof.pdf', self::pdf()), $policy);
            self::fail('Expected configuration failure.');
        } catch (PaymentProofException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Payment proof operation failed.', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function malformedPolicies(): iterable
    {
        yield 'missing allowed list' => [['maximum_file_size_bytes' => 5_242_880]];
        yield 'empty allowed list' => [[
            'allowed_mime_extensions' => [],
            'maximum_file_size_bytes' => 5_242_880,
        ]];
        yield 'zero maximum' => [[
            'allowed_mime_extensions' => ['application/pdf' => ['pdf']],
            'maximum_file_size_bytes' => 0,
        ]];
        yield 'empty extension list' => [[
            'allowed_mime_extensions' => ['application/pdf' => []],
            'maximum_file_size_bytes' => 5_242_880,
        ]];
        yield 'duplicate extension' => [[
            'allowed_mime_extensions' => ['application/pdf' => ['pdf', 'pdf']],
            'maximum_file_size_bytes' => 5_242_880,
        ]];
        yield 'executable extension' => [[
            'allowed_mime_extensions' => ['application/pdf' => ['php']],
            'maximum_file_size_bytes' => 5_242_880,
        ]];
    }

    private function assertValidationFailure(
        UploadedFile $file,
        string $message,
        ?array $policy = null,
    ): void {
        try {
            PaymentProofUploadRequest::fromInput($file, $policy ?? $this->policy);
            self::fail('Expected validation failure.');
        } catch (PaymentProofException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame($message, $exception->getMessage());
        }
    }

    private function upload(
        string $name,
        string $bytes,
        string $clientMediaType = 'application/octet-stream',
    ): UploadedFile {
        $path = $this->temporary($bytes);
        $size = filesize($path);
        self::assertIsInt($size);

        return new UploadedFile($name, $clientMediaType, $path, UPLOAD_ERR_OK, $size);
    }

    private function temporary(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tgh-payment-proof-request-');
        self::assertIsString($path);
        $this->temporaryPaths[] = $path;
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));

        return $path;
    }

    private static function png(): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data))
            . $type
            . $data
            . pack('N', crc32($type . $data));
        $row = "\0\0\0\0";
        $pixels = gzcompress($row, 9);
        self::assertIsString($pixels);

        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
            . $chunk('IDAT', $pixels)
            . $chunk('IEND', '');
    }

    private static function jpeg(): string
    {
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////'
            . '2wBDAf//////////////////////////////////////////////////////////////////////////////////////'
            . 'wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/'
            . '9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAA'
            . 'AAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAA'
            . 'AAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIA'
            . 'AwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ED//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oA'
            . 'CAECAQE/ED//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/ED//2Q==',
            true,
        );
        self::assertIsString($bytes);

        return $bytes;
    }

    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }
}
