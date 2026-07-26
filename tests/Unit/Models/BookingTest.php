<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Booking;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    public function testItPreservesCompleteImmutableBookingData(): void
    {
        $booking = self::booking();

        self::assertSame(7, $booking->id());
        self::assertSame('TGH-7F3A9C2D', $booking->bookingReference());
        self::assertNull($booking->userId());
        self::assertSame('Guest Example', $booking->guestFullName());
        self::assertSame('guest@example.test', $booking->guestEmail());
        self::assertSame('+9779812345678', $booking->guestPhone());
        self::assertSame('2026-08-10', $booking->checkIn()->format('Y-m-d'));
        self::assertSame('2026-08-13', $booking->checkOut()->format('Y-m-d'));
        self::assertSame(2, $booking->adults());
        self::assertSame(1, $booking->children());
        self::assertSame(Booking::CURRENCY_NPR, $booking->currency());
        self::assertSame('18000.00', $booking->roomAmount());
        self::assertSame('18000.00', $booking->totalAmount());
        self::assertSame(Booking::STATUS_PENDING, $booking->bookingStatus());
        self::assertSame('Late arrival.', $booking->specialRequest());
        self::assertSame('v1', $booking->policyVersion());
        self::assertNull($booking->confirmedAt());
        self::assertNull($booking->checkedInAt());
        self::assertNull($booking->checkedOutAt());
        self::assertNull($booking->cancelledAt());
        self::assertNull($booking->cancellationReason());
        self::assertNull($booking->rejectedAt());
        self::assertNull($booking->rejectionReason());
        self::assertNull($booking->deletedAt());
        self::assertFalse($booking->isDeleted());
        self::assertSame(0, $booking->createdAt()->getOffset());
        self::assertSame(0, $booking->updatedAt()->getOffset());
    }

    public function testItSupportsNullableFutureUserAndLifecycleFields(): void
    {
        $timestamp = self::timestamp('2026-08-01 12:00:00');
        $booking = self::booking([
            'userId' => 9,
            'bookingStatus' => Booking::STATUS_CANCELLED,
            'confirmedAt' => $timestamp,
            'checkedInAt' => $timestamp,
            'checkedOutAt' => $timestamp,
            'cancelledAt' => $timestamp,
            'cancellationReason' => 'Guest request.',
            'rejectedAt' => $timestamp,
            'rejectionReason' => 'Rejected fixture.',
            'deletedAt' => $timestamp,
        ]);

        self::assertSame(9, $booking->userId());
        self::assertSame($timestamp, $booking->confirmedAt());
        self::assertSame($timestamp, $booking->checkedInAt());
        self::assertSame($timestamp, $booking->checkedOutAt());
        self::assertSame($timestamp, $booking->cancelledAt());
        self::assertSame('Guest request.', $booking->cancellationReason());
        self::assertSame($timestamp, $booking->rejectedAt());
        self::assertSame('Rejected fixture.', $booking->rejectionReason());
        self::assertSame($timestamp, $booking->deletedAt());
        self::assertTrue($booking->isDeleted());
    }

    public function testCanonicalStatusRecognitionIsExact(): void
    {
        foreach ([
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CHECKED_IN,
            Booking::STATUS_CHECKED_OUT,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_REJECTED,
        ] as $status) {
            self::assertTrue(Booking::isStatus($status));
        }

        self::assertFalse(Booking::isStatus('Pending'));
        self::assertFalse(Booking::isStatus('completed'));
        self::assertFalse(Booking::isStatus(''));
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidData')]
    public function testItRejectsInvalidPersistenceData(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Booking data is invalid.');

        self::booking($override);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidData(): iterable
    {
        yield 'non-positive id' => [['id' => 0]];
        yield 'invalid nullable user id' => [['userId' => 0]];
        yield 'blank reference' => [['bookingReference' => '']];
        yield 'untrimmed reference' => [['bookingReference' => ' TGH-1 ']];
        yield 'reference too long' => [['bookingReference' => str_repeat('A', 31)]];
        yield 'blank guest name' => [['guestFullName' => '']];
        yield 'invalid email' => [['guestEmail' => 'not-an-email']];
        yield 'blank phone' => [['guestPhone' => '']];
        yield 'check-in has time component' => [['checkIn' => self::timestamp('2026-08-10 12:00:00')]];
        yield 'checkout not after check-in' => [['checkOut' => self::date('2026-08-10')]];
        yield 'no adults' => [['adults' => 0]];
        yield 'negative children' => [['children' => -1]];
        yield 'wrong currency' => [['currency' => 'USD']];
        yield 'zero room amount' => [['roomAmount' => '0.00']];
        yield 'malformed total amount' => [['totalAmount' => '100']];
        yield 'unknown status' => [['bookingStatus' => 'completed']];
        yield 'non-UTC accepted timestamp' => [[
            'termsAcceptedAt' => new DateTimeImmutable('2026-08-01 12:00:00 +05:45'),
        ]];
        yield 'non-UTC nullable lifecycle timestamp' => [[
            'cancelledAt' => new DateTimeImmutable('2026-08-01 12:00:00 +05:45'),
        ]];
        yield 'non-UTC created timestamp' => [[
            'createdAt' => new DateTimeImmutable('2026-08-01 12:00:00 +05:45'),
        ]];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function booking(array $override = []): Booking
    {
        return new Booking(...array_replace([
            'id' => 7,
            'bookingReference' => 'TGH-7F3A9C2D',
            'userId' => null,
            'guestFullName' => 'Guest Example',
            'guestEmail' => 'guest@example.test',
            'guestPhone' => '+9779812345678',
            'checkIn' => self::date('2026-08-10'),
            'checkOut' => self::date('2026-08-13'),
            'adults' => 2,
            'children' => 1,
            'currency' => Booking::CURRENCY_NPR,
            'roomAmount' => '18000.00',
            'totalAmount' => '18000.00',
            'bookingStatus' => Booking::STATUS_PENDING,
            'specialRequest' => 'Late arrival.',
            'termsAcceptedAt' => self::timestamp('2026-08-01 10:00:00'),
            'policyVersion' => 'v1',
            'confirmationDeadlineAt' => self::timestamp('2026-08-02 10:00:00'),
            'confirmedAt' => null,
            'checkedInAt' => null,
            'checkedOutAt' => null,
            'cancelledAt' => null,
            'cancellationReason' => null,
            'rejectedAt' => null,
            'rejectionReason' => null,
            'createdAt' => self::timestamp('2026-08-01 10:00:00'),
            'updatedAt' => self::timestamp('2026-08-01 10:00:00'),
            'deletedAt' => null,
        ], $override));
    }

    private static function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00 UTC');
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
