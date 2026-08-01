<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ReviewException;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Repositories\ReviewRepository;
use App\Services\PublicReviewService;
use App\Services\PublicReviewServiceProvider;
use App\Services\ReviewTokenHasher;
use App\Services\ReviewTransaction;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicReviewServiceProviderTest extends TestCase
{
    public function testItBuildsOnceAndCachesTheService(): void
    {
        $service = $this->service();
        $calls = 0;
        $provider = new PublicReviewServiceProvider(
            static function () use ($service, &$calls): PublicReviewService {
                $calls++;
                return $service;
            },
        );

        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryFailureIsSanitized(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static fn (): never => throw new RuntimeException('sensitive review provider failure'),
        ] as $factory) {
            try {
                (new PublicReviewServiceProvider($factory))->service();
                self::fail('An invalid public review service factory was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Review workflow operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    private function service(): PublicReviewService
    {
        return new PublicReviewService(
            $this->createStub(BookingCatalogRepository::class),
            $this->createStub(ReviewInvitationRepository::class),
            $this->createStub(ReviewRepository::class),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(ReviewTransaction::class),
            new ReviewTokenHasher(),
        );
    }
}
