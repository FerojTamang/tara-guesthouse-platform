<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\GuestPaymentException;
use App\Models\Payment;
use DateTimeZone;
use Throwable;

/**
 * Transfers one safe payment-submission result through the private session.
 */
final readonly class GuestPaymentSuccessFlash
{
    private const SESSION_KEY = 'public_payment_success';

    private const VERSION = 1;

    public function __construct(
        private Session $session,
        private DateTimeZone $timezone,
    ) {
    }

    public function store(Payment $payment, string $bookingReference): void
    {
        try {
            $submittedAt = $payment->submittedAt();

            if (
                $payment->paymentStatus() !== Payment::STATUS_SUBMITTED
                || $submittedAt === null
                || $bookingReference === ''
                || strlen($bookingReference) > 30
                || preg_match('/[\x00-\x20\x7F]/', $bookingReference) === 1
            ) {
                throw GuestPaymentException::failure();
            }

            $this->session->set(self::SESSION_KEY, [
                'version' => self::VERSION,
                'booking_reference' => $bookingReference,
                'payment_status' => $payment->paymentStatus(),
                'currency' => $payment->currency(),
                'amount' => $payment->amount(),
                'submitted_at' => $submittedAt
                    ->setTimezone($this->timezone)
                    ->format(DATE_ATOM),
            ]);
        } catch (GuestPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }

    /**
     * @return null|array{
     *     booking_reference: string,
     *     payment_status: string,
     *     currency: string,
     *     amount: string,
     *     submitted_at: string
     * }
     */
    public function pull(): ?array
    {
        try {
            $stored = $this->session->pull(self::SESSION_KEY);

            if (!$this->isValid($stored)) {
                return null;
            }

            return [
                'booking_reference' => $stored['booking_reference'],
                'payment_status' => $stored['payment_status'],
                'currency' => $stored['currency'],
                'amount' => $stored['amount'],
                'submitted_at' => $stored['submitted_at'],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function isValid(mixed $stored): bool
    {
        return is_array($stored)
            && array_keys($stored) === [
                'version',
                'booking_reference',
                'payment_status',
                'currency',
                'amount',
                'submitted_at',
            ]
            && $stored['version'] === self::VERSION
            && is_string($stored['booking_reference'])
            && $stored['booking_reference'] !== ''
            && strlen($stored['booking_reference']) <= 30
            && is_string($stored['payment_status'])
            && $stored['payment_status'] === Payment::STATUS_SUBMITTED
            && is_string($stored['currency'])
            && $stored['currency'] === Payment::CURRENCY_NPR
            && is_string($stored['amount'])
            && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $stored['amount']) === 1
            && $stored['amount'] !== '0.00'
            && is_string($stored['submitted_at'])
            && $stored['submitted_at'] !== '';
    }
}
