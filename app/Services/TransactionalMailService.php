<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MailException;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\IssuedReviewInvitation;
use App\Models\MailMessage;
use App\Models\Payment;
use DateTimeImmutable;
use DateTimeZone;
use SensitiveParameter;
use Throwable;

/** Prepares and sends the approved Version 1 Guest transactional emails. */
final readonly class TransactionalMailService
{
    public function __construct(
        private MailTransport $transport,
        private MailTemplateRenderer $templates,
        private string $applicationName,
        private string $baseUrl,
        private DateTimeZone $displayTimezone,
    ) {
        if (!$this->validApplicationName() || !$this->validBaseUrl()) {
            throw MailException::configuration();
        }
    }

    public function sendBookingReceived(Booking $booking): void
    {
        if ($booking->bookingStatus() !== Booking::STATUS_PENDING) {
            throw MailException::failure();
        }

        $this->send(
            $booking,
            $this->applicationName . ': booking received',
            'booking-received',
            $this->bookingData($booking),
        );
    }

    public function sendBookingStatusChanged(Booking $booking, string $status): void
    {
        if (
            !in_array($status, [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED], true)
            || $booking->bookingStatus() !== $status
        ) {
            throw MailException::failure();
        }

        $label = $status === Booking::STATUS_CANCELLED ? 'cancelled' : 'rejected';
        $this->send(
            $booking,
            sprintf('%s: booking %s', $this->applicationName, $label),
            'booking-status',
            [
                ...$this->bookingData($booking),
                'statusLabel' => ucfirst($label),
            ],
        );
    }

    public function sendPaymentSubmitted(Booking $booking, Payment $payment): void
    {
        $this->assertPaymentBooking($booking, $payment);

        if (!$payment->isSubmitted() || $booking->bookingStatus() !== Booking::STATUS_PENDING) {
            throw MailException::failure();
        }

        $this->send(
            $booking,
            $this->applicationName . ': payment submitted',
            'payment-submitted',
            [
                ...$this->bookingData($booking),
                ...$this->paymentData($payment),
            ],
        );
    }

    public function sendPaymentReviewed(Booking $booking, Payment $payment): void
    {
        $this->assertPaymentBooking($booking, $payment);

        if (!$payment->isVerified() && !$payment->isRejected()) {
            throw MailException::failure();
        }

        $verified = $payment->isVerified();
        $bookingStatusValid = $verified
            ? $booking->bookingStatus() === Booking::STATUS_CONFIRMED
            : $booking->bookingStatus() === Booking::STATUS_PENDING;

        if (!$bookingStatusValid) {
            throw MailException::failure();
        }
        $this->send(
            $booking,
            $verified
                ? $this->applicationName . ': payment verified and booking confirmed'
                : $this->applicationName . ': payment rejected',
            'payment-status',
            [
                ...$this->bookingData($booking),
                ...$this->paymentData($payment),
                'statusLabel' => $verified ? 'Verified' : 'Rejected',
                'bookingConfirmed' => $verified,
            ],
        );
    }

    public function sendReviewInvitation(
        Booking $booking,
        #[SensitiveParameter] IssuedReviewInvitation $invitation,
    ): void {
        if (
            $invitation->invitation()->bookingId() !== $booking->id()
            || $booking->bookingStatus() !== Booking::STATUS_CHECKED_OUT
        ) {
            throw MailException::failure();
        }

        $token = $invitation->token();
        $reviewUrl = $this->baseUrl . '/review/' . rawurlencode($token);
        $this->send(
            $booking,
            $this->applicationName . ': share your stay experience',
            'review-invitation',
            [
                ...$this->bookingData($booking),
                'reviewUrl' => $reviewUrl,
                'expiresAt' => $this->formatDateTime($invitation->invitation()->expiresAt()),
            ],
        );
    }

    public function sendContactReply(
        ContactMessage $contact,
        #[SensitiveParameter] string $replyBody,
    ): void {
        if (
            $contact->isClosed()
            || $replyBody === ''
            || $replyBody !== trim($replyBody)
            || !mb_check_encoding($replyBody, 'UTF-8')
            || mb_strlen($replyBody, 'UTF-8') > 5000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $replyBody) === 1
        ) {
            throw MailException::failure();
        }

        $subject = 'Re: ' . mb_substr($contact->subject(), 0, 190, 'UTF-8');
        $this->sendTo(
            $contact->email(),
            $contact->name(),
            $subject,
            'contact-reply',
            [
                'guestName' => $contact->name(),
                'originalSubject' => $contact->subject(),
                'replyBody' => $replyBody,
            ],
        );
    }

    /** @param array<string, mixed> $data */
    private function send(
        Booking $booking,
        string $subject,
        string $template,
        array $data,
    ): void {
        $this->sendTo(
            $booking->guestEmail(),
            $booking->guestFullName(),
            $subject,
            $template,
            $data,
        );
    }

    /** @param array<string, mixed> $data */
    private function sendTo(
        string $recipientAddress,
        string $recipientName,
        string $subject,
        string $template,
        array $data,
    ): void {
        try {
            $rendered = $this->templates->render($template, [
                ...$data,
                'applicationName' => $this->applicationName,
                'baseUrl' => $this->baseUrl,
            ]);
            $this->transport->send(new MailMessage(
                $recipientAddress,
                $recipientName,
                $subject,
                $rendered['text'],
                $rendered['html'],
            ));
        } catch (MailException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw MailException::failure();
        }
    }

    /** @return array<string, mixed> */
    private function bookingData(Booking $booking): array
    {
        return [
            'guestName' => $booking->guestFullName(),
            'bookingReference' => $booking->bookingReference(),
            'checkIn' => $booking->checkIn()->setTimezone($this->displayTimezone)->format('Y-m-d'),
            'checkOut' => $booking->checkOut()->setTimezone($this->displayTimezone)->format('Y-m-d'),
            'currency' => $booking->currency(),
            'totalAmount' => $booking->totalAmount(),
            'confirmationDeadline' => $this->formatDateTime($booking->confirmationDeadlineAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function paymentData(Payment $payment): array
    {
        return [
            'paymentCurrency' => $payment->currency(),
            'paymentAmount' => $payment->amount(),
        ];
    }

    private function assertPaymentBooking(Booking $booking, Payment $payment): void
    {
        if (
            $payment->bookingId() !== $booking->id()
            || $payment->currency() !== $booking->currency()
        ) {
            throw MailException::failure();
        }
    }

    private function formatDateTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone($this->displayTimezone)->format('Y-m-d H:i T');
    }

    private function validApplicationName(): bool
    {
        return $this->applicationName !== ''
            && $this->applicationName === trim($this->applicationName)
            && mb_check_encoding($this->applicationName, 'UTF-8')
            && mb_strlen($this->applicationName, 'UTF-8') <= 100
            && preg_match('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $this->applicationName) !== 1;
    }

    private function validBaseUrl(): bool
    {
        if (
            $this->baseUrl === ''
            || $this->baseUrl !== rtrim($this->baseUrl, '/')
            || strlen($this->baseUrl) > 2048
            || filter_var($this->baseUrl, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $parts = parse_url($this->baseUrl);

        return is_array($parts)
            && in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && isset($parts['host'])
            && !isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment']);
    }
}
