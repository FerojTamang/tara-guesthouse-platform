<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\GuestPaymentException;
use App\Models\Booking;
use App\Models\BookingSubmissionResult;
use Throwable;

/**
 * Retains one signed Guest payment continuation in the private PHP session.
 *
 * The signed value is never returned to a view, URL, browser storage, or log.
 */
final readonly class GuestPaymentContinuation
{
    private const SESSION_KEY = 'public_payment_continuation';

    private const VERSION = 1;

    public function __construct(private Session $session)
    {
    }

    public function store(BookingSubmissionResult $result): void
    {
        try {
            $booking = $result->booking();

            if ($booking->bookingStatus() !== Booking::STATUS_PENDING) {
                throw GuestPaymentException::failure();
            }

            $this->session->set(self::SESSION_KEY, [
                'version' => self::VERSION,
                'booking_reference' => $booking->bookingReference(),
                'booking_access_token' => $result->bookingAccessToken(),
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
     *     booking_access_token: string
     * }
     */
    public function current(): ?array
    {
        try {
            $stored = $this->session->get(self::SESSION_KEY);

            if (!$this->isValid($stored)) {
                $this->session->remove(self::SESSION_KEY);

                return null;
            }

            return [
                'booking_reference' => $stored['booking_reference'],
                'booking_access_token' => $stored['booking_access_token'],
            ];
        } catch (Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }

    public function clear(): void
    {
        try {
            $this->session->remove(self::SESSION_KEY);
        } catch (Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }

    private function isValid(mixed $stored): bool
    {
        return is_array($stored)
            && array_keys($stored) === [
                'version',
                'booking_reference',
                'booking_access_token',
            ]
            && $stored['version'] === self::VERSION
            && is_string($stored['booking_reference'])
            && $stored['booking_reference'] !== ''
            && strlen($stored['booking_reference']) <= 30
            && preg_match('/[\x00-\x20\x7F]/', $stored['booking_reference']) !== 1
            && is_string($stored['booking_access_token'])
            && preg_match(
                '/^v1\.[A-Za-z0-9_-]{43}$/D',
                $stored['booking_access_token'],
            ) === 1;
    }
}
