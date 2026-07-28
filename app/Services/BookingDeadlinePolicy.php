<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use DateInterval;
use DateTimeImmutable;
use Throwable;

/**
 * Calculates the pending confirmation deadline from an explicitly approved duration.
 */
final readonly class BookingDeadlinePolicy
{
    public function __construct(private int $minutes)
    {
        if ($minutes <= 0) {
            throw BookingException::failure();
        }
    }

    public function minutes(): int
    {
        return $this->minutes;
    }

    public function deadlineFrom(DateTimeImmutable $acceptedAt): DateTimeImmutable
    {
        try {
            if ($acceptedAt->getOffset() !== 0) {
                throw BookingException::failure();
            }

            $deadline = $acceptedAt->add(
                new DateInterval('PT' . $this->minutes . 'M'),
            );

            if ($deadline <= $acceptedAt || $deadline->getOffset() !== 0) {
                throw BookingException::failure();
            }

            return $deadline;
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
