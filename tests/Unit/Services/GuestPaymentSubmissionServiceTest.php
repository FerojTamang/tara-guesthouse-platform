<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\UploadedFile;
use App\Exceptions\DatabaseException;
use App\Exceptions\GuestPaymentException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Repositories\PaymentWriteRepository;
use App\Requests\GuestPaymentRequest;
use App\Services\BookingAccessToken;
use App\Services\BookingTransaction;
use App\Services\GuestPaymentAccessValidator;
use App\Services\GuestPaymentSubmissionService;
use App\Storage\PaymentProofStorage;
use App\Storage\StoredPaymentProof;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GuestPaymentSubmissionServiceTest extends TestCase
{
    private const KEY = 'guest-payment-submission-test-key-32-bytes';

    /** @var array<string, mixed> */
    private array $proofPolicy;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $this->proofPolicy = $configuration['payment_proofs'];
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
    }

    public function testItLocksRechecksAndCommitsOneSubmittedAttempt(): void
    {
        $booking = $this->booking();
        $method = $this->method();
        $writer = new GuestPaymentWriteRepositoryFake($booking, $method);
        $storage = new GuestPaymentProofStorageFake();
        $transaction = new GuestPaymentTransactionFake();
        $service = $this->service($booking, $method, $writer, $storage, $transaction);

        $payment = $service->submit($this->request($booking));

        self::assertSame(44, $payment->id());
        self::assertTrue($payment->isSubmitted());
        self::assertSame($booking->id(), $payment->bookingId());
        self::assertSame($method->id(), $payment->paymentMethodId());
        self::assertSame('18000.00', $payment->amount());
        self::assertSame('BANK-001', $payment->transactionReference());
        self::assertSame('Transferred today.', $payment->guestNotes());
        self::assertNull($payment->proofPath());
        self::assertSame(['begin', 'commit'], $transaction->events);
        self::assertSame(
            ['lock-booking', 'lock-method', 'create'],
            $writer->events,
        );
        self::assertSame(1, $transaction->commits);
        self::assertSame(0, $transaction->rollbacks);
        self::assertSame([], $storage->removedPaths);
    }

    public function testEachSuccessfulSubmissionCreatesASeparateRetainedAttempt(): void
    {
        $booking = $this->booking();
        $method = $this->method();
        $writer = new GuestPaymentWriteRepositoryFake($booking, $method);
        $service = $this->service(
            $booking,
            $method,
            $writer,
            new GuestPaymentProofStorageFake(),
            new GuestPaymentTransactionFake(),
        );

        $first = $service->submit($this->request($booking));
        $second = $service->submit($this->request($booking));

        self::assertNotSame($first->id(), $second->id());
        self::assertSame(2, $writer->createCalls);
    }

    public function testLockedBookingAndMethodFactsAreRecheckedBeforeProofStorage(): void
    {
        $initialBooking = $this->booking();
        $initialMethod = $this->method();
        $cases = [
            [
                $this->booking(status: Booking::STATUS_CONFIRMED),
                $initialMethod,
                'conflict',
            ],
            [
                $this->booking(totalAmount: '19000.00'),
                $initialMethod,
                'validation',
            ],
            [
                $initialBooking,
                $this->method(active: false),
                'conflict',
            ],
            [
                $initialBooking,
                $this->method(requiresProof: true),
                'validation',
            ],
        ];

        foreach ($cases as [$lockedBooking, $lockedMethod, $category]) {
            $writer = new GuestPaymentWriteRepositoryFake($lockedBooking, $lockedMethod);
            $storage = new GuestPaymentProofStorageFake();
            $transaction = new GuestPaymentTransactionFake();
            $service = $this->service(
                $initialBooking,
                $initialMethod,
                $writer,
                $storage,
                $transaction,
            );

            try {
                $service->submit($this->request($initialBooking));
                self::fail('Changed locked payment facts were accepted.');
            } catch (GuestPaymentException $exception) {
                self::assertSame(
                    $category === 'validation',
                    $exception->isValidationFailure(),
                );
                self::assertSame(
                    $category === 'conflict',
                    $exception->isConflict(),
                );
            }

            self::assertSame(0, $writer->createCalls);
            self::assertSame(1, $transaction->rollbacks);
            self::assertSame(0, $storage->storeCalls);
        }
    }

    public function testDeadlineIsRecheckedInsideTheTransaction(): void
    {
        $booking = $this->booking(
            confirmationDeadlineAt: self::timestamp('2026-08-01 12:00:00'),
        );
        $method = $this->method();
        $writer = new GuestPaymentWriteRepositoryFake($booking, $method);
        $storage = new GuestPaymentProofStorageFake();
        $transaction = new GuestPaymentTransactionFake();
        $times = [
            self::timestamp('2026-08-01 11:59:59'),
            self::timestamp('2026-08-01 12:00:00'),
        ];
        $clock = static function () use (&$times): DateTimeImmutable {
            $current = array_shift($times);

            if (!$current instanceof DateTimeImmutable) {
                throw new RuntimeException('The test clock was exhausted.');
            }

            return $current;
        };
        $service = $this->service(
            $booking,
            $method,
            $writer,
            $storage,
            $transaction,
            $clock,
        );

        try {
            $service->submit($this->request($booking));
            self::fail('The expired locked booking accepted a payment.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isAccessDenied());
        }

        self::assertSame(0, $writer->createCalls);
        self::assertSame(1, $transaction->rollbacks);
        self::assertSame(0, $storage->storeCalls);
    }

    public function testStoredProofIsRemovedWhenTheDatabaseWriteRollsBack(): void
    {
        $booking = $this->booking();
        $method = $this->method(requiresProof: true);
        $writer = new GuestPaymentWriteRepositoryFake($booking, $method);
        $writer->createFailure = new DatabaseException('SQLSTATE private details');
        $storage = new GuestPaymentProofStorageFake();
        $transaction = new GuestPaymentTransactionFake();
        $service = $this->service($booking, $method, $writer, $storage, $transaction);

        try {
            $service->submit($this->request($booking, withProof: true));
            self::fail('Expected the payment write failure.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Payment operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        self::assertSame(1, $storage->storeCalls);
        self::assertSame(
            ['payment-proofs/' . str_repeat('a', 64) . '.pdf'],
            $storage->removedPaths,
        );
        self::assertSame(1, $transaction->rollbacks);
        self::assertSame(0, $transaction->commits);
    }

    public function testCommitFailureRollsBackAndCleansTheStoredProof(): void
    {
        $booking = $this->booking();
        $method = $this->method(requiresProof: true);
        $writer = new GuestPaymentWriteRepositoryFake($booking, $method);
        $storage = new GuestPaymentProofStorageFake();
        $transaction = new GuestPaymentTransactionFake();
        $transaction->commitFailure = new RuntimeException('commit details');
        $service = $this->service($booking, $method, $writer, $storage, $transaction);

        try {
            $service->submit($this->request($booking, withProof: true));
            self::fail('Expected the commit failure.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertSame(1, $transaction->rollbacks);
        self::assertCount(1, $storage->removedPaths);
    }

    private function service(
        Booking $initialBooking,
        PaymentMethod $initialMethod,
        GuestPaymentWriteRepositoryFake $writer,
        GuestPaymentProofStorageFake $storage,
        GuestPaymentTransactionFake $transaction,
        ?callable $clock = null,
    ): GuestPaymentSubmissionService {
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($initialBooking);
        $methods = $this->createStub(PaymentMethodCatalogRepository::class);
        $methods->method('findById')->willReturn($initialMethod);
        $tokens = new BookingAccessToken(self::KEY);
        $clock ??= static fn (): DateTimeImmutable => self::timestamp(
            '2026-08-01 12:05:00',
        );

        return new GuestPaymentSubmissionService(
            new GuestPaymentAccessValidator(
                $bookings,
                $methods,
                $tokens,
                $clock,
            ),
            $tokens,
            $writer,
            $storage,
            $transaction,
            $clock,
        );
    }

    private function request(Booking $booking, bool $withProof = false): GuestPaymentRequest
    {
        $proof = null;

        if ($withProof) {
            $path = tempnam(sys_get_temp_dir(), 'tgh-payment-proof-');
            self::assertIsString($path);
            $contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
            self::assertSame(strlen($contents), file_put_contents($path, $contents));
            $this->temporaryFiles[] = $path;
            $proof = new UploadedFile(
                'proof.pdf',
                'application/pdf',
                $path,
                UPLOAD_ERR_OK,
                strlen($contents),
            );
        }

        $token = (new BookingAccessToken(self::KEY))->issue(
            $booking->bookingReference(),
            $booking->confirmationDeadlineAt(),
        );

        return GuestPaymentRequest::fromInput(
            $booking->bookingReference(),
            $token,
            7,
            '18000.00',
            'BANK-001',
            'Transferred today.',
            $proof,
            $this->proofPolicy,
        );
    }

    private function booking(
        string $status = Booking::STATUS_PENDING,
        string $totalAmount = '18000.00',
        ?DateTimeImmutable $confirmationDeadlineAt = null,
    ): Booking {
        return new Booking(
            7,
            'TGH-7F3A9C2D',
            null,
            'Guest Example',
            'guest@example.test',
            '+9779812345678',
            self::date('2026-08-10'),
            self::date('2026-08-13'),
            2,
            1,
            Booking::CURRENCY_NPR,
            $totalAmount,
            $totalAmount,
            $status,
            null,
            self::timestamp('2026-08-01 10:00:00'),
            'v1',
            $confirmationDeadlineAt
                ?? self::timestamp('2026-08-01 18:00:00'),
            $status === Booking::STATUS_CONFIRMED
                ? self::timestamp('2026-08-01 11:00:00')
                : null,
            null,
            null,
            null,
            null,
            null,
            null,
            self::timestamp('2026-08-01 10:00:00'),
            self::timestamp('2026-08-01 10:00:00'),
            null,
        );
    }

    private function method(
        bool $active = true,
        bool $requiresProof = false,
    ): PaymentMethod {
        $timestamp = self::timestamp('2026-07-28 06:00:00');

        return new PaymentMethod(
            7,
            'Bank Transfer',
            null,
            null,
            null,
            $requiresProof,
            $active,
            $timestamp,
            $timestamp,
        );
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

final class GuestPaymentWriteRepositoryFake implements PaymentWriteRepository
{
    /** @var list<string> */
    public array $events = [];

    public int $createCalls = 0;

    public ?\Throwable $createFailure = null;

    private int $nextIdentifier = 44;

    public function __construct(
        public ?Booking $booking,
        public ?PaymentMethod $method,
    ) {
    }

    public function lockBookingByReference(string $bookingReference): ?Booking
    {
        $this->events[] = 'lock-booking';

        return $this->booking;
    }

    public function lockPaymentMethodById(int $paymentMethodId): ?PaymentMethod
    {
        $this->events[] = 'lock-method';

        return $this->method;
    }

    public function lockCurrent(int $paymentId): ?Payment
    {
        throw new RuntimeException('Not used.');
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
        $this->events[] = 'create';
        $this->createCalls++;

        if ($this->createFailure !== null) {
            throw $this->createFailure;
        }

        $identifier = $this->nextIdentifier++;

        return new Payment(
            $identifier,
            $bookingId,
            $paymentMethodId,
            Payment::CURRENCY_NPR,
            $amount,
            $transactionReference,
            $proofPath,
            $guestNotes,
            Payment::STATUS_SUBMITTED,
            $submittedAt,
            null,
            null,
            null,
            null,
            null,
            $submittedAt,
            $submittedAt,
        );
    }

    public function hasVerifiedForBooking(
        int $bookingId,
        ?int $excludingPaymentId = null,
    ): bool {
        return false;
    }

    public function markVerified(
        Payment $current,
        int $verifiedBy,
        string $note,
        DateTimeImmutable $verifiedAt,
    ): Payment {
        throw new RuntimeException('Not used.');
    }

    public function markRejected(
        Payment $current,
        int $rejectedBy,
        string $note,
        DateTimeImmutable $rejectedAt,
    ): Payment {
        throw new RuntimeException('Not used.');
    }
}

final class GuestPaymentProofStorageFake implements PaymentProofStorage
{
    public int $storeCalls = 0;

    /** @var list<string> */
    public array $removedPaths = [];

    public function store(UploadedFile $file, string $detectedMediaType): StoredPaymentProof
    {
        $this->storeCalls++;
        $filename = str_repeat('a', 64) . '.pdf';

        return new StoredPaymentProof(
            'payment-proofs/' . $filename,
            $filename,
            'pdf',
            'application/pdf',
            $file->size(),
        );
    }

    public function remove(string $relativePath): void
    {
        $this->removedPaths[] = $relativePath;
    }
}

final class GuestPaymentTransactionFake implements BookingTransaction
{
    /** @var list<string> */
    public array $events = [];

    public int $commits = 0;

    public int $rollbacks = 0;

    public ?\Throwable $commitFailure = null;

    private bool $active = false;

    public function begin(): void
    {
        $this->events[] = 'begin';
        $this->active = true;
    }

    public function commit(): void
    {
        $this->events[] = 'commit';

        if ($this->commitFailure !== null) {
            throw $this->commitFailure;
        }

        $this->commits++;
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->events[] = 'roll-back';
        $this->rollbacks++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
