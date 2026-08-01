<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\MailException;
use App\Models\Booking;
use App\Models\IssuedReviewInvitation;
use App\Models\Payment;
use App\Repositories\BookingCatalogRepository;
use App\Services\ReviewInvitationMailDelivery;
use App\Services\TransactionalMailDispatcher;
use PHPUnit\Framework\TestCase;
use Tests\Support\MailFixture;

final class ReviewInvitationMailDeliveryTest extends TestCase
{
    public function testBookingIsResolvedAndRawTokenIsForwardedOnlyToDispatcher(): void
    {
        $booking = MailFixture::booking('checked_out');
        $repository = $this->createStub(BookingCatalogRepository::class);
        $repository->method('findById')->willReturn($booking);
        $dispatcher = new RecordingTransactionalMailDispatcher();
        $delivery = new ReviewInvitationMailDelivery($repository, $dispatcher);
        $invitation = MailFixture::invitation();

        $delivery->deliver($invitation);

        self::assertSame($booking, $dispatcher->reviewBooking);
        self::assertSame($invitation, $dispatcher->reviewInvitation);
    }

    public function testMissingBookingFailureIsSanitizedAndDetached(): void
    {
        $repository = $this->createStub(BookingCatalogRepository::class);
        $repository->method('findById')->willReturn(null);
        $delivery = new ReviewInvitationMailDelivery(
            $repository,
            new RecordingTransactionalMailDispatcher(),
        );
        $token = str_repeat('fe', 32);

        try {
            $delivery->deliver(MailFixture::invitation($token));
            self::fail('Missing invitation booking was accepted.');
        } catch (MailException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Transactional email operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString($token, $exception->getMessage());
        }
    }
}

final class RecordingTransactionalMailDispatcher implements TransactionalMailDispatcher
{
    public ?Booking $reviewBooking = null;
    public ?IssuedReviewInvitation $reviewInvitation = null;

    public function bookingReceived(Booking $booking): void
    {
    }

    public function bookingStatusChanged(Booking $booking, string $status): void
    {
    }

    public function paymentSubmitted(Booking $booking, Payment $payment): void
    {
    }

    public function paymentReviewed(Booking $booking, Payment $payment): void
    {
    }
    public function reviewInvitation(Booking $booking, IssuedReviewInvitation $invitation): void
    {
        $this->reviewBooking = $booking;
        $this->reviewInvitation = $invitation;
    }
}
