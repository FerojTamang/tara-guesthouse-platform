<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ReviewException;
use App\Models\ReviewInvitationPolicy;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Repositories\ReviewRepository;
use App\Services\ReviewInvitationService;
use App\Services\ReviewInvitationServiceProvider;
use App\Services\ReviewTokenHasher;
use App\Services\ReviewTransaction;
use PHPUnit\Framework\TestCase;

final class ReviewInvitationServiceProviderTest extends TestCase
{
    public function testProviderBuildsOnceAndCaches(): void
    {
        $service = new ReviewInvitationService(
            $this->createStub(BookingCatalogRepository::class),
            $this->createStub(ReviewInvitationRepository::class),
            $this->createStub(ReviewRepository::class),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(ReviewTransaction::class),
            new ReviewTokenHasher(static fn (int $length): string => str_repeat("\x01", $length)),
            new ReviewInvitationPolicy(3600),
        );
        $calls = 0;
        $provider = new ReviewInvitationServiceProvider(function () use (&$calls, $service): ReviewInvitationService {
            $calls++;
            return $service;
        });

        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryResultFailsSafely(): void
    {
        $provider = new ReviewInvitationServiceProvider(static fn (): object => new \stdClass());

        try {
            $provider->service();
            self::fail('An invalid review service was accepted.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
        }
    }
}
