<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AvailabilityException;
use App\Models\StayPeriod;
use DateTimeImmutable;
use DateTimeZone;

final readonly class RoomAvailabilityCalendarRequest
{
    private const MAXIMUM_DAYS = 93;

    private function __construct(private StayPeriod $window)
    {
    }

    public static function fromInput(mixed $from, mixed $to, DateTimeImmutable $localToday): self
    {
        $start = self::date($from);
        $end = self::date($to);
        $today = self::date($localToday->format('Y-m-d'));

        try {
            $window = new StayPeriod($start, $end);
        } catch (\InvalidArgumentException) {
            throw AvailabilityException::invalidInput('The calendar date range is invalid.');
        }

        $latest = $today->modify('+12 months');

        if (
            $start < $today
            || $end > $latest
            || $window->nights() > self::MAXIMUM_DAYS
        ) {
            throw AvailabilityException::invalidInput('The calendar date range is invalid.');
        }

        return new self($window);
    }

    public function window(): StayPeriod
    {
        return $this->window;
    }

    private static function date(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $value) !== 1) {
            throw AvailabilityException::invalidInput('The calendar date range is invalid.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw AvailabilityException::invalidInput('The calendar date range is invalid.');
        }

        return $date;
    }
}
