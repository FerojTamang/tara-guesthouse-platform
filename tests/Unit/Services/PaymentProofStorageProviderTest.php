<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PaymentProofException;
use App\Services\PaymentProofStorageProvider;
use App\Storage\LocalPaymentProofStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentProofStorageProviderTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-proof-provider-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . DIRECTORY_SEPARATOR . 'config', 0700, true);
        mkdir(
            $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private'
                . DIRECTORY_SEPARATOR . 'payment-proofs',
            0700,
            true,
        );
        $this->writeConfiguration();
    }

    protected function tearDown(): void
    {
        @unlink($this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'config');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'payment-proofs');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'storage');
        @rmdir($this->basePath);
    }

    public function testItLoadsCachesAndConstructsOnlyPrivateStorage(): void
    {
        $provider = new PaymentProofStorageProvider($this->basePath);
        $configuration = $provider->uploadConfiguration();
        $storage = $provider->storage();

        self::assertSame($configuration, $provider->uploadConfiguration());
        self::assertSame($storage, $provider->storage());
        self::assertSame($storage, $provider->reader());
        self::assertInstanceOf(LocalPaymentProofStorage::class, $storage);
        self::assertSame('storage/private/payment-proofs', $configuration['filesystem_root']);
        self::assertSame('payment-proofs/', $configuration['database_path_prefix']);
        self::assertSame(5_242_880, $configuration['maximum_file_size_bytes']);

        $root = (new \ReflectionProperty(LocalPaymentProofStorage::class, 'root'))->getValue($storage);
        self::assertStringStartsWith(realpath($this->basePath), $root);
        self::assertStringContainsString(
            DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'payment-proofs',
            $root,
        );
        self::assertStringNotContainsString(DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR, $root);
    }

    #[DataProvider('malformedConfigurations')]
    public function testMalformedConfigurationFailsClosedWithoutLeakingValues(array $overrides): void
    {
        $this->writeConfiguration($overrides);
        $provider = new PaymentProofStorageProvider($this->basePath);

        try {
            $provider->uploadConfiguration();
            self::fail('Expected provider configuration failure.');
        } catch (PaymentProofException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Payment proof operation failed.', $exception->getMessage());
            self::assertStringNotContainsString($this->basePath, $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function malformedConfigurations(): iterable
    {
        yield 'wrong mime list' => [[
            'allowed_mime_extensions' => ['application/pdf' => ['pdf', 'php-secret']],
        ]];
        yield 'wrong maximum' => [['maximum_file_size_bytes' => 5_242_879]];
        yield 'public root' => [['filesystem_root' => 'public/uploads/payments-secret']];
        yield 'absolute root' => [['filesystem_root' => '/tmp/private-secret']];
        yield 'wrong database prefix' => [['database_path_prefix' => '../secret/']];
        yield 'unknown policy key' => [['unknown_secret' => true]];
    }

    public function testMissingOrReplacedStorageRootIsSanitized(): void
    {
        $root = $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . 'payment-proofs';
        self::assertTrue(rmdir($root));
        $provider = new PaymentProofStorageProvider($this->basePath);

        try {
            $provider->storage();
            self::fail('Expected missing storage root failure.');
        } catch (PaymentProofException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Payment proof operation failed.', $exception->getMessage());
            self::assertStringNotContainsString($root, $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $overrides */
    private function writeConfiguration(array $overrides = []): void
    {
        $paymentProofs = array_replace([
            'allowed_mime_extensions' => [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'application/pdf' => ['pdf'],
            ],
            'maximum_file_size_bytes' => 5_242_880,
            'filesystem_root' => 'storage/private/payment-proofs',
            'database_path_prefix' => 'payment-proofs/',
        ], $overrides);
        $configuration = "<?php\nreturn " . var_export([
            'room_images' => [],
            'payment_proofs' => $paymentProofs,
        ], true) . ";\n";
        file_put_contents(
            $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php',
            $configuration,
        );
    }
}
