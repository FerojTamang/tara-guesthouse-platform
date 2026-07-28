<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Core\UploadedFile;
use App\Storage\LocalPaymentProofStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalPaymentProofStorageTest extends TestCase
{
    /** @var list<string> */
    private array $cleanupPaths = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanupPaths) as $path) {
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }

        $this->cleanupPaths = [];
    }

    #[DataProvider('supportedMedia')]
    public function testItStoresPrivateBytesWithRandomCanonicalNames(
        string $mime,
        string $clientName,
        string $extension,
    ): void {
        [$root, $source, $bytes] = $this->fixture($clientName);
        $storage = new LocalPaymentProofStorage($root, 5_242_880);
        $stored = $storage->store($source, $mime);
        $destination = $root . DIRECTORY_SEPARATOR . $stored->filename();
        $this->cleanupPaths[] = $destination;

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}\.' . $extension . '$/D', $stored->filename());
        self::assertSame('payment-proofs/' . $stored->filename(), $stored->relativePath());
        self::assertSame($mime, $stored->mediaType());
        self::assertSame($extension, $stored->extension());
        self::assertSame(strlen($bytes), $stored->byteSize());
        self::assertSame($bytes, file_get_contents($destination));
        self::assertSame($bytes, file_get_contents($source->temporaryPath()));
        self::assertStringNotContainsString(pathinfo($clientName, PATHINFO_FILENAME), $stored->filename());

        if (DIRECTORY_SEPARATOR !== '\\') {
            self::assertSame(0600, fileperms($destination) & 0777);
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function supportedMedia(): iterable
    {
        yield 'jpeg canonicalized' => ['image/jpeg', 'guest.jpeg', 'jpg'];
        yield 'png double extension ignored' => ['image/png', 'attack.php.png', 'png'];
        yield 'pdf' => ['application/pdf', 'receipt.pdf', 'pdf'];
    }

    public function testItRetriesCollisionWithoutOverwritingExistingProof(): void
    {
        [$root, $source] = $this->fixture('proof.pdf');
        $collision = str_repeat("\x11", 32);
        $success = str_repeat("\x22", 32);
        $existing = $root . DIRECTORY_SEPARATOR . bin2hex($collision) . '.pdf';
        file_put_contents($existing, 'keep');
        $this->cleanupPaths[] = $existing;
        $calls = 0;
        $random = static function () use (&$calls, $collision, $success): string {
            return $calls++ === 0 ? $collision : $success;
        };
        $storage = new LocalPaymentProofStorage($root, 5_242_880, $random(...));
        $stored = $storage->store($source, 'application/pdf');
        $this->cleanupPaths[] = $root . DIRECTORY_SEPARATOR . $stored->filename();

        self::assertSame(2, $calls);
        self::assertSame('keep', file_get_contents($existing));
        self::assertSame(bin2hex($success) . '.pdf', $stored->filename());
    }

    public function testFailedWriteRemovesPartialDestinationAndPreservesSource(): void
    {
        [$root, $source, $bytes] = $this->fixture('proof.png');
        $random = static fn (): string => str_repeat("\x33", 32);
        $writer = static function ($input, $output): int {
            $chunk = fread($input, 2);
            self::assertIsString($chunk);
            self::assertSame(strlen($chunk), fwrite($output, $chunk));

            return strlen($chunk);
        };
        $storage = new LocalPaymentProofStorage($root, 5_242_880, $random(...), $writer(...));
        $destination = $root . DIRECTORY_SEPARATOR . str_repeat('33', 32) . '.png';

        try {
            $storage->store($source, 'image/png');
            self::fail('Expected storage failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Payment proof storage failed.', $exception->getMessage());
        }

        self::assertFileDoesNotExist($destination);
        self::assertSame($bytes, file_get_contents($source->temporaryPath()));
    }

    public function testItRejectsUnsupportedOversizedChangedAndLinkedSources(): void
    {
        [$root, $source] = $this->fixture('proof.pdf');
        $storage = new LocalPaymentProofStorage($root, $source->size() - 1);
        $this->assertStorageFailure(fn () => $storage->store($source, 'application/pdf'), $root);

        $storage = new LocalPaymentProofStorage($root, 5_242_880);
        $this->assertStorageFailure(fn () => $storage->store($source, 'text/plain'), $root);

        $changed = new UploadedFile(
            'proof.pdf',
            'application/pdf',
            $source->temporaryPath(),
            UPLOAD_ERR_OK,
            $source->size() + 1,
        );
        $this->assertStorageFailure(fn () => $storage->store($changed, 'application/pdf'), $root);

        $link = $source->temporaryPath() . '-link';

        if (@symlink($source->temporaryPath(), $link)) {
            $this->cleanupPaths[] = $link;
            $linked = new UploadedFile('proof.pdf', 'application/pdf', $link, UPLOAD_ERR_OK, $source->size());
            $this->assertStorageFailure(fn () => $storage->store($linked, 'application/pdf'), $root);
        }
    }

    #[DataProvider('removableExtensions')]
    public function testItRemovesCanonicalPathsAndTreatsAbsenceAsSuccess(string $extension): void
    {
        [$root] = $this->fixture('proof.pdf');
        $filename = str_repeat('a', 64) . '.' . $extension;
        $path = $root . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, 'stored');
        $this->cleanupPaths[] = $path;
        $storage = new LocalPaymentProofStorage($root, 5_242_880);

        $storage->remove('payment-proofs/' . $filename);
        self::assertFileDoesNotExist($path);
        $storage->remove('payment-proofs/' . $filename);
        self::assertFileDoesNotExist($path);
    }

    /** @return iterable<string, array{string}> */
    public static function removableExtensions(): iterable
    {
        yield 'jpeg' => ['jpg'];
        yield 'png' => ['png'];
        yield 'pdf' => ['pdf'];
    }

    public function testRemoveRejectsTraversalPublicAndExecutablePaths(): void
    {
        [$root] = $this->fixture('proof.pdf');
        $storage = new LocalPaymentProofStorage($root, 5_242_880);
        $paths = [
            'uploads/payments/' . str_repeat('a', 64) . '.pdf',
            '/payment-proofs/' . str_repeat('a', 64) . '.pdf',
            'payment-proofs/../' . str_repeat('a', 64) . '.pdf',
            'payment-proofs\\' . str_repeat('a', 64) . '.pdf',
            'payment-proofs//' . str_repeat('a', 64) . '.pdf',
            'payment-proofs/' . str_repeat('a', 64) . '.php',
            'payment-proofs/' . str_repeat('A', 64) . '.pdf',
            'payment-proofs/' . str_repeat('a', 32) . "\0" . str_repeat('a', 32) . '.pdf',
        ];

        foreach ($paths as $path) {
            try {
                $storage->remove($path);
                self::fail('Expected unsafe path rejection.');
            } catch (RuntimeException $exception) {
                self::assertSame('Payment proof removal failed.', $exception->getMessage());
                self::assertStringNotContainsString($root, $exception->getMessage());
                self::assertStringNotContainsString($path, $exception->getMessage());
            }
        }
    }

    public function testReplacedRootIsRejectedWithoutTouchingReplacementFiles(): void
    {
        [$root, $source] = $this->fixture('proof.pdf');
        $storage = new LocalPaymentProofStorage($root, 5_242_880);
        $moved = $root . '-moved';
        self::assertTrue(rename($root, $moved));
        $this->cleanupPaths[] = $moved;
        $this->cleanupPaths[] = $moved . DIRECTORY_SEPARATOR . 'validated-upload.tmp';
        self::assertTrue(mkdir($root, 0700));
        $replacement = $root . DIRECTORY_SEPARATOR . str_repeat('b', 64) . '.pdf';
        file_put_contents($replacement, 'keep');
        $this->cleanupPaths[] = $replacement;

        $this->assertStorageFailure(fn () => $storage->store($source, 'application/pdf'), $root);
        self::assertSame('keep', file_get_contents($replacement));
    }

    private function assertStorageFailure(callable $operation, string $secret): void
    {
        try {
            $operation();
            self::fail('Expected payment proof storage failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Payment proof storage failed.', $exception->getMessage());
            self::assertStringNotContainsString($secret, $exception->getMessage());
        }
    }

    /** @return array{string, UploadedFile, string} */
    private function fixture(string $clientName): array
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-private-proof-' . bin2hex(random_bytes(8));
        $root = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'payment-proofs';
        mkdir($root, 0700, true);
        $sourcePath = $base . DIRECTORY_SEPARATOR . 'validated-upload.tmp';
        $bytes = 'validated-proof-bytes-' . random_bytes(8);
        file_put_contents($sourcePath, $bytes);
        $this->cleanupPaths[] = $base;
        $this->cleanupPaths[] = dirname(dirname($root));
        $this->cleanupPaths[] = dirname($root);
        $this->cleanupPaths[] = $root;
        $this->cleanupPaths[] = $sourcePath;
        $source = new UploadedFile(
            $clientName,
            'application/client-claim',
            $sourcePath,
            UPLOAD_ERR_OK,
            strlen($bytes),
        );

        return [$root, $source, $bytes];
    }
}
