<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Booking;
use App\Models\IssuedReviewInvitation;
use App\Models\Payment;
use App\Models\ReviewInvitation;
use DateTimeImmutable;

final class MailFixture
{
    public static function booking(
        string $status = Booking::STATUS_PENDING,
        string $guestName = 'Guest Example',
        string $guestEmail = 'guest@example.test',
    ): Booking {
        $created = new DateTimeImmutable('2026-08-01 04:00:00 UTC');

        return new Booking(
            31,
            'TGH-2026-MAIL000000000001',
            null,
            $guestName,
            $guestEmail,
            '+9779812345678',
            new DateTimeImmutable('2026-08-10 UTC'),
            new DateTimeImmutable('2026-08-13 UTC'),
            2,
            1,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            $status,
            null,
            $created,
            'v1',
            new DateTimeImmutable('2026-08-01 10:00:00 UTC'),
            $status === Booking::STATUS_CONFIRMED ? new DateTimeImmutable('2026-08-01 05:00:00 UTC') : null,
            $status === Booking::STATUS_CHECKED_IN ? new DateTimeImmutable('2026-08-10 06:00:00 UTC') : null,
            $status === Booking::STATUS_CHECKED_OUT ? new DateTimeImmutable('2026-08-13 04:00:00 UTC') : null,
            $status === Booking::STATUS_CANCELLED ? new DateTimeImmutable('2026-08-02 04:00:00 UTC') : null,
            $status === Booking::STATUS_CANCELLED ? 'Guest requested cancellation.' : null,
            $status === Booking::STATUS_REJECTED ? new DateTimeImmutable('2026-08-02 04:00:00 UTC') : null,
            $status === Booking::STATUS_REJECTED ? 'Booking could not be accepted.' : null,
            $created,
            new DateTimeImmutable('2026-08-02 04:00:00 UTC'),
            null,
        );
    }

    public static function payment(string $status = Payment::STATUS_SUBMITTED): Payment
    {
        $reviewedAt = new DateTimeImmutable('2026-08-01 05:00:00 UTC');

        return new Payment(
            44,
            31,
            7,
            Payment::CURRENCY_NPR,
            '18000.00',
            'BANK-001',
            null,
            null,
            $status,
            new DateTimeImmutable('2026-08-01 04:30:00 UTC'),
            $status === Payment::STATUS_VERIFIED ? $reviewedAt : null,
            $status === Payment::STATUS_VERIFIED ? 91 : null,
            $status === Payment::STATUS_REJECTED ? $reviewedAt : null,
            $status === Payment::STATUS_REJECTED ? 91 : null,
            in_array($status, [Payment::STATUS_VERIFIED, Payment::STATUS_REJECTED], true)
                ? 'Administrator review note.'
                : null,
            new DateTimeImmutable('2026-08-01 04:30:00 UTC'),
            $reviewedAt,
        );
    }

    public static function invitation(string $token = ''): IssuedReviewInvitation
    {
        $token = $token === '' ? str_repeat('ab', 32) : $token;

        return new IssuedReviewInvitation(
            new ReviewInvitation(
                77,
                31,
                hash('sha256', $token),
                new DateTimeImmutable('2026-08-08 04:00:00 UTC'),
                null,
                new DateTimeImmutable('2026-08-01 04:00:00 UTC'),
            ),
            $token,
        );
    }
}
