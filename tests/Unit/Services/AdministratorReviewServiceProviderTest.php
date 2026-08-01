<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ReviewException;
use App\Repositories\AdministratorReviewRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Services\AdministratorReviewService;
use App\Services\AdministratorReviewServiceProvider;
use App\Services\ReviewTransaction;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorReviewServiceProviderTest extends TestCase
{
    public function testValidServiceIsLoadedOnceAndCached(): void
    {
        $calls = 0;
        $service = $this->service();
        $provider = new AdministratorReviewServiceProvider(
            static function () use (&$calls, $service): AdministratorReviewService {
                ++$calls;
                return $service;
            },
        );

        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testWrongFactoryTypeAndFailureAreSanitized(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static fn (): never => throw new RuntimeException('sensitive moderation provider failure'),
            static fn (): never => throw ReviewException::failure(
                new RuntimeException('sensitive chained moderation provider failure'),
            ),
        ] as $factory) {
            $provider = new AdministratorReviewServiceProvider($factory);

            try {
                $provider->service();
                self::fail('Invalid Administrator review service factory was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Review workflow operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    private function service(): AdministratorReviewService
    {
        return new AdministratorReviewService(
            $this->createStub(AdministratorReviewRepository::class),
            $this->createStub(BookingCatalogRepository::class),
            $this->createStub(ReviewInvitationRepository::class),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(ReviewTransaction::class),
        );
    }
}
