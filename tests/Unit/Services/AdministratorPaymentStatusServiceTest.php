<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AdministratorPaymentException;
use App\Exceptions\BookingException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingStatusTransitionResult;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PaymentWriteRepository;
use App\Requests\AdministratorPaymentStatusRequest;
use App\Services\AdministratorPaymentStatusService;
use App\Services\BookingTransaction;
use App\Services\VerifiedPaymentBookingConfirmer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorPaymentStatusServiceTest extends TestCase
{
    private PaymentStatusWriteRepositoryFake $payments;
    private PaymentStatusAuditRepositoryFake $audit;
    private PaymentStatusBookingConfirmerFake $bookingConfirmer;
    private PaymentStatusTransactionFake $transaction;
    private AdministratorPaymentStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = new PaymentStatusWriteRepositoryFake();
        $this->audit = new PaymentStatusAuditRepositoryFake();
        $this->bookingConfirmer = new PaymentStatusBookingConfirmerFake();
        $this->transaction = new PaymentStatusTransactionFake();
        $this->service = new AdministratorPaymentStatusService(
            $this->payments,
            $this->audit,
            $this->bookingConfirmer,
            $this->transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable(
                '2026-07-30 04:00:00 UTC',
            ),
        );
        $this->payments->payment = self::payment();
        $this->payments->booking = self::booking();
    }

    public function testItVerifiesSubmittedPaymentAndAppendsSafeAudit(): void
    {
        $result = $this->service->transition(
            44,
            self::request(Payment::STATUS_VERIFIED, 'Reference and proof confirmed.'),
            91,
            '203.0.113.20',
            'Tara Review Agent',
        );

        self::assertSame(44, $result->paymentId());
        self::assertSame(31, $result->bookingId());
        self::assertSame(Payment::STATUS_SUBMITTED, $result->fromStatus());
        self::assertSame(Payment::STATUS_VERIFIED, $result->toStatus());
        self::assertSame(['payment:44', 'booking:31', 'verified:31:44'], $this->payments->operations);
        self::assertSame(Payment::STATUS_VERIFIED, $this->payments->reviewedStatus);
        self::assertSame(91, $this->payments->reviewedBy);
        self::assertSame('Reference and proof confirmed.', $this->payments->reviewedNote);
        self::assertCount(1, $this->bookingConfirmer->calls);
        self::assertSame(31, $this->bookingConfirmer->calls[0]['booking_id']);
        self::assertSame(44, $this->bookingConfirmer->calls[0]['payment_id']);
        self::assertSame(91, $this->bookingConfirmer->calls[0]['administrator_id']);
        self::assertSame(1, $this->transaction->commitCount);
        self::assertSame(0, $this->transaction->rollbackCount);
        self::assertCount(1, $this->audit->entries);
        $entry = $this->audit->entries[0];
        self::assertSame('payment_verified', $entry->eventType());
        self::assertSame('payment', $entry->entityType());
        self::assertSame(44, $entry->entityId());
        self::assertSame(91, $entry->userId());
        self::assertSame('203.0.113.20', $entry->ipAddress());
        self::assertSame('Tara Review Agent', $entry->userAgent());
        self::assertSame([
            'booking_id' => 31,
            'from_status' => Payment::STATUS_SUBMITTED,
            'to_status' => Payment::STATUS_VERIFIED,
        ], json_decode(
            (string) $entry->metadataJson(),
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
        self::assertStringNotContainsString('Reference and proof confirmed.', (string) $entry->metadataJson());
        self::assertStringNotContainsString('payment-proofs', (string) $entry->metadataJson());
    }

    public function testVerificationClockIsReadOnlyAfterPaymentAndBookingLocks(): void
    {
        $service = new AdministratorPaymentStatusService(
            $this->payments,
            $this->audit,
            $this->bookingConfirmer,
            $this->transaction,
            function (): DateTimeImmutable {
                self::assertSame(
                    ['payment:44', 'booking:31'],
                    $this->payments->operations,
                );

                return new DateTimeImmutable('2026-07-30 04:00:00 UTC');
            },
        );

        $service->transition(
            44,
            self::request(Payment::STATUS_VERIFIED, 'Review completed.'),
            91,
            null,
            null,
        );

        self::assertSame(
            ['payment:44', 'booking:31', 'verified:31:44'],
            $this->payments->operations,
        );
    }

    public function testItRejectsOnlyThePaymentAttemptWithoutBookingChecks(): void
    {
        $result = $this->service->transition(
            44,
            self::request(Payment::STATUS_REJECTED, 'Reference could not be matched.'),
            91,
            null,
            null,
        );

        self::assertSame(Payment::STATUS_REJECTED, $result->toStatus());
        self::assertSame(['payment:44'], $this->payments->operations);
        self::assertSame(Payment::STATUS_REJECTED, $this->payments->reviewedStatus);
        self::assertSame([], $this->bookingConfirmer->calls);
        self::assertSame('payment_rejected', $this->audit->entries[0]->eventType());
        self::assertSame(1, $this->transaction->commitCount);
    }

    public function testOnlySubmittedAttemptMayTransition(): void
    {
        $this->payments->payment = self::payment(Payment::STATUS_REJECTED);

        try {
            $this->service->transition(
                44,
                self::request(Payment::STATUS_VERIFIED, 'Review again.'),
                91,
                null,
                null,
            );
            self::fail('Expected a transition conflict.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertNull($this->payments->reviewedStatus);
        self::assertSame([], $this->audit->entries);
        self::assertSame(1, $this->transaction->rollbackCount);
    }

    public function testVerificationRejectsIneligibleOrDuplicateBookingState(): void
    {
        $scenarios = [
            'missing booking' => static function (PaymentStatusWriteRepositoryFake $fake): void {
                $fake->booking = null;
            },
            'confirmed booking' => static function (PaymentStatusWriteRepositoryFake $fake): void {
                $fake->booking = self::booking(Booking::STATUS_CONFIRMED);
            },
            'expired booking' => static function (PaymentStatusWriteRepositoryFake $fake): void {
                $fake->booking = self::booking(
                    deadline: new DateTimeImmutable('2026-07-30 04:00:00 UTC'),
                );
            },
            'amount mismatch' => static function (PaymentStatusWriteRepositoryFake $fake): void {
                $fake->booking = self::booking(total: '19000.00');
            },
            'existing verified payment' => static function (PaymentStatusWriteRepositoryFake $fake): void {
                $fake->hasVerified = true;
            },
        ];

        foreach ($scenarios as $name => $configure) {
            $payments = new PaymentStatusWriteRepositoryFake();
            $payments->payment = self::payment();
            $payments->booking = self::booking();
            $configure($payments);
            $audit = new PaymentStatusAuditRepositoryFake();
            $bookingConfirmer = new PaymentStatusBookingConfirmerFake();
            $transaction = new PaymentStatusTransactionFake();
            $service = new AdministratorPaymentStatusService(
                $payments,
                $audit,
                $bookingConfirmer,
                $transaction,
                static fn (): DateTimeImmutable => new DateTimeImmutable(
                    '2026-07-30 04:00:00 UTC',
                ),
            );

            try {
                $service->transition(
                    44,
                    self::request(Payment::STATUS_VERIFIED, 'Verify payment.'),
                    91,
                    null,
                    null,
                );
                self::fail('Expected verification conflict for ' . $name);
            } catch (AdministratorPaymentException $exception) {
                self::assertTrue($exception->isConflict(), $name);
            }

            self::assertNull($payments->reviewedStatus, $name);
            self::assertSame([], $audit->entries, $name);
            self::assertSame(1, $transaction->rollbackCount, $name);
        }
    }

    public function testMissingPaymentReturnsNotFoundAndRollsBack(): void
    {
        $this->payments->payment = null;

        try {
            $this->service->transition(
                404,
                self::request(Payment::STATUS_REJECTED, 'No payment.'),
                91,
                null,
                null,
            );
            self::fail('Expected a missing payment.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isNotFound());
        }

        self::assertSame(1, $this->transaction->rollbackCount);
    }

    public function testAuditFailureRollsBackPaymentDecision(): void
    {
        $this->audit->fail = true;

        try {
            $this->service->transition(
                44,
                self::request(Payment::STATUS_REJECTED, 'Reject payment.'),
                91,
                null,
                null,
            );
            self::fail('Expected an audit failure.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('audit exploded', $exception->getMessage());
        }

        self::assertSame(0, $this->transaction->commitCount);
        self::assertSame(1, $this->transaction->rollbackCount);
    }

    public function testBookingConfirmationConflictRollsBackVerification(): void
    {
        $this->bookingConfirmer->conflict = true;

        try {
            $this->service->transition(
                44,
                self::request(Payment::STATUS_VERIFIED, 'Verify payment.'),
                91,
                null,
                null,
            );
            self::fail('Expected booking confirmation conflict.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertSame(0, $this->transaction->commitCount);
        self::assertSame(1, $this->transaction->rollbackCount);
        self::assertSame([], $this->audit->entries);
    }

    public function testItWillNotJoinAnExistingTransaction(): void
    {
        $this->transaction->active = true;

        $this->expectException(AdministratorPaymentException::class);

        try {
            $this->service->transition(
                44,
                self::request(Payment::STATUS_REJECTED, 'Reject payment.'),
                91,
                null,
                null,
            );
        } finally {
            self::assertSame([], $this->payments->operations);
        }
    }

    private static function request(string $status, string $note): AdministratorPaymentStatusRequest
    {
        return AdministratorPaymentStatusRequest::fromInput($status, $note, true);
    }

    private static function payment(string $status = Payment::STATUS_SUBMITTED): Payment
    {
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
            $status === Payment::STATUS_VERIFIED
                ? new DateTimeImmutable('2026-07-30 03:30:00 UTC')
                : null,
            $status === Payment::STATUS_VERIFIED ? 91 : null,
            $status === Payment::STATUS_REJECTED
                ? new DateTimeImmutable('2026-07-30 03:30:00 UTC')
                : null,
            $status === Payment::STATUS_REJECTED ? 91 : null,
            $status === Payment::STATUS_SUBMITTED ? null : 'Reviewed.',
            $createdAt,
            $createdAt,
        );
    }

    private static function booking(
        string $status = Booking::STATUS_PENDING,
        string $total = '18000.00',
        ?DateTimeImmutable $deadline = null,
    ): Booking {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Booking(
            31,
            'TGH-2026-PAYMENTSTATUS00001',
            null,
            'Payment Status Guest',
            'payment-status@example.test',
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
}

final class PaymentStatusWriteRepositoryFake implements PaymentWriteRepository
{
    public ?Payment $payment = null;
    public ?Booking $booking = null;
    public bool $hasVerified = false;
    /** @var list<string> */
    public array $operations = [];
    public ?string $reviewedStatus = null;
    public ?int $reviewedBy = null;
    public ?string $reviewedNote = null;

    public function lockBookingByReference(string $bookingReference): ?Booking
    {
        return $this->booking;
    }

    public function lockBookingById(int $bookingId): ?Booking
    {
        $this->operations[] = 'booking:' . $bookingId;

        return $this->booking;
    }

    public function lockPaymentMethodById(int $paymentMethodId): ?PaymentMethod
    {
        return null;
    }

    public function lockCurrent(int $paymentId): ?Payment
    {
        $this->operations[] = 'payment:' . $paymentId;

        return $this->payment;
    }

    public function createSubmitted(
        int $bookingId,
        int $paymentMethodId,
        string $amount,
        ?string $transactionReference,
        ?string $proofPath,
        ?string $guestNotes,
        DateTimeImmutable $submittedAt,
    ): Payment {
        throw new RuntimeException('Not used.');
    }

    public function hasVerifiedForBooking(
        int $bookingId,
        ?int $excludingPaymentId = null,
    ): bool {
        $this->operations[] = sprintf(
            'verified:%d:%s',
            $bookingId,
            (string) $excludingPaymentId,
        );

        return $this->hasVerified;
    }

    public function markVerified(
        Payment $current,
        int $verifiedBy,
        string $note,
        DateTimeImmutable $verifiedAt,
    ): Payment {
        return $this->review(
            $current,
            Payment::STATUS_VERIFIED,
            $verifiedBy,
            $note,
            $verifiedAt,
        );
    }

    public function markRejected(
        Payment $current,
        int $rejectedBy,
        string $note,
        DateTimeImmutable $rejectedAt,
    ): Payment {
        return $this->review(
            $current,
            Payment::STATUS_REJECTED,
            $rejectedBy,
            $note,
            $rejectedAt,
        );
    }

    private function review(
        Payment $current,
        string $status,
        int $actorId,
        string $note,
        DateTimeImmutable $reviewedAt,
    ): Payment {
        $this->reviewedStatus = $status;
        $this->reviewedBy = $actorId;
        $this->reviewedNote = $note;

        return new Payment(
            $current->id(),
            $current->bookingId(),
            $current->paymentMethodId(),
            $current->currency(),
            $current->amount(),
            $current->transactionReference(),
            $current->proofPath(),
            $current->guestNotes(),
            $status,
            $current->submittedAt(),
            $status === Payment::STATUS_VERIFIED ? $reviewedAt : null,
            $status === Payment::STATUS_VERIFIED ? $actorId : null,
            $status === Payment::STATUS_REJECTED ? $reviewedAt : null,
            $status === Payment::STATUS_REJECTED ? $actorId : null,
            $note,
            $current->createdAt(),
            $reviewedAt,
        );
    }
}

final class PaymentStatusAuditRepositoryFake implements AuditLogWriteRepository
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

final class PaymentStatusBookingConfirmerFake implements VerifiedPaymentBookingConfirmer
{
    /** @var list<array{booking_id: int, payment_id: int, administrator_id: int}> */
    public array $calls = [];
    public bool $conflict = false;

    public function confirm(
        Booking $booking,
        Payment $verifiedPayment,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeImmutable $confirmedAt,
    ): BookingStatusTransitionResult {
        $this->calls[] = [
            'booking_id' => $booking->id(),
            'payment_id' => $verifiedPayment->id(),
            'administrator_id' => $administratorId,
        ];

        if ($this->conflict) {
            throw BookingException::transitionConflict();
        }

        return new BookingStatusTransitionResult(
            $booking->id(),
            $booking->bookingReference(),
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            $confirmedAt,
        );
    }
}

final class PaymentStatusTransactionFake implements BookingTransaction
{
    public bool $active = false;
    public int $commitCount = 0;
    public int $rollbackCount = 0;

    public function begin(): void
    {
        if ($this->active) {
            throw new RuntimeException('already active');
        }

        $this->active = true;
    }

    public function commit(): void
    {
        if (!$this->active) {
            throw new RuntimeException('not active');
        }

        $this->commitCount++;
        $this->active = false;
    }

    public function rollBack(): void
    {
        if (!$this->active) {
            throw new RuntimeException('not active');
        }

        $this->rollbackCount++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
