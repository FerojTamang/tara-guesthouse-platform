<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\ViewRenderer;
use App\Exceptions\MailException;
use App\Models\Booking;
use App\Models\MailMessage;
use App\Models\Payment;
use App\Services\MailTemplateRenderer;
use App\Services\MailTransport;
use App\Services\TransactionalMailService;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\MailFixture;

final class TransactionalMailServiceTest extends TestCase
{
    private CapturingMailTransport $transport;
    private TransactionalMailService $service;

    protected function setUp(): void
    {
        $this->transport = new CapturingMailTransport();
        $this->service = new TransactionalMailService(
            $this->transport,
            new MailTemplateRenderer(new ViewRenderer(
                dirname(__DIR__, 3) . '/resources/views',
            )),
            'Tara Guest House',
            'https://stay.example.test',
            new DateTimeZone('Asia/Kathmandu'),
        );
    }

    public function testBookingAndPaymentTemplatesContainOnlyNecessaryEscapedGuestData(): void
    {
        $booking = MailFixture::booking(guestName: '<script>Asha</script>');

        $this->service->sendBookingReceived($booking);
        $this->service->sendPaymentSubmitted($booking, MailFixture::payment());
        $this->service->sendPaymentReviewed(
            MailFixture::booking(Booking::STATUS_CONFIRMED, '<script>Asha</script>'),
            MailFixture::payment(Payment::STATUS_VERIFIED),
        );
        $this->service->sendPaymentReviewed(
            $booking,
            MailFixture::payment(Payment::STATUS_REJECTED),
        );

        self::assertCount(4, $this->transport->messages);
        $bookingMessage = $this->transport->messages[0];
        self::assertSame('guest@example.test', $bookingMessage->recipientAddress());
        self::assertSame('Tara Guest House: booking received', $bookingMessage->subject());
        self::assertStringContainsString('TGH-2026-MAIL000000000001', $bookingMessage->textBody());
        self::assertStringContainsString('&lt;script&gt;Asha&lt;/script&gt;', $bookingMessage->htmlBody());
        self::assertStringNotContainsString('<script>Asha</script>', $bookingMessage->htmlBody());

        $submitted = $this->transport->messages[1];
        self::assertStringContainsString('awaiting Administrator verification', $submitted->textBody());
        self::assertStringNotContainsString('BANK-001', $submitted->textBody());
        self::assertStringNotContainsString('payment-proofs', $submitted->htmlBody());

        $verified = $this->transport->messages[2];
        self::assertStringContainsString('booking is now confirmed', $verified->textBody());
        self::assertStringNotContainsString('Administrator review note', $verified->textBody());

        $rejected = $this->transport->messages[3];
        self::assertStringContainsString('payment rejected', strtolower($rejected->subject()));
        self::assertStringContainsString('booking has not been confirmed', $rejected->textBody());
        self::assertStringNotContainsString('Administrator review note', $rejected->textBody());
    }

    public function testCancelledAndRejectedTemplatesDoNotExposeInternalReasons(): void
    {
        $cancelled = MailFixture::booking('cancelled');
        $rejected = MailFixture::booking('rejected');

        $this->service->sendBookingStatusChanged($cancelled, 'cancelled');
        $this->service->sendBookingStatusChanged($rejected, 'rejected');

        self::assertCount(2, $this->transport->messages);
        self::assertStringContainsString('has been cancelled', $this->transport->messages[0]->textBody());
        self::assertStringNotContainsString('Guest requested cancellation', $this->transport->messages[0]->textBody());
        self::assertStringContainsString('has been rejected', $this->transport->messages[1]->textBody());
        self::assertStringNotContainsString('Booking could not be accepted', $this->transport->messages[1]->textBody());
    }

    public function testReviewInvitationPlacesRawTokenOnlyInsideTheSecureMessageLink(): void
    {
        $token = str_repeat('cd', 32);
        $booking = MailFixture::booking('checked_out');
        $invitation = MailFixture::invitation($token);

        $this->service->sendReviewInvitation($booking, $invitation);

        self::assertCount(1, $this->transport->messages);
        $message = $this->transport->messages[0];
        self::assertStringNotContainsString($token, $message->subject());
        self::assertStringNotContainsString($token, $message->recipientAddress());
        self::assertStringContainsString(
            'https://stay.example.test/review/' . $token,
            $message->textBody(),
        );
        self::assertStringContainsString('/review/' . $token, $message->htmlBody());
        self::assertStringNotContainsString($invitation->invitation()->tokenHash(), $message->textBody());
    }

    public function testWrongEventStateFailsBeforeTransport(): void
    {
        $operations = [
            fn () => $this->service->sendBookingReceived(
                MailFixture::booking('cancelled'),
            ),
            fn () => $this->service->sendBookingStatusChanged(
                MailFixture::booking('cancelled'),
                'rejected',
            ),
            fn () => $this->service->sendPaymentSubmitted(
                MailFixture::booking(),
                MailFixture::payment(Payment::STATUS_VERIFIED),
            ),
            fn () => $this->service->sendPaymentReviewed(
                MailFixture::booking('cancelled'),
                MailFixture::payment(Payment::STATUS_VERIFIED),
            ),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('Invalid mail event state was accepted.');
            } catch (MailException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertNull($exception->getPrevious());
            }
        }

        self::assertSame([], $this->transport->messages);
    }

    public function testMismatchedPaymentOrReviewFactsFailBeforeTransport(): void
    {
        try {
            $this->service->sendPaymentSubmitted(
                MailFixture::booking(),
                new \App\Models\Payment(
                    45,
                    99,
                    7,
                    'NPR',
                    '18000.00',
                    null,
                    null,
                    null,
                    'submitted',
                    new \DateTimeImmutable('2026-08-01 04:30:00 UTC'),
                    null,
                    null,
                    null,
                    null,
                    null,
                    new \DateTimeImmutable('2026-08-01 04:30:00 UTC'),
                    new \DateTimeImmutable('2026-08-01 04:30:00 UTC'),
                ),
            );
            self::fail('Mismatched payment was accepted.');
        } catch (MailException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        try {
            $this->service->sendReviewInvitation(
                MailFixture::booking(),
                MailFixture::invitation(),
            );
            self::fail('Non-checked-out booking received a review email.');
        } catch (MailException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertSame([], $this->transport->messages);
    }
}

final class CapturingMailTransport implements MailTransport
{
    /** @var list<MailMessage> */
    public array $messages = [];

    public function send(MailMessage $message): void
    {
        $this->messages[] = $message;
    }
}
