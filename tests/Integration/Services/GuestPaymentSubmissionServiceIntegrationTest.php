<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Core\UploadedFile;
use App\Exceptions\GuestPaymentException;
use App\Models\Payment;
use App\Repositories\BookingRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PdoPaymentWriteRepository;
use App\Requests\GuestPaymentRequest;
use App\Services\BookingAccessToken;
use App\Services\GuestPaymentAccessValidator;
use App\Services\GuestPaymentSubmissionService;
use App\Services\PdoBookingTransaction;
use App\Storage\LocalPaymentProofStorage;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class GuestPaymentSubmissionServiceIntegrationTest extends DatabaseTestCase
{
    private const KEY = 'guest-payment-integration-key-32-bytes-minimum';

    private ?string $proofDirectory = null;

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var array<string, mixed> */
    private array $proofPolicy;

    private int $methodId;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $this->methodId = $this->insertMethod();
        $this->insertBooking('TGH-PAY-SUBMIT-001');
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $this->proofPolicy = $configuration['payment_proofs'];
        $this->proofDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-submission-proof-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->proofDirectory, 0700));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        if ($this->proofDirectory !== null) {
            foreach (glob($this->proofDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($this->proofDirectory);
        }
        parent::tearDown();
    }

    public function testItCommitsSeparateSubmittedRowsAndPrivateProofs(): void
    {
        $service = $this->service();
        $request = $this->request('TGH-PAY-SUBMIT-001');

        $first = $service->submit($request);
        $second = $service->submit($request);

        self::assertNotSame($first->id(), $second->id());
        self::assertTrue($first->isSubmitted());
        self::assertTrue($second->isSubmitted());
        self::assertNotSame($first->proofPath(), $second->proofPath());
        self::assertSame(
            2,
            (int) $this->connection->query('SELECT COUNT(*) FROM payments')->fetchColumn(),
        );

        foreach ([$first, $second] as $payment) {
            self::assertSame(Payment::CURRENCY_NPR, $payment->currency());
            self::assertSame('10000.00', $payment->amount());
            self::assertSame('BANK-INTEGRATION-001', $payment->transactionReference());
            self::assertMatchesRegularExpression(
                '/^payment-proofs\/[a-f0-9]{64}\.pdf$/D',
                (string) $payment->proofPath(),
            );
            $filename = basename((string) $payment->proofPath());
            self::assertFileExists($this->proofDirectory . DIRECTORY_SEPARATOR . $filename);
        }
    }

    public function testDatabaseFailureRollsBackAndRemovesTheStoredProof(): void
    {
        $service = $this->service();
        $request = $this->request('TGH-PAY-SUBMIT-001');
        $this->dropTables(['payments']);

        try {
            $service->submit($request);
            self::fail('Expected the payment insert failure.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Payment operation failed.', $exception->getMessage());
        }

        self::assertSame(
            [],
            glob($this->proofDirectory . DIRECTORY_SEPARATOR . '*') ?: [],
        );
        self::assertFalse($this->connection->inTransaction());
    }

    private function service(): GuestPaymentSubmissionService
    {
        $bookings = new BookingRepository($this->connection);
        $methods = new PaymentMethodRepository($this->connection);
        $tokens = new BookingAccessToken(self::KEY);
        $clock = static fn (): DateTimeImmutable => new DateTimeImmutable(
            '2026-08-01 12:00:00 UTC',
        );

        return new GuestPaymentSubmissionService(
            new GuestPaymentAccessValidator(
                $bookings,
                $methods,
                $tokens,
                $clock,
            ),
            $tokens,
            new PdoPaymentWriteRepository($this->connection),
            new LocalPaymentProofStorage($this->proofDirectory, 5_242_880),
            new PdoBookingTransaction($this->connection),
            $clock,
        );
    }

    private function request(string $bookingReference): GuestPaymentRequest
    {
        $deadline = new DateTimeImmutable('2026-08-01 18:00:00 UTC');
        $token = (new BookingAccessToken(self::KEY))->issue(
            $bookingReference,
            $deadline,
        );
        $path = tempnam(sys_get_temp_dir(), 'tgh-payment-integration-upload-');
        self::assertIsString($path);
        $contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
        self::assertSame(strlen($contents), file_put_contents($path, $contents));
        $this->temporaryFiles[] = $path;

        return GuestPaymentRequest::fromInput(
            $bookingReference,
            $token,
            $this->methodId,
            '10000.00',
            'BANK-INTEGRATION-001',
            'Integration payment proof.',
            new UploadedFile(
                'proof.pdf',
                'application/pdf',
                $path,
                UPLOAD_ERR_OK,
                strlen($contents),
            ),
            $this->proofPolicy,
        );
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
            'account_reference' => 'TEST-BANK-ACCOUNT',
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
            'guest_full_name' => 'Payment Integration Guest',
            'guest_email' => 'payment.integration@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'currency' => Payment::CURRENCY_NPR,
            'room_amount' => '10000.00',
            'total_amount' => '10000.00',
            'booking_status' => 'pending',
            'terms_accepted_at' => '2026-08-01 10:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-08-01 18:00:00',
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
