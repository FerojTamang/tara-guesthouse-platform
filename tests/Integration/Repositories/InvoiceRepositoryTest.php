<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\AdministratorInvoiceRecord;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\PdoInvoiceRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class InvoiceRepositoryTest extends DatabaseTestCase
{
    private PdoInvoiceRepository $repository;
    private int $administratorId;
    private int $bookingId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR . 'database'
                . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $roleId = $this->insertRole();
        $this->administratorId = $this->insertUser($roleId);
        $roomTypeId = $this->insertRoomType();
        $roomId = $this->insertRoom($roomTypeId);
        $methodId = $this->insertMethod();
        $this->bookingId = $this->insertBooking();
        $this->insertBookingRoom($this->bookingId, $roomId);
        $this->insertVerifiedPayment($this->bookingId, $methodId);
        $this->repository = new PdoInvoiceRepository($this->connection);
    }

    public function testItLocksSnapshotsWritesStableInvoiceAndMapsCatalog(): void
    {
        $issuedAt = new DateTimeImmutable('2026-07-30 05:00:00 UTC');
        $this->connection->beginTransaction();

        $booking = $this->repository->lockBooking($this->bookingId);
        $room = $this->repository->lockRoom($this->bookingId);
        $payments = $this->repository->lockVerifiedPayments($this->bookingId);
        self::assertInstanceOf(Booking::class, $booking);
        self::assertSame(Booking::STATUS_CONFIRMED, $booking->bookingStatus());
        self::assertNotNull($room);
        self::assertSame('6000.00', $room->pricePerNight());
        self::assertCount(1, $payments);
        self::assertSame(Payment::STATUS_VERIFIED, $payments[0]->paymentStatus());
        self::assertNull($this->repository->findInvoiceByBookingId($this->bookingId));

        $invoice = $this->repository->createInvoice(
            $this->bookingId,
            Invoice::CURRENCY_NPR,
            '18000.00',
            Invoice::ZERO_TAX,
            '18000.00',
            $issuedAt,
            $this->administratorId,
            2026,
        );
        $item = $this->repository->appendItem(
            $invoice->id(),
            'Accommodation',
            '3.00',
            '6000.00',
            '18000.00',
        );
        $this->connection->commit();

        self::assertMatchesRegularExpression(
            '/^TGH-INV-2026-[0-9]{6}$/D',
            $invoice->invoiceNumber(),
        );
        self::assertSame($invoice->id(), $item->invoiceId());
        self::assertSame(
            $invoice->invoiceNumber(),
            $this->repository->findInvoiceByBookingId($this->bookingId)?->invoiceNumber(),
        );

        $list = $this->repository->findAll();
        self::assertCount(1, $list);
        self::assertSame($invoice->id(), $list[0]->invoice()->id());
        self::assertSame('TGH-2026-INVREPOSITORY001', $list[0]->bookingReference());

        $detail = $this->repository->findById($invoice->id());
        self::assertInstanceOf(AdministratorInvoiceRecord::class, $detail);
        self::assertSame($invoice->invoiceNumber(), $detail->invoice()->invoiceNumber());
        self::assertSame('Invoice Repository Guest', $detail->guestFullName());
        self::assertSame('Bank Transfer', $detail->paymentMethodName());
        self::assertSame('BANK-INVOICE-001', $detail->transactionReference());
        self::assertCount(1, $detail->items());
        self::assertSame('Accommodation', $detail->items()[0]->description());
    }

    public function testWriteLocksFailClosedOutsideCallerTransaction(): void
    {
        foreach ([
            fn () => $this->repository->lockBooking($this->bookingId),
            fn () => $this->repository->lockRoom($this->bookingId),
            fn () => $this->repository->lockVerifiedPayments($this->bookingId),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected caller-owned transaction requirement.');
            } catch (DatabaseException $exception) {
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }
    }

    private function insertRole(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) VALUES ('Administrator', 'Invoice repository test')",
        );

        return (int) $this->connection->lastInsertId();
    }

    private function insertUser(int $roleId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, username, email, password, account_status) '
            . 'VALUES (:role_id, :name, :username, :email, :password, :status)',
        );
        $statement->execute([
            'role_id' => $roleId,
            'name' => 'Invoice Administrator',
            'username' => 'invoice.repository.admin',
            'email' => 'invoice.repository.admin@example.test',
            'password' => 'test-only-password-hash',
            'status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoomType(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_types ('
            . 'name, slug, description, base_price, capacity_adults, capacity_children, is_active'
            . ") VALUES ('Invoice Type', 'invoice-type', NULL, '6000.00', 2, 1, 1)",
        );
        $statement->execute();

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(int $roomTypeId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms ('
            . 'room_type_id, room_number, name, slug, description, price_per_night, '
            . 'capacity_adults, capacity_children, operational_status, is_published'
            . ') VALUES ('
            . ":room_type_id, 'INV-101', 'Invoice Room', 'invoice-room', NULL, '6000.00', "
            . "2, 1, 'active', 1)",
        );
        $statement->execute(['room_type_id' => $roomTypeId]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertMethod(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO payment_methods ('
            . 'name, instructions, account_reference, qr_image_path, requires_proof, is_active'
            . ") VALUES ('Bank Transfer', 'Transfer exact total.', 'INVOICE-BANK', NULL, 0, 1)",
        );
        $statement->execute();

        return (int) $this->connection->lastInsertId();
    }

    private function insertBooking(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, '
            . 'check_in, check_out, adults, children, currency, room_amount, total_amount, '
            . 'booking_status, special_request, terms_accepted_at, policy_version, '
            . 'confirmation_deadline_at, confirmed_at, created_at, updated_at'
            . ') VALUES ('
            . ':reference, NULL, :name, :email, :phone, :check_in, :check_out, 2, 0, '
            . "'NPR', '18000.00', '18000.00', 'confirmed', NULL, :terms, 'v1', "
            . ':deadline, :confirmed, :created, :updated)',
        );
        $statement->execute([
            'reference' => 'TGH-2026-INVREPOSITORY001',
            'name' => 'Invoice Repository Guest',
            'email' => 'invoice.repository.guest@example.test',
            'phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-13',
            'terms' => '2026-07-30 03:00:00',
            'deadline' => '2026-07-30 05:00:00',
            'confirmed' => '2026-07-30 04:00:00',
            'created' => '2026-07-30 03:00:00',
            'updated' => '2026-07-30 04:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertBookingRoom(int $bookingId, int $roomId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO booking_rooms ('
            . 'booking_id, room_id, price_per_night, nights, subtotal, created_at'
            . ") VALUES (:booking_id, :room_id, '6000.00', 3, '18000.00', :created_at)",
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'room_id' => $roomId,
            'created_at' => '2026-07-30 03:00:00',
        ]);
    }

    private function insertVerifiedPayment(int $bookingId, int $methodId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO payments ('
            . 'booking_id, payment_method_id, currency, amount, transaction_reference, '
            . 'proof_path, guest_notes, payment_status, submitted_at, verified_at, '
            . 'verified_by, verification_note, created_at, updated_at'
            . ') VALUES ('
            . ":booking_id, :method_id, 'NPR', '18000.00', 'BANK-INVOICE-001', "
            . "NULL, NULL, 'verified', :submitted, :verified, :verified_by, "
            . "'Verified for invoice.', :created, :updated)",
        );
        $statement->execute([
            'booking_id' => $bookingId,
            'method_id' => $methodId,
            'submitted' => '2026-07-30 03:10:00',
            'verified' => '2026-07-30 04:00:00',
            'verified_by' => $this->administratorId,
            'created' => '2026-07-30 03:10:00',
            'updated' => '2026-07-30 04:00:00',
        ]);
    }
}
