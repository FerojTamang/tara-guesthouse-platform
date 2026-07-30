<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingStatusSnapshot;
use App\Models\Payment;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingStatusWriteRepository;
use App\Services\VerifiedPaymentBookingConfirmationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VerifiedPaymentBookingConfirmationServiceTest extends TestCase
{
    public function testItConfirmsBookingAndAppendsLinkedEventAndAudit(): void
    {
        $bookings = new VerifiedPaymentBookingRepositoryFake();
        $audit = new VerifiedPaymentBookingAuditFake();
        $service = new VerifiedPaymentBookingConfirmationService($bookings, $audit);
        $changedAt = new DateTimeImmutable('2026-07-30 04:00:00 UTC');

        $result = $service->confirm(
            self::booking(),
            self::verifiedPayment($changedAt),
            91,
            '203.0.113.90',
            'Booking Confirmation Agent',
            $changedAt,
        );

        self::assertSame(31, $result->bookingId());
        self::assertSame(Booking::STATUS_PENDING, $result->fromStatus());
        self::assertSame(Booking::STATUS_CONFIRMED, $result->toStatus());
        self::assertCount(1, $bookings->updates);
        self::assertSame(Booking::STATUS_CONFIRMED, $bookings->updates[0]['target']);
        self::assertNull($bookings->updates[0]['reason']);
        self::assertCount(1, $bookings->events);
        self::assertSame('booking_confirmed', $bookings->events[0]['event_type']);
        self::assertSame(
            ['payment_id' => 44],
            json_decode(
                (string) $bookings->events[0]['metadata_json'],
                true,
                16,
                JSON_THROW_ON_ERROR,
            ),
        );
        self::assertCount(1, $audit->entries);
        $entry = $audit->entries[0];
        self::assertSame('booking_confirmed_from_payment', $entry->eventType());
        self::assertSame('booking', $entry->entityType());
        self::assertSame(31, $entry->entityId());
        self::assertSame(91, $entry->userId());
        self::assertSame('203.0.113.90', $entry->ipAddress());
        self::assertSame('Booking Confirmation Agent', $entry->userAgent());
        self::assertSame([
            'payment_id' => 44,
            'from_status' => Booking::STATUS_PENDING,
            'to_status' => Booking::STATUS_CONFIRMED,
        ], json_decode(
            (string) $entry->metadataJson(),
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
        self::assertStringNotContainsString('payment-proofs', (string) $entry->metadataJson());
        self::assertStringNotContainsString('Guest note', (string) $entry->metadataJson());
        self::assertStringNotContainsString('Verified note', (string) $entry->metadataJson());
    }

    public function testItRejectsBookingOrPaymentThatIsNoLongerEligible(): void
    {
        $changedAt = new DateTimeImmutable('2026-07-30 04:00:00 UTC');
        $scenarios = [
            'booking already confirmed' => [
                self::booking(Booking::STATUS_CONFIRMED),
                self::verifiedPayment($changedAt),
                91,
                $changedAt,
            ],
            'deadline reached' => [
                self::booking(deadline: $changedAt),
                self::verifiedPayment($changedAt),
                91,
                $changedAt,
            ],
            'payment not verified' => [
                self::booking(),
                self::submittedPayment(),
                91,
                $changedAt,
            ],
            'amount mismatch' => [
                self::booking(total: '19000.00'),
                self::verifiedPayment($changedAt),
                91,
                $changedAt,
            ],
            'different actor' => [
                self::booking(),
                self::verifiedPayment($changedAt),
                92,
                $changedAt,
            ],
        ];

        foreach ($scenarios as $name => [$booking, $payment, $administratorId, $time]) {
            $bookings = new VerifiedPaymentBookingRepositoryFake();
            $audit = new VerifiedPaymentBookingAuditFake();
            $service = new VerifiedPaymentBookingConfirmationService($bookings, $audit);

            try {
                $service->confirm(
                    $booking,
                    $payment,
                    $administratorId,
                    null,
                    null,
                    $time,
                );
                self::fail('Expected conflict for ' . $name);
            } catch (BookingException $exception) {
                self::assertTrue($exception->isConflict(), $name);
            }

            self::assertSame([], $bookings->updates, $name);
            self::assertSame([], $bookings->events, $name);
            self::assertSame([], $audit->entries, $name);
        }
    }

    public function testAuditFailureIsSanitizedForCallerOwnedRollback(): void
    {
        $bookings = new VerifiedPaymentBookingRepositoryFake();
        $audit = new VerifiedPaymentBookingAuditFake();
        $audit->fail = true;
        $service = new VerifiedPaymentBookingConfirmationService($bookings, $audit);
        $changedAt = new DateTimeImmutable('2026-07-30 04:00:00 UTC');

        try {
            $service->confirm(
                self::booking(),
                self::verifiedPayment($changedAt),
                91,
                null,
                null,
                $changedAt,
            );
            self::fail('Expected booking audit failure.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('audit exploded', $exception->getMessage());
        }

        self::assertCount(1, $bookings->updates);
        self::assertCount(1, $bookings->events);
    }

    private static function booking(
        string $status = Booking::STATUS_PENDING,
        string $total = '18000.00',
        ?DateTimeImmutable $deadline = null,
    ): Booking {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Booking(
            31,
            'TGH-2026-CONFIRM000000001',
            null,
            'Confirmation Guest',
            'confirmation@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            $total,
            $total,
            $status,
            null,
            $createdAt,
            'v1',
            $deadline ?? new DateTimeImmutable('2026-07-30 05:00:00 UTC'),
            $status === Booking::STATUS_CONFIRMED
                ? new DateTimeImmutable('2026-07-30 03:30:00 UTC')
                : null,
            null,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
            null,
        );
    }

    private static function verifiedPayment(DateTimeImmutable $verifiedAt): Payment
    {
        return self::payment(Payment::STATUS_VERIFIED, $verifiedAt, 91);
    }

    private static function submittedPayment(): Payment
    {
        return self::payment(Payment::STATUS_SUBMITTED, null, null);
    }

    private static function payment(
        string $status,
        ?DateTimeImmutable $verifiedAt,
        ?int $verifiedBy,
    ): Payment {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Payment(
            44,
            31,
            2,
            Payment::CURRENCY_NPR,
            '18000.00',
            'BANK-44',
            'payment-proofs/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf',
            'Guest note.',
            $status,
            new DateTimeImmutable('2026-07-30 03:10:00 UTC'),
            $verifiedAt,
            $verifiedBy,
            null,
            null,
            $status === Payment::STATUS_VERIFIED ? 'Verified note.' : null,
            $createdAt,
            $verifiedAt ?? $createdAt,
        );
    }
}

final class VerifiedPaymentBookingRepositoryFake implements BookingStatusWriteRepository
{
    /** @var list<array{target: string, reason: ?string}> */
    public array $updates = [];
    /** @var list<array{event_type: string, metadata_json: ?string}> */
    public array $events = [];

    public function lockCurrent(int $bookingId): ?BookingStatusSnapshot
    {
        throw new RuntimeException('The caller already owns the booking row lock.');
    }

    public function updateStatus(
        BookingStatusSnapshot $current,
        string $targetStatus,
        ?string $reason,
        DateTimeImmutable $changedAt,
    ): void {
        $this->updates[] = ['target' => $targetStatus, 'reason' => $reason];
    }

    public function appendEvent(
        int $bookingId,
        string $eventType,
        string $fromStatus,
        string $toStatus,
        int $performedBy,
        ?string $reason,
        DateTimeImmutable $createdAt,
        ?string $metadataJson = null,
    ): void {
        $this->events[] = [
            'event_type' => $eventType,
            'metadata_json' => $metadataJson,
        ];
    }
}

final class VerifiedPaymentBookingAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public bool $fail = false;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->fail) {
            throw new RuntimeException('audit exploded');
        }

        $this->entries[] = $entry;
    }
}
