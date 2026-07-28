<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingSubmissionResult;
use DateTimeZone;
use Throwable;

/**
 * Transfers one booking success result through the private server-side session.
 *
 * The access token is stored only in the session and is never returned to a view.
 */
final readonly class BookingSuccessFlash
{
    private const SESSION_KEY = 'public_booking_success';

    private const VERSION = 1;

    public function __construct(
        private Session $session,
        private DateTimeZone $timezone,
    ) {
    }

    public function store(BookingSubmissionResult $result): void
    {
        try {
            $booking = $result->booking();
            $deadline = $booking->confirmationDeadlineAt();

            if (
                $booking->bookingStatus() !== Booking::STATUS_PENDING
                || $deadline === null
            ) {
                throw BookingException::failure();
            }

            $this->session->set(self::SESSION_KEY, [
                'version' => self::VERSION,
                'booking_reference' => $booking->bookingReference(),
                'booking_status' => $booking->bookingStatus(),
                'confirmation_deadline_at' => $deadline
                    ->setTimezone($this->timezone)
                    ->format(DATE_ATOM),
                'currency' => $booking->currency(),
                'total_amount' => $booking->totalAmount(),
                'booking_access_token' => $result->bookingAccessToken(),
            ]);
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    /**
     * @return null|array{
     *     booking_reference: string,
     *     booking_status: string,
     *     confirmation_deadline_at: string,
     *     currency: string,
     *     total_amount: string
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
                'booking_status' => $stored['booking_status'],
                'confirmation_deadline_at' => $stored['confirmation_deadline_at'],
                'currency' => $stored['currency'],
                'total_amount' => $stored['total_amount'],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function isValid(mixed $stored): bool
    {
        if (
            !is_array($stored)
            || array_keys($stored) !== [
                'version',
                'booking_reference',
                'booking_status',
                'confirmation_deadline_at',
                'currency',
                'total_amount',
                'booking_access_token',
            ]
            || $stored['version'] !== self::VERSION
            || !is_string($stored['booking_reference'])
            || !is_string($stored['booking_status'])
            || !is_string($stored['confirmation_deadline_at'])
            || !is_string($stored['currency'])
            || !is_string($stored['total_amount'])
            || !is_string($stored['booking_access_token'])
        ) {
            return false;
        }

        return $stored['booking_reference'] !== ''
            && strlen($stored['booking_reference']) <= 30
            && $stored['booking_status'] === Booking::STATUS_PENDING
            && $stored['currency'] === Booking::CURRENCY_NPR
            && preg_match('/^\d+\.\d{2}$/D', $stored['total_amount']) === 1
            && preg_match(
                '/^v1\.[A-Za-z0-9_-]{43}$/D',
                $stored['booking_access_token'],
            ) === 1;
    }
}
