<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\IssuedReviewInvitation;
use App\Models\ReviewInvitation;
use App\Services\ReviewCheckoutInvitationCoordinator;
use App\Services\ReviewInvitationDelivery;
use App\Services\ReviewInvitationServiceProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ReviewCheckoutInvitationCoordinatorTest extends TestCase
{
    public function testDeliveryFailureIsLoggedWithoutRawTokenOrPropagation(): void
    {
        $issued = self::issuedInvitation();
        $delivery = $this->createMock(ReviewInvitationDelivery::class);
        $delivery->expects(self::once())
            ->method('deliver')
            ->with($issued)
            ->willThrowException(new RuntimeException($issued->token()));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'review_invitation_delivery_failed',
                self::callback(static function (array $context) use ($issued): bool {
                    self::assertSame([
                        'booking_id' => 31,
                        'review_invitation_id' => 77,
                    ], $context);
                    self::assertStringNotContainsString(
                        $issued->token(),
                        json_encode($context, JSON_THROW_ON_ERROR),
                    );

                    return true;
                }),
            );
        $coordinator = new ReviewCheckoutInvitationCoordinator(
            new ReviewInvitationServiceProvider(
                static fn (): never => throw new RuntimeException('not used'),
            ),
            $delivery,
            $logger,
        );

        $coordinator->deliverAfterCommit($issued);

        self::addToAssertionCount(1);
    }

    private static function issuedInvitation(): IssuedReviewInvitation
    {
        return new IssuedReviewInvitation(
            new ReviewInvitation(
                77,
                31,
                hash('sha256', str_repeat('ab', 32)),
                new DateTimeImmutable('2026-08-08 12:00:00 UTC'),
                null,
                new DateTimeImmutable('2026-08-01 12:00:00 UTC'),
            ),
            str_repeat('ab', 32),
        );
    }
}
