<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RoomImageException;
use App\Services\RoomImageService;
use App\Services\RoomImageServiceProvider;
use App\Services\PdoRoomCatalogTransaction;
use App\Repositories\RoomImageRepository;
use App\Repositories\RoomRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class RoomImageServiceProviderTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-image-provider-' . bin2hex(random_bytes(8));
        mkdir($this->basePath . DIRECTORY_SEPARATOR . 'config', 0700, true);
        mkdir($this->basePath . DIRECTORY_SEPARATOR . 'uploads', 0700);
        $this->writeConfiguration(10, 'uploads');
    }

    protected function tearDown(): void
    {
        @unlink($this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'config');
        @rmdir($this->basePath . DIRECTORY_SEPARATOR . 'uploads');
        @rmdir($this->basePath);
    }

    public function testItConstructsLazilyCachesAndPassesConfiguredMaximum(): void
    {
        $calls = 0;
        $connection = (new \ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
        $provider = new RoomImageServiceProvider(
            static function () use (&$calls, $connection): PDO {
                $calls++;
                return $connection;
            },
            $this->basePath,
        );

        self::assertSame(0, $calls);
        $service = $provider->service();
        self::assertInstanceOf(RoomImageService::class, $service);
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
        $property = new \ReflectionProperty(RoomImageService::class, 'maximumImages');
        self::assertSame(10, $property->getValue($service));
        self::assertSame('uploads', $provider->uploadConfiguration()['filesystem_root']);

        foreach ([
            ['service' => 'rooms', 'class' => RoomRepository::class],
            ['service' => 'images', 'class' => RoomImageRepository::class],
            ['service' => 'transaction', 'class' => PdoRoomCatalogTransaction::class],
        ] as $dependency) {
            $serviceProperty = new \ReflectionProperty(RoomImageService::class, $dependency['service']);
            $instance = $serviceProperty->getValue($service);
            self::assertInstanceOf($dependency['class'], $instance);
            $connectionProperty = new \ReflectionProperty($dependency['class'], 'connection');
            self::assertSame($connection, $connectionProperty->getValue($instance));
        }
    }

    public function testMalformedConfigurationAndStorageFailuresAreSanitized(): void
    {
        foreach ([
            ['maximum' => 0, 'root' => 'uploads'],
            ['maximum' => 10, 'root' => 'missing-sensitive-root'],
        ] as $case) {
            $this->writeConfiguration($case['maximum'], $case['root']);
            $provider = new RoomImageServiceProvider(
                static fn (): PDO => (new \ReflectionClass(PDO::class))->newInstanceWithoutConstructor(),
                $this->basePath,
            );

            try {
                $provider->service();
                self::fail('Expected provider failure.');
            } catch (RoomImageException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Room image operation failed.', $exception->getMessage());
                self::assertStringNotContainsString($case['root'], $exception->getMessage());
            }
        }
    }

    public function testMalformedPoliciesFailClosedWithoutLeakingValues(): void
    {
        $cases = [
            'mime-secret' => ['allowed_mime_extensions' => ['image/png' => []]],
            'size-secret' => ['maximum_file_size_bytes' => 0],
            'dimension-secret' => ['minimum_width_pixels' => 7000],
            'prefix-secret' => ['database_path_prefix' => 'private/secret/'],
        ];

        try {
            foreach ($cases as $secret => $overrides) {
                $this->writeConfiguration(10, 'uploads', $overrides);
                $provider = new RoomImageServiceProvider(
                    fn (): PDO => $this->pdo(),
                    $this->basePath,
                );

                try {
                    $provider->service();
                    self::fail('Expected malformed configuration failure.');
                } catch (RoomImageException $exception) {
                    self::assertTrue($exception->isSystemFailure());
                    self::assertSame('Room image operation failed.', $exception->getMessage());
                    self::assertStringNotContainsString($secret, $exception->getMessage());
                    self::assertStringNotContainsString($this->basePath, $exception->getMessage());
                }
            }
        } finally {
            $this->writeConfiguration(10, 'uploads');
        }
    }

    public function testConnectionFailureIsSanitizedAndDoesNotExposePhysicalRoot(): void
    {
        $provider = new RoomImageServiceProvider(
            static fn (): never => throw new \RuntimeException('raw connection secret'),
            $this->basePath,
        );

        try {
            $provider->service();
            self::fail('Expected connection failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room image operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('raw connection secret', $exception->getMessage());
            self::assertStringNotContainsString($this->basePath, $exception->getMessage());
        }
    }

    public function testAbsoluteRootIsAcceptedLazilyAndServiceRemainsCached(): void
    {
        $calls = 0;

        try {
            $this->writeConfiguration(10, realpath($this->basePath . DIRECTORY_SEPARATOR . 'uploads'));
            $provider = new RoomImageServiceProvider(
                function () use (&$calls): PDO {
                    $calls++;
                    return $this->pdo();
                },
                $this->basePath,
            );

            self::assertSame(0, $calls);
            $service = $provider->service();
            self::assertSame($service, $provider->service());
            self::assertSame(1, $calls);
        } finally {
            $this->writeConfiguration(10, 'uploads');
        }
    }

    /** @param array<string, mixed> $overrides */
    private function writeConfiguration(int $maximum, string $root, array $overrides = []): void
    {
        $roomImages = array_replace([
            'allowed_mime_extensions' => ['image/png' => ['png']],
            'maximum_file_size_bytes' => 5242880,
            'minimum_width_pixels' => 640,
            'minimum_height_pixels' => 480,
            'maximum_width_pixels' => 6000,
            'maximum_height_pixels' => 6000,
            'maximum_images_per_room' => $maximum,
            'filesystem_root' => $root,
            'database_path_prefix' => 'uploads/rooms/',
        ], $overrides);
        $configuration = "<?php\nreturn " . var_export(['room_images' => $roomImages], true) . ";\n";
        file_put_contents(
            $this->basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'upload.php',
            $configuration,
        );
    }

    private function pdo(): PDO
    {
        return (new \ReflectionClass(PDO::class))->newInstanceWithoutConstructor();
    }
}
