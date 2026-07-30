<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ActivityException;
use App\Repositories\ActivityCatalogRepository;
use App\Services\PublicActivityService;
use App\Services\PublicActivityServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicActivityServiceProviderTest extends TestCase
{
    public function testItBuildsTheServiceLazilyAndCachesIt(): void
    {
        $calls = 0;
        $repository = $this->createStub(ActivityCatalogRepository::class);
        $provider = new PublicActivityServiceProvider(
            static function () use (&$calls, $repository): PublicActivityService {
                ++$calls;

                return new PublicActivityService($repository);
            },
        );

        self::assertSame(0, $calls);
        $first = $provider->service();
        $second = $provider->service();

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
    }

    public function testInvalidFactoryResultFailsSafely(): void
    {
        $provider = new PublicActivityServiceProvider(
            static fn (): object => new \stdClass(),
        );

        $this->expectException(ActivityException::class);
        $this->expectExceptionMessage('Activity catalogue operation failed.');
        $provider->service();
    }

    public function testUnexpectedFactoryFailureIsSanitized(): void
    {
        $provider = new PublicActivityServiceProvider(
            static fn (): never => throw new RuntimeException('private factory details'),
        );

        try {
            $provider->service();
            self::fail('Expected a safe public activity provider failure.');
        } catch (ActivityException $exception) {
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }
    }
}
