<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\AdministratorPaymentException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoBookingStatusWriteRepository;
use App\Repositories\PdoPaymentWriteRepository;
use App\Requests\AdministratorPaymentStatusRequest;
use App\Services\AdministratorPaymentStatusService;
use App\Services\PdoBookingTransaction;
use App\Services\VerifiedPaymentBookingConfirmationService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorPaymentStatusServiceIntegrationTest extends DatabaseTestCase
{
    private int $administratorId;

    private int $methodId;

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
    }

    public function testVerificationCommitsPaymentBookingEventAndBothAudits(): void
    {
        $bookingId = $this->insertBooking('TGH-PAY-STATUS-VERIFY-001');
        $paymentId = $this->insertSubmittedPayment($bookingId, 'VERIFY-001');

        $result = $this->service()->transition(
            $paymentId,
            $this->request(Payment::STATUS_VERIFIED, 'Transfer and proof confirmed.'),
            $this->administratorId,
            '203.0.113.81',
            'Payment Integration Agent',
        );

        self::assertSame($paymentId, $result->paymentId());
        self::assertSame(Payment::STATUS_VERIFIED, $result->toStatus());
        self::assertFalse($this->connection->inTransaction());

        $payment = $this->paymentRow($paymentId);
        self::assertSame(Payment::STATUS_VERIFIED, $payment['payment_status']);
        self::assertSame((string) $this->administratorId, (string) $payment['verified_by']);
        self::assertSame('2026-07-30 04:00:00', $payment['verified_at']);
        self::assertNull($payment['rejected_at']);
        self::assertNull($payment['rejected_by']);
        self::assertSame('Transfer and proof confirmed.', $payment['verification_note']);

        $booking = $this->bookingRow($bookingId);
        self::assertSame(Booking::STATUS_CONFIRMED, $booking['booking_status']);
        self::assertSame('2026-07-30 04:00:00', $booking['confirmed_at']);

        $event = $this->bookingEventRow($bookingId);
        self::assertSame('booking_confirmed', $event['event_type']);
        self::assertSame(Booking::STATUS_PENDING, $event['from_status']);
        self::assertSame(Booking::STATUS_CONFIRMED, $event['to_status']);
        self::assertSame((string) $this->administratorId, (string) $event['performed_by']);
        self::assertSame(
            ['payment_id' => $paymentId],
            json_decode((string) $event['metadata_json'], true, 16, JSON_THROW_ON_ERROR),
        );

        $audit = $this->auditRow($paymentId);
        self::assertSame('payment_verified', $audit['event_type']);
        self::assertSame((string) $this->administratorId, (string) $audit['user_id']);
        self::assertSame('203.0.113.81', $audit['ip_address']);
        self::assertSame('Payment Integration Agent', $audit['user_agent']);
        self::assertSame([
            'booking_id' => $bookingId,
            'from_status' => Payment::STATUS_SUBMITTED,
            'to_status' => Payment::STATUS_VERIFIED,
        ], json_decode(
            (string) $audit['metadata_json'],
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
        self::assertStringNotContainsString(
            'Transfer and proof confirmed.',
            (string) $audit['metadata_json'],
        );

        $bookingAudit = $this->bookingAuditRow($bookingId);
        self::assertSame('booking_confirmed_from_payment', $bookingAudit['event_type']);
        self::assertSame([
            'payment_id' => $paymentId,
            'from_status' => Booking::STATUS_PENDING,
            'to_status' => Booking::STATUS_CONFIRMED,
        ], json_decode(
            (string) $bookingAudit['metadata_json'],
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
    }

    public function testDuplicateVerificationIsPreventedTransactionally(): void
    {
        $bookingId = $this->insertBooking('TGH-PAY-STATUS-DUPLICATE-01');
        $firstId = $this->insertSubmittedPayment($bookingId, 'DUPLICATE-001');
        $secondId = $this->insertSubmittedPayment($bookingId, 'DUPLICATE-002');
        $service = $this->service();

        $service->transition(
            $firstId,
            $this->request(Payment::STATUS_VERIFIED, 'First qualifying attempt.'),
            $this->administratorId,
            null,
            null,
        );

        try {
            $service->transition(
                $secondId,
                $this->request(Payment::STATUS_VERIFIED, 'Duplicate attempt.'),
                $this->administratorId,
                null,
                null,
            );
            self::fail('Expected duplicate verification to be rejected.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertFalse($this->connection->inTransaction());
        self::assertSame(
            Payment::STATUS_VERIFIED,
            $this->paymentRow($firstId)['payment_status'],
        );
        self::assertSame(
            Payment::STATUS_SUBMITTED,
            $this->paymentRow($secondId)['payment_status'],
        );
        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM payments WHERE booking_id = {$bookingId} "
                . "AND payment_status = 'verified'",
            )->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'payment_verified'",
            )->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'booking_confirmed_from_payment'",
            )->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM booking_events WHERE event_type = 'booking_confirmed'",
            )->fetchColumn(),
        );
    }

    public function testRejectionChangesOnlyAttemptAndAuditFailureRollsBack(): void
    {
        $bookingId = $this->insertBooking('TGH-PAY-STATUS-REJECT-001');
        $rejectedId = $this->insertSubmittedPayment($bookingId, 'REJECT-001');

        $this->service()->transition(
            $rejectedId,
            $this->request(Payment::STATUS_REJECTED, 'Reference did not match.'),
            $this->administratorId,
            null,
            null,
        );

        self::assertSame(
            Payment::STATUS_REJECTED,
            $this->paymentRow($rejectedId)['payment_status'],
        );
        self::assertSame(
            Booking::STATUS_PENDING,
            $this->connection->query(
                'SELECT booking_status FROM bookings WHERE id = ' . $bookingId,
            )->fetchColumn(),
        );
        self::assertSame('payment_rejected', $this->auditRow($rejectedId)['event_type']);
        self::assertSame(
            0,
            (int) $this->connection->query(
                'SELECT COUNT(*) FROM booking_events WHERE booking_id = ' . $bookingId,
            )->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $this->connection->query(
                "SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'booking' "
                . 'AND entity_id = ' . $bookingId,
            )->fetchColumn(),
        );

        $rollbackBookingId = $this->insertBooking('TGH-PAY-STATUS-ROLLBACK-01');
        $rollbackPaymentId = $this->insertSubmittedPayment(
            $rollbackBookingId,
            'ROLLBACK-001',
        );
        $failingAudit = new FailingPaymentStatusAuditRepository();
        $service = new AdministratorPaymentStatusService(
            new PdoPaymentWriteRepository($this->connection),
            $failingAudit,
            new VerifiedPaymentBookingConfirmationService(
                new PdoBookingStatusWriteRepository($this->connection),
                $failingAudit,
            ),
            new PdoBookingTransaction($this->connection),
            self::clock(...),
        );

        try {
            $service->transition(
                $rollbackPaymentId,
                $this->request(Payment::STATUS_REJECTED, 'Must roll back.'),
                $this->administratorId,
                null,
                null,
            );
            self::fail('Expected the audit write failure.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('audit failure detail', $exception->getMessage());
        }

        self::assertFalse($this->connection->inTransaction());
        $rolledBack = $this->paymentRow($rollbackPaymentId);
        self::assertSame(Payment::STATUS_SUBMITTED, $rolledBack['payment_status']);
        self::assertNull($rolledBack['rejected_at']);
        self::assertNull($rolledBack['rejected_by']);
        self::assertNull($rolledBack['verification_note']);
    }

    public function testBookingAuditFailureRollsBackPaymentBookingAndEvent(): void
    {
        $bookingId = $this->insertBooking('TGH-PAY-STATUS-ATOMIC-001');
        $paymentId = $this->insertSubmittedPayment($bookingId, 'ATOMIC-001');
        $failingAudit = new FailingPaymentStatusAuditRepository();
        $service = new AdministratorPaymentStatusService(
            new PdoPaymentWriteRepository($this->connection),
            $failingAudit,
            new VerifiedPaymentBookingConfirmationService(
                new PdoBookingStatusWriteRepository($this->connection),
                $failingAudit,
            ),
            new PdoBookingTransaction($this->connection),
            self::clock(...),
        );

        try {
            $service->transition(
                $paymentId,
                $this->request(Payment::STATUS_VERIFIED, 'Atomic verification.'),
                $this->administratorId,
                null,
                null,
            );
            self::fail('Expected booking audit failure.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertSame(
            Payment::STATUS_SUBMITTED,
            $this->paymentRow($paymentId)['payment_status'],
        );
        $booking = $this->bookingRow($bookingId);
        self::assertSame(Booking::STATUS_PENDING, $booking['booking_status']);
        self::assertNull($booking['confirmed_at']);
        self::assertSame(
            0,
            (int) $this->connection->query(
                'SELECT COUNT(*) FROM booking_events WHERE booking_id = ' . $bookingId,
            )->fetchColumn(),
        );
        self::assertSame(0, (int) $this->connection->query(
            'SELECT COUNT(*) FROM audit_logs',
        )->fetchColumn());
    }

    private function service(): AdministratorPaymentStatusService
    {
        $auditLogs = new PdoAuditLogRepository($this->connection);

        return new AdministratorPaymentStatusService(
            new PdoPaymentWriteRepository($this->connection),
            $auditLogs,
            new VerifiedPaymentBookingConfirmationService(
                new PdoBookingStatusWriteRepository($this->connection),
                $auditLogs,
            ),
            new PdoBookingTransaction($this->connection),
            self::clock(...),
        );
    }

    private function request(
        string $status,
        string $note,
    ): AdministratorPaymentStatusRequest {
        return AdministratorPaymentStatusRequest::fromInput($status, $note, true);
    }

    private static function clock(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-30 04:00:00 UTC');
    }

    /** @return array<string, mixed> */
    private function paymentRow(int $paymentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT payment_status, verified_at, verified_by, rejected_at, '
            . 'rejected_by, verification_note FROM payments WHERE id = :payment_id',
        );
        $statement->execute(['payment_id' => $paymentId]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function bookingRow(int $bookingId): array
    {
        $statement = $this->connection->prepare(
            'SELECT booking_status, confirmed_at FROM bookings WHERE id = :booking_id',
        );
        $statement->execute(['booking_id' => $bookingId]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function bookingEventRow(int $bookingId): array
    {
        $statement = $this->connection->prepare(
            'SELECT event_type, from_status, to_status, performed_by, metadata_json '
            . 'FROM booking_events WHERE booking_id = :booking_id ORDER BY id DESC LIMIT 1',
        );
        $statement->execute(['booking_id' => $bookingId]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function bookingAuditRow(int $bookingId): array
    {
        $statement = $this->connection->prepare(
            'SELECT event_type, metadata_json FROM audit_logs '
            . "WHERE entity_type = 'booking' AND entity_id = :booking_id "
            . 'ORDER BY id DESC LIMIT 1',
        );
        $statement->execute(['booking_id' => $bookingId]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function auditRow(int $paymentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT user_id, event_type, entity_type, entity_id, metadata_json, '
            . 'ip_address, user_agent FROM audit_logs '
            . "WHERE entity_type = 'payment' AND entity_id = :payment_id "
            . 'ORDER BY id DESC LIMIT 1',
        );
        $statement->execute(['payment_id' => $paymentId]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        return $row;
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) "
            . "VALUES ('Administrator', 'Payment status integration test')",
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
            'full_name' => 'Payment Status Administrator',
            'username' => 'payment.status.admin',
            'email' => 'payment.status.admin@example.test',
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
            . ':name, :instructions, :account_reference, NULL, 1, 1'
            . ')',
        );
        $statement->execute([
            'name' => 'Bank Transfer',
            'instructions' => 'Transfer the exact total.',
            'account_reference' => 'STATUS-TEST-BANK',
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
            . ':check_in, :check_out, 2, 0, :currency, :room_amount, :total_amount, '
            . ':booking_status, NULL, :terms_accepted_at, :policy_version, '
            . ':confirmation_deadline_at, :created_at, :updated_at'
            . ')',
        );
        $statement->execute([
            'booking_reference' => $reference,
            'guest_full_name' => 'Payment Status Guest',
            'guest_email' => 'payment.status.guest@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'currency' => Payment::CURRENCY_NPR,
            'room_amount' => '10000.00',
            'total_amount' => '10000.00',
            'booking_status' => Booking::STATUS_PENDING,
            'terms_accepted_at' => '2026-07-30 03:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-07-30 05:00:00',
            'created_at' => '2026-07-30 03:00:00',
            'updated_at' => '2026-07-30 03:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertSubmittedPayment(int $bookingId, string $reference): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO payments ('
            . 'booking_id, payment_method_id, currency, amount, '
            . 'transaction_reference, proof_path, guest_notes, payment_status, '
            . 'submitted_at, created_at, updated_at'
            . ') VALUES ('
            . ':booking_id, :payment_method_id, :currency, :amount, '
            . ':transaction_reference, NULL, NULL, :payment_status, '
            . ':submitted_at, :created_at, :updated_at'
            . ')',
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'payment_method_id' => $this->methodId,
            'currency' => Payment::CURRENCY_NPR,
            'amount' => '10000.00',
            'transaction_reference' => $reference,
            'payment_status' => Payment::STATUS_SUBMITTED,
            'submitted_at' => '2026-07-30 03:15:00',
            'created_at' => '2026-07-30 03:15:00',
            'updated_at' => '2026-07-30 03:15:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }
}

final class FailingPaymentStatusAuditRepository implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new RuntimeException('audit failure detail');
    }
}
