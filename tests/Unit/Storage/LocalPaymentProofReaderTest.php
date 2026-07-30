<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Storage\LocalPaymentProofStorage;
use App\Storage\RetrievedPaymentProof;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalPaymentProofReaderTest extends TestCase
{
    private string $base;
    private string $root;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-proof-reader-'
            . bin2hex(random_bytes(8));
        $this->root = $this->base
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'private'
            . DIRECTORY_SEPARATOR
            . 'payment-proofs';
        self::assertTrue(mkdir($this->root, 0700, true));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file) || is_link($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->root);
        @rmdir(dirname($this->root));
        @rmdir(dirname(dirname($this->root)));
        @rmdir($this->base);
    }

    public function testItReadsValidatedPrivateBytesWithoutExposingPath(): void
    {
        $filename = str_repeat('a', 64) . '.png';
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1EAAAAASUVORK5CYII=',
            true,
        );
        self::assertIsString($bytes);
        self::assertSame(strlen($bytes), file_put_contents($this->root . DIRECTORY_SEPARATOR . $filename, $bytes));
        $storage = new LocalPaymentProofStorage($this->root, 5_242_880);

        $proof = $storage->read('payment-proofs/' . $filename);

        self::assertInstanceOf(RetrievedPaymentProof::class, $proof);
        self::assertSame($bytes, $proof->bytes());
        self::assertSame('png', $proof->extension());
        self::assertSame('image/png', $proof->mediaType());
    }

    public function testMissingCanonicalProofReturnsNull(): void
    {
        $storage = new LocalPaymentProofStorage($this->root, 5_242_880);

        self::assertNull(
            $storage->read('payment-proofs/' . str_repeat('b', 64) . '.pdf'),
        );
    }

    public function testItRejectsTraversalExecutableAndMimeMismatchPaths(): void
    {
        $storage = new LocalPaymentProofStorage($this->root, 5_242_880);
        $paths = [
            '../' . str_repeat('a', 64) . '.pdf',
            'payment-proofs/../' . str_repeat('a', 64) . '.pdf',
            'payment-proofs\\' . str_repeat('a', 64) . '.pdf',
            'payment-proofs/' . str_repeat('a', 64) . '.php',
            'payment-proofs/' . str_repeat('A', 64) . '.pdf',
        ];

        foreach ($paths as $path) {
            $this->assertReadFailure($storage, $path);
        }

        $filename = str_repeat('c', 64) . '.pdf';
        file_put_contents($this->root . DIRECTORY_SEPARATOR . $filename, 'not a pdf');
        $this->assertReadFailure($storage, 'payment-proofs/' . $filename);
    }

    public function testLinkedProofIsRejected(): void
    {
        $target = $this->base . DIRECTORY_SEPARATOR . 'outside.pdf';
        file_put_contents($target, "%PDF-1.4\n%%EOF\n");
        $link = $this->root . DIRECTORY_SEPARATOR . str_repeat('d', 64) . '.pdf';

        if (!@symlink($target, $link)) {
            self::markTestSkipped('Symbolic links are unavailable.');
        }

        try {
            $storage = new LocalPaymentProofStorage($this->root, 5_242_880);
            $this->assertReadFailure(
                $storage,
                'payment-proofs/' . str_repeat('d', 64) . '.pdf',
            );
        } finally {
            @unlink($link);
            @unlink($target);
        }
    }

    private function assertReadFailure(
        LocalPaymentProofStorage $storage,
        string $path,
    ): void {
        try {
            $storage->read($path);
            self::fail('Expected payment proof read failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Payment proof read failed.', $exception->getMessage());
            self::assertStringNotContainsString($this->root, $exception->getMessage());
            self::assertStringNotContainsString($path, $exception->getMessage());
        }
    }
}
