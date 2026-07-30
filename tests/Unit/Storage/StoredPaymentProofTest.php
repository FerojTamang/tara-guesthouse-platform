<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Storage\StoredPaymentProof;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StoredPaymentProofTest extends TestCase
{
    private const BASENAME = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testItExposesValidImmutablePrivateMetadata(): void
    {
        $proof = new StoredPaymentProof(
            'payment-proofs/' . self::BASENAME . '.pdf',
            self::BASENAME . '.pdf',
            'pdf',
            'application/pdf',
            1200,
        );

        self::assertSame('payment-proofs/' . self::BASENAME . '.pdf', $proof->relativePath());
        self::assertSame(self::BASENAME . '.pdf', $proof->filename());
        self::assertSame('pdf', $proof->extension());
        self::assertSame('application/pdf', $proof->mediaType());
        self::assertSame(1200, $proof->byteSize());
    }

    #[DataProvider('invalidMetadata')]
    public function testItRejectsUnsafeOrInconsistentMetadata(
        string $path,
        string $filename,
        string $extension,
        string $mediaType,
        int $size,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stored payment proof data is invalid.');

        new StoredPaymentProof($path, $filename, $extension, $mediaType, $size);
    }

    /** @return iterable<string, array{string, string, string, string, int}> */
    public static function invalidMetadata(): iterable
    {
        $name = self::BASENAME . '.png';

        yield 'public path' => ['uploads/payments/' . $name, $name, 'png', 'image/png', 1];
        yield 'absolute path' => ['/payment-proofs/' . $name, $name, 'png', 'image/png', 1];
        yield 'traversal' => ['payment-proofs/../' . $name, $name, 'png', 'image/png', 1];
        yield 'backslash' => ['payment-proofs/' . $name, 'nested\\' . $name, 'png', 'image/png', 1];
        yield 'short name' => ['payment-proofs/abcd.png', 'abcd.png', 'png', 'image/png', 1];
        yield 'uppercase name' => [
            'payment-proofs/' . strtoupper(self::BASENAME) . '.png',
            strtoupper(self::BASENAME) . '.png',
            'png',
            'image/png',
            1,
        ];
        yield 'double extension' => [
            'payment-proofs/' . self::BASENAME . '.php.png',
            self::BASENAME . '.php.png',
            'png',
            'image/png',
            1,
        ];
        yield 'unsupported extension' => [
            'payment-proofs/' . self::BASENAME . '.php',
            self::BASENAME . '.php',
            'php',
            'application/x-httpd-php',
            1,
        ];
        yield 'media mismatch' => [
            'payment-proofs/' . self::BASENAME . '.pdf',
            self::BASENAME . '.pdf',
            'pdf',
            'image/png',
            1,
        ];
        yield 'zero size' => ['payment-proofs/' . $name, $name, 'png', 'image/png', 0];
    }
}
