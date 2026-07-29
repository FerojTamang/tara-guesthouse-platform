<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\PdoPaymentWriteRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PdoPaymentWriteRepositoryTest extends DatabaseTestCase
{
    private PdoPaymentWriteRepository $writer;

    private int $administratorId;

    private int $methodId;

    private int $bookingId;

    private int $secondBookingId;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $roleId = $this->insertRole();
        $this->administratorId = $this->insertUser($roleId);
        $this->methodId = $this->insertMethod();
        $this->bookingId = $this->insertBooking('TGH-PAY-WRITE-001');
        $this->secondBookingId = $this->insertBooking('TGH-PAY-WRITE-002');
        $this->writer = new PdoPaymentWriteRepository($this->connection);
    }

    public function testItLocksAndReloadsOneCurrentPaymentInsideATransaction(): void
    {
        $paymentId = $this->insertSubmittedPayment($this->bookingId, 'LOCK-001');

        try {
            $this->writer->lockCurrent($paymentId);
            self::fail('Expected a caller-owned transaction requirement.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment lock failed.', $exception->getMessage());
        }

        $this->connection->beginTransaction();

        try {
            $payment = $this->writer->lockCurrent($paymentId);

            self::assertInstanceOf(Payment::class, $payment);
            self::assertSame($paymentId, $payment->id());
            self::assertSame($this->bookingId, $payment->bookingId());
            self::assertTrue($payment->isSubmitted());
            self::assertTrue($this->connection->inTransaction());
            self::assertNull($this->writer->lockCurrent(999999));
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testItLocksAndReloadsSubmissionBookingAndMethodInsideATransaction(): void
    {
        foreach ([
            fn () => $this->writer->lockBookingByReference('TGH-PAY-WRITE-001'),
            fn () => $this->writer->lockPaymentMethodById($this->methodId),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected a caller-owned transaction requirement.');
            } catch (DatabaseException $exception) {
                self::assertStringContainsString('lock failed', $exception->getMessage());
            }
        }

        $this->connection->beginTransaction();

        try {
            $booking = $this->writer->lockBookingByReference('TGH-PAY-WRITE-001');
            $method = $this->writer->lockPaymentMethodById($this->methodId);

            self::assertInstanceOf(Booking::class, $booking);
            self::assertSame($this->bookingId, $booking->id());
            self::assertSame(Booking::STATUS_PENDING, $booking->bookingStatus());
            self::assertInstanceOf(PaymentMethod::class, $method);
            self::assertSame($this->methodId, $method->id());
            self::assertTrue($method->isActive());
            self::assertTrue($method->requiresProof());
            self::assertNull(
                $this->writer->lockBookingByReference('TGH-PAY-WRITE-MISSING'),
            );
            self::assertNull($this->writer->lockPaymentMethodById(999999));
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testItCreatesASubmittedAttemptUsingFixedNprAndCallerOwnedTransaction(): void
    {
        $this->connection->beginTransaction();

        $payment = $this->writer->createSubmitted(
            $this->bookingId,
            $this->methodId,
            '10000.00',
            'BANK-WRITE-001',
            'payment-proofs/write-001.pdf',
            'Paid from the booking contact account.',
            self::timestamp('2026-07-28 06:15:00'),
        );

        $this->connection->commit();

        self::assertGreaterThan(0, $payment->id());
        self::assertSame($this->bookingId, $payment->bookingId());
        self::assertSame($this->methodId, $payment->paymentMethodId());
        self::assertSame(Payment::CURRENCY_NPR, $payment->currency());
        self::assertSame('10000.00', $payment->amount());
        self::assertSame('BANK-WRITE-001', $payment->transactionReference());
        self::assertSame('payment-proofs/write-001.pdf', $payment->proofPath());
        self::assertSame(
            'Paid from the booking contact account.',
            $payment->guestNotes(),
        );
        self::assertTrue($payment->isSubmitted());
        self::assertSame(
            '2026-07-28 06:15:00',
            $payment->submittedAt()?->format('Y-m-d H:i:s'),
        );
        self::assertNull($payment->verifiedAt());
        self::assertNull($payment->rejectedAt());

        $stored = $this->connection->query(
            'SELECT currency, amount, payment_status, submitted_at '
            . 'FROM payments WHERE id = ' . $payment->id(),
        )->fetch();
        self::assertIsArray($stored);
        self::assertSame(Payment::CURRENCY_NPR, $stored['currency']);
        self::assertSame('10000.00', $stored['amount']);
        self::assertSame(Payment::STATUS_SUBMITTED, $stored['payment_status']);
        self::assertSame('2026-07-28 06:15:00', $stored['submitted_at']);
    }

    public function testRollbackRemovesTheSubmittedAttempt(): void
    {
        $this->connection->beginTransaction();

        $payment = $this->writer->createSubmitted(
            $this->bookingId,
            $this->methodId,
            '10000.00',
            null,
            null,
            null,
            self::timestamp('2026-07-28 06:20:00'),
        );

        self::assertGreaterThan(0, $payment->id());
        $this->connection->rollBack();

        self::assertSame(
            0,
            (int) $this->connection->query(
                'SELECT COUNT(*) FROM payments WHERE id = ' . $payment->id(),
            )->fetchColumn(),
        );
    }

    public function testCreateSubmittedRejectsInvalidPreconditionsBeforeWriting(): void
    {
        foreach ([
            ['bookingId' => 0],
            ['paymentMethodId' => 0],
            ['amount' => '0.00'],
            ['amount' => '10000'],
            ['transactionReference' => str_repeat('a', 101)],
            ['proofPath' => '../escape.pdf'],
            ['submittedAt' => new DateTimeImmutable('2026-07-28 06:15:00 +05:45')],
        ] as $override) {
            $this->connection->beginTransaction();

            try {
                $this->writer->createSubmitted(
                    $override['bookingId'] ?? $this->bookingId,
                    $override['paymentMethodId'] ?? $this->methodId,
                    $override['amount'] ?? '10000.00',
                    $override['transactionReference'] ?? null,
                    $override['proofPath'] ?? null,
                    null,
                    $override['submittedAt']
                        ?? self::timestamp('2026-07-28 06:15:00'),
                );
                self::fail('Expected an invalid payment-write precondition.');
            } catch (DatabaseException $exception) {
                self::assertSame('The payment write failed.', $exception->getMessage());
            } finally {
                $this->connection->rollBack();
            }
        }

        try {
            $this->writer->createSubmitted(
                $this->bookingId,
                $this->methodId,
                '10000.00',
                null,
                null,
                null,
                self::timestamp('2026-07-28 06:15:00'),
            );
            self::fail('Expected an active transaction requirement.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment write failed.', $exception->getMessage());
        }

        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COUNT(*) FROM payments')->fetchColumn(),
        );
    }

    public function testVerifiedLookupSupportsTheCallerHeldBookingLockContract(): void
    {
        $verifiedId = $this->insertReviewedPayment(
            $this->bookingId,
            Payment::STATUS_VERIFIED,
            'VERIFIED-001',
        );

        try {
            $this->writer->hasVerifiedForBooking($this->bookingId);
            self::fail('Expected a transaction requirement.');
        } catch (DatabaseException $exception) {
            self::assertSame(
                'The verified payment lookup failed.',
                $exception->getMessage(),
            );
        }

        $this->connection->beginTransaction();

        try {
            self::assertTrue(
                $this->writer->hasVerifiedForBooking($this->bookingId),
            );
            self::assertFalse(
                $this->writer->hasVerifiedForBooking(
                    $this->bookingId,
                    $verifiedId,
                ),
            );
            self::assertFalse(
                $this->writer->hasVerifiedForBooking($this->secondBookingId),
            );
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testItMarksSubmittedAttemptsVerifiedOrRejected(): void
    {
        $verifiedId = $this->insertSubmittedPayment(
            $this->bookingId,
            'VERIFY-001',
        );
        $rejectedId = $this->insertSubmittedPayment(
            $this->secondBookingId,
            'REJECT-001',
        );

        $this->connection->beginTransaction();
        $currentVerified = $this->writer->lockCurrent($verifiedId);
        self::assertInstanceOf(Payment::class, $currentVerified);
        $verified = $this->writer->markVerified(
            $currentVerified,
            $this->administratorId,
            'Verified against the transfer record.',
            self::timestamp('2026-07-28 07:00:00'),
        );
        $this->connection->commit();

        self::assertTrue($verified->isVerified());
        self::assertSame($this->administratorId, $verified->verifiedBy());
        self::assertSame(
            '2026-07-28 07:00:00',
            $verified->verifiedAt()?->format('Y-m-d H:i:s'),
        );
        self::assertNull($verified->rejectedAt());
        self::assertNull($verified->rejectedBy());
        self::assertSame(
            'Verified against the transfer record.',
            $verified->verificationNote(),
        );

        $this->connection->beginTransaction();
        $currentRejected = $this->writer->lockCurrent($rejectedId);
        self::assertInstanceOf(Payment::class, $currentRejected);
        $rejected = $this->writer->markRejected(
            $currentRejected,
            $this->administratorId,
            'Reference did not match.',
            self::timestamp('2026-07-28 07:10:00'),
        );
        $this->connection->commit();

        self::assertTrue($rejected->isRejected());
        self::assertSame($this->administratorId, $rejected->rejectedBy());
        self::assertSame(
            '2026-07-28 07:10:00',
            $rejected->rejectedAt()?->format('Y-m-d H:i:s'),
        );
        self::assertNull($rejected->verifiedAt());
        self::assertNull($rejected->verifiedBy());
        self::assertSame('Reference did not match.', $rejected->verificationNote());
    }

    public function testReviewRequiresSubmittedCurrentStateAndRejectsStaleWrites(): void
    {
        $paymentId = $this->insertSubmittedPayment($this->bookingId, 'STALE-001');
        $this->connection->beginTransaction();

        try {
            $current = $this->writer->lockCurrent($paymentId);
            self::assertInstanceOf(Payment::class, $current);

            $this->connection->exec(
                "UPDATE payments SET payment_status = 'rejected' WHERE id = {$paymentId}",
            );

            $this->writer->markVerified(
                $current,
                $this->administratorId,
                'This stale write must fail.',
                self::timestamp('2026-07-28 07:20:00'),
            );
            self::fail('Expected the stale status update to fail.');
        } catch (DatabaseException $exception) {
            self::assertSame(
                'The payment status update failed.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        } finally {
            $this->connection->rollBack();
        }

        self::assertSame(
            Payment::STATUS_SUBMITTED,
            $this->connection->query(
                'SELECT payment_status FROM payments WHERE id = ' . $paymentId,
            )->fetchColumn(),
        );
    }

    public function testWriteDatabaseFailuresExposeOnlyStableMessages(): void
    {
        $paymentId = $this->insertSubmittedPayment($this->bookingId, 'FAIL-001');
        $this->dropTables(['payments']);
        $this->connection->beginTransaction();

        try {
            $this->writer->lockCurrent($paymentId);
            self::fail('Expected a sanitized payment lock failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment lock failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        } finally {
            $this->connection->rollBack();
        }

        $this->connection->beginTransaction();

        try {
            $this->writer->createSubmitted(
                $this->bookingId,
                $this->methodId,
                '10000.00',
                null,
                null,
                null,
                self::timestamp('2026-07-28 07:30:00'),
            );
            self::fail('Expected a sanitized payment write failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment write failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        } finally {
            $this->connection->rollBack();
        }
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) "
            . "VALUES ('Administrator', 'Payment write repository test')",
        );

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(int $roleId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users ('
            . 'role_id, full_name, username, email, password, account_status'
            . ') VALUES ('
            . ':role_id, :full_name, :username, :email, :password, :account_status'
            . ')',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Payment Write Administrator',
            'username' => 'payment.write.admin',
            'email' => 'payment.write.admin@example.test',
            'password' => 'test-only-password-hash',
            'account_status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMethod(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO payment_methods ('
            . 'name, instructions, account_reference, qr_image_path, '
            . 'requires_proof, is_active'
            . ') VALUES ('
            . ':name, :instructions, :account_reference, NULL, '
            . ':requires_proof, :is_active'
            . ')',
        );
        $statement->execute([
            'name' => 'Bank Transfer',
            'instructions' => 'Transfer the exact total.',
            'account_reference' => 'TEST-BANK-ACCOUNT',
            'requires_proof' => 1,
            'is_active' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBooking(string $reference): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, '
            . 'check_in, check_out, adults, children, currency, room_amount, total_amount, '
            . 'booking_status, special_request, terms_accepted_at, policy_version, '
            . 'confirmation_deadline_at, created_at, updated_at'
            . ') VALUES ('
            . ':booking_reference, NULL, :guest_full_name, :guest_email, :guest_phone, '
            . ':check_in, :check_out, :adults, :children, :currency, :room_amount, :total_amount, '
            . ':booking_status, NULL, :terms_accepted_at, :policy_version, '
            . ':confirmation_deadline_at, :created_at, :updated_at'
            . ')',
        );
        $statement->execute([
            'booking_reference' => $reference,
            'guest_full_name' => 'Payment Write Guest',
            'guest_email' => 'payment.write.guest@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'currency' => 'NPR',
            'room_amount' => '10000.00',
            'total_amount' => '10000.00',
            'booking_status' => 'pending',
            'terms_accepted_at' => '2026-07-28 06:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-07-28 12:00:00',
            'created_at' => '2026-07-28 06:00:00',
            'updated_at' => '2026-07-28 06:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertSubmittedPayment(int $bookingId, string $reference): int
    {
        return $this->insertPayment(
            $bookingId,
            Payment::STATUS_SUBMITTED,
            $reference,
            [
                'submitted_at' => '2026-07-28 06:15:00',
            ],
        );
    }

    private function insertReviewedPayment(
        int $bookingId,
        string $status,
        string $reference,
    ): int {
        $reviewColumn = $status === Payment::STATUS_VERIFIED
            ? 'verified'
            : 'rejected';

        return $this->insertPayment(
            $bookingId,
            $status,
            $reference,
            [
                'submitted_at' => '2026-07-28 06:15:00',
                $reviewColumn . '_at' => '2026-07-28 06:30:00',
                $reviewColumn . '_by' => $this->administratorId,
                'verification_note' => 'Reviewed fixture.',
                'updated_at' => '2026-07-28 06:30:00',
            ],
        );
    }

    /**
     * @param array<string, int|string|null> $overrides
     */
    private function insertPayment(
        int $bookingId,
        string $status,
        string $reference,
        array $overrides = [],
    ): int {
        $values = array_replace([
            'booking_id' => $bookingId,
            'payment_method_id' => $this->methodId,
            'currency' => Payment::CURRENCY_NPR,
            'amount' => '10000.00',
            'transaction_reference' => $reference,
            'proof_path' => 'payment-proofs/write-fixture.pdf',
            'guest_notes' => null,
            'payment_status' => $status,
            'submitted_at' => null,
            'verified_at' => null,
            'verified_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'verification_note' => null,
            'created_at' => '2026-07-28 06:15:00',
            'updated_at' => '2026-07-28 06:15:00',
        ], $overrides);
        $statement = $this->connection->prepare(
            'INSERT INTO payments ('
            . 'booking_id, payment_method_id, currency, amount, '
            . 'transaction_reference, proof_path, guest_notes, payment_status, '
            . 'submitted_at, verified_at, verified_by, rejected_at, rejected_by, '
            . 'verification_note, created_at, updated_at'
            . ') VALUES ('
            . ':booking_id, :payment_method_id, :currency, :amount, '
            . ':transaction_reference, :proof_path, :guest_notes, :payment_status, '
            . ':submitted_at, :verified_at, :verified_by, :rejected_at, :rejected_by, '
            . ':verification_note, :created_at, :updated_at'
            . ')',
        );
        $statement->execute($values);

        return (int) $this->connection->lastInsertId();
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
