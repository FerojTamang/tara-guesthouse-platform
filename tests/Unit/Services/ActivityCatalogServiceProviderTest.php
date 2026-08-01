<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ActivityException;
use App\Repositories\ActivityCatalogRepository;
use App\Services\ActivityCatalogService;
use App\Services\ActivityCatalogServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ActivityCatalogServiceProviderTest extends TestCase
{
    public function testItBuildsAndCachesOneService(): void
    {
        $repository = $this->createStub(ActivityCatalogRepository::class);
        $calls = 0;
        $provider = new ActivityCatalogServiceProvider(
            static function () use (&$calls, $repository): ActivityCatalogService {
                ++$calls;

                return new ActivityCatalogService($repository);
            },
        );

        self::assertSame($provider->service(), $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryResultFailsSafely(): void
    {
        $provider = new ActivityCatalogServiceProvider(static fn (): object => new \stdClass());

        $this->expectException(ActivityException::class);
        $this->expectExceptionMessage('Activity catalogue operation failed.');
        $provider->service();
    }

    public function testUnexpectedFactoryFailureIsSanitized(): void
    {
        $provider = new ActivityCatalogServiceProvider(
            static fn (): never => throw new RuntimeException('private factory details'),
        );

        try {
            $provider->service();
            self::fail('Expected a safe activity provider failure.');
        } catch (ActivityException $exception) {
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('private factory details', $exception->getMessage());
        }
    }
}
