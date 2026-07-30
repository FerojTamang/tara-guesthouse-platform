<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\AdministratorPaymentRecord;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Repositories\PdoAdministratorPaymentRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PaymentRepositoryTest extends DatabaseTestCase
{
    private PaymentRepository $repository;

    private int $administratorId;

    private int $methodId;

    private int $firstBookingId;

    private int $secondBookingId;

    /** @var array<string, int> */
    private array $paymentIds = [];

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
        $this->firstBookingId = $this->insertBooking('TGH-PAY-READ-001');
        $this->secondBookingId = $this->insertBooking('TGH-PAY-READ-002');

        $this->paymentIds['submitted'] = $this->insertPayment(
            $this->firstBookingId,
            Payment::STATUS_SUBMITTED,
            '2026-07-28 06:10:00',
            [
                'transaction_reference' => 'BANK-READ-001',
                'proof_path' => 'payment-proofs/read-001.pdf',
                'guest_notes' => 'First submitted attempt.',
                'submitted_at' => '2026-07-28 06:10:00',
            ],
        );
        $this->paymentIds['rejected'] = $this->insertPayment(
            $this->firstBookingId,
            Payment::STATUS_REJECTED,
            '2026-07-28 06:20:00',
            [
                'transaction_reference' => 'BANK-READ-002',
                'submitted_at' => '2026-07-28 06:20:00',
                'rejected_at' => '2026-07-28 06:30:00',
                'rejected_by' => $this->administratorId,
                'verification_note' => 'Reference did not match.',
                'updated_at' => '2026-07-28 06:30:00',
            ],
        );
        $this->paymentIds['verified'] = $this->insertPayment(
            $this->secondBookingId,
            Payment::STATUS_VERIFIED,
            '2026-07-28 06:40:00',
            [
                'transaction_reference' => 'BANK-READ-003',
                'submitted_at' => '2026-07-28 06:40:00',
                'verified_at' => '2026-07-28 06:50:00',
                'verified_by' => $this->administratorId,
                'verification_note' => 'Verified against the transfer record.',
                'updated_at' => '2026-07-28 06:50:00',
            ],
        );

        $this->repository = new PaymentRepository($this->connection);
    }

    public function testItFindsAndMapsTheCompletePaymentAttempt(): void
    {
        $payment = $this->repository->findById($this->paymentIds['submitted']);

        self::assertInstanceOf(Payment::class, $payment);
        self::assertSame($this->paymentIds['submitted'], $payment->id());
        self::assertSame($this->firstBookingId, $payment->bookingId());
        self::assertSame($this->methodId, $payment->paymentMethodId());
        self::assertSame(Payment::CURRENCY_NPR, $payment->currency());
        self::assertSame('10000.00', $payment->amount());
        self::assertSame('BANK-READ-001', $payment->transactionReference());
        self::assertSame('payment-proofs/read-001.pdf', $payment->proofPath());
        self::assertSame('First submitted attempt.', $payment->guestNotes());
        self::assertSame(Payment::STATUS_SUBMITTED, $payment->paymentStatus());
        self::assertEquals(
            new DateTimeImmutable('2026-07-28 06:10:00 UTC'),
            $payment->submittedAt(),
        );
        self::assertNull($payment->verifiedAt());
        self::assertNull($payment->verifiedBy());
        self::assertNull($payment->rejectedAt());
        self::assertNull($payment->rejectedBy());
        self::assertNull($payment->verificationNote());
        self::assertSame(0, $payment->createdAt()->getOffset());
        self::assertSame(0, $payment->updatedAt()->getOffset());
    }

    public function testItListsNewestFirstAndFiltersOnlyCanonicalStatuses(): void
    {
        $all = $this->repository->findAll();

        self::assertSame(
            [
                $this->paymentIds['verified'],
                $this->paymentIds['rejected'],
                $this->paymentIds['submitted'],
            ],
            array_map(
                static fn (Payment $payment): int => $payment->id(),
                $all,
            ),
        );

        $submitted = $this->repository->findAll(Payment::STATUS_SUBMITTED);
        self::assertCount(1, $submitted);
        self::assertSame($this->paymentIds['submitted'], $submitted[0]->id());

        $verified = $this->repository->findAll(Payment::STATUS_VERIFIED);
        self::assertCount(1, $verified);
        self::assertSame($this->paymentIds['verified'], $verified[0]->id());

        self::assertSame([], $this->repository->findAll('SUBMITTED'));
        self::assertSame([], $this->repository->findAll('completed'));
    }

    public function testItListsRetainedAttemptsForOneBookingNewestFirst(): void
    {
        $payments = $this->repository->findAllByBookingId($this->firstBookingId);

        self::assertSame(
            [$this->paymentIds['rejected'], $this->paymentIds['submitted']],
            array_map(
                static fn (Payment $payment): int => $payment->id(),
                $payments,
            ),
        );
        self::assertSame(
            [Payment::STATUS_REJECTED, Payment::STATUS_SUBMITTED],
            array_map(
                static fn (Payment $payment): string => $payment->paymentStatus(),
                $payments,
            ),
        );
    }

    public function testAdministratorReadRepositoryMapsJoinedReviewContext(): void
    {
        $repository = new PdoAdministratorPaymentRepository($this->connection);
        $submitted = $repository->findAll(Payment::STATUS_SUBMITTED);

        self::assertCount(1, $submitted);
        self::assertInstanceOf(AdministratorPaymentRecord::class, $submitted[0]);
        self::assertSame($this->paymentIds['submitted'], $submitted[0]->payment()->id());
        self::assertSame('TGH-PAY-READ-001', $submitted[0]->bookingReference());
        self::assertSame('Payment Repository Guest', $submitted[0]->guestFullName());
        self::assertSame('Bank Transfer', $submitted[0]->paymentMethodName());
        self::assertSame('TEST-BANK-ACCOUNT', $submitted[0]->paymentMethodAccountReference());
        self::assertTrue($submitted[0]->paymentMethodRequiresProof());
        self::assertTrue($submitted[0]->paymentMethodActive());
        self::assertSame('10000.00', $submitted[0]->bookingTotalAmount());
        self::assertSame('2026-08-10', $submitted[0]->checkIn()->format('Y-m-d'));
        self::assertSame('2026-08-12', $submitted[0]->checkOut()->format('Y-m-d'));

        $detail = $repository->findById($this->paymentIds['verified']);
        self::assertInstanceOf(AdministratorPaymentRecord::class, $detail);
        self::assertSame(Payment::STATUS_VERIFIED, $detail->payment()->paymentStatus());
        self::assertSame($this->administratorId, $detail->payment()->verifiedBy());
        self::assertNull($repository->findById(0));
        self::assertNull($repository->findById(999999));
        self::assertSame([], $repository->findAll('completed'));
    }

    public function testItFindsTheQualifyingVerifiedPaymentForAConfirmedWorkflow(): void
    {
        $verified = $this->repository->findVerifiedByBookingId($this->secondBookingId);

        self::assertInstanceOf(Payment::class, $verified);
        self::assertSame($this->paymentIds['verified'], $verified->id());
        self::assertTrue($verified->isVerified());
        self::assertSame($this->administratorId, $verified->verifiedBy());
        self::assertSame(
            'Verified against the transfer record.',
            $verified->verificationNote(),
        );
        self::assertNull(
            $this->repository->findVerifiedByBookingId($this->firstBookingId),
        );
    }

    public function testInvalidAndMissingLookupsReturnEmptyResults(): void
    {
        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(999999));
        self::assertSame([], $this->repository->findAllByBookingId(0));
        self::assertSame([], $this->repository->findAllByBookingId(999999));
        self::assertNull($this->repository->findVerifiedByBookingId(0));
        self::assertNull($this->repository->findVerifiedByBookingId(999999));
    }

    public function testDatabaseFailuresExposeOnlyStableMessages(): void
    {
        $administratorRepository = new PdoAdministratorPaymentRepository(
            $this->connection,
        );
        $this->dropTables(['payments']);

        try {
            $this->repository->findById($this->paymentIds['submitted']);
            self::fail('Expected a sanitized payment lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->findAll();
            self::fail('Expected a sanitized payment listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->findAllByBookingId($this->firstBookingId);
            self::fail('Expected a sanitized booking-payment listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The booking payment listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->findVerifiedByBookingId($this->secondBookingId);
            self::fail('Expected a sanitized verified-payment lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The verified payment lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $administratorRepository->findAll();
            self::fail('Expected a sanitized Administrator payment listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The Administrator payment listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $administratorRepository->findById($this->paymentIds['submitted']);
            self::fail('Expected a sanitized Administrator payment lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The Administrator payment lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) "
            . "VALUES ('Administrator', 'Payment repository test')",
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
            'full_name' => 'Payment Repository Administrator',
            'username' => 'payment.repository.admin',
            'email' => 'payment.repository.admin@example.test',
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
            'guest_full_name' => 'Payment Repository Guest',
            'guest_email' => 'payment.repository.guest@example.test',
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

    /**
     * @param array<string, int|string|null> $overrides
     */
    private function insertPayment(
        int $bookingId,
        string $status,
        string $createdAt,
        array $overrides = [],
    ): int {
        $values = array_replace([
            'booking_id' => $bookingId,
            'payment_method_id' => $this->methodId,
            'currency' => Payment::CURRENCY_NPR,
            'amount' => '10000.00',
            'transaction_reference' => null,
            'proof_path' => null,
            'guest_notes' => null,
            'payment_status' => $status,
            'submitted_at' => null,
            'verified_at' => null,
            'verified_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'verification_note' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
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
}
