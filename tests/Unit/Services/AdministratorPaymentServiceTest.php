<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AdministratorPaymentException;
use App\Models\AdministratorPaymentRecord;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\AdministratorPaymentCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Services\AdministratorPaymentService;
use App\Services\BookingTransaction;
use App\Storage\PaymentProofReader;
use App\Storage\RetrievedPaymentProof;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorPaymentServiceTest extends TestCase
{
    public function testItListsAllOrOneCanonicalStatus(): void
    {
        $record = self::record();
        $repository = $this->createMock(AdministratorPaymentCatalogRepository::class);
        $repository->expects(self::exactly(2))
            ->method('findAll')
            ->willReturnCallback(
                static fn (?string $status): array => $status === null
                    || $status === Payment::STATUS_SUBMITTED
                    ? [$record]
                    : [],
            );
        $service = self::service($repository);

        self::assertSame([$record], $service->list());
        self::assertSame([$record], $service->list(Payment::STATUS_SUBMITTED));
    }

    public function testInvalidStatusFailsBeforeRepositoryAccess(): void
    {
        $repository = $this->createMock(AdministratorPaymentCatalogRepository::class);
        $repository->expects(self::never())->method('findAll');
        $service = self::service($repository);

        $this->expectException(AdministratorPaymentException::class);
        $this->expectExceptionMessage('Payment status filter is invalid.');

        $service->list('completed');
    }

    public function testInvalidAndMissingDetailReturnNull(): void
    {
        $repository = $this->createMock(AdministratorPaymentCatalogRepository::class);
        $repository->expects(self::once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);
        $service = self::service($repository);

        self::assertNull($service->detail(0));
        self::assertNull($service->detail(999));
    }

    public function testSuccessfulProofAccessIsAuditedWithoutSensitiveMetadata(): void
    {
        $record = self::record();
        $repository = $this->createStub(AdministratorPaymentCatalogRepository::class);
        $repository->method('findById')->willReturn($record);
        $proof = new RetrievedPaymentProof('%PDF-1.4 proof', 'pdf', 'application/pdf');
        $reader = new AdministratorPaymentProofReaderFake($proof);
        $audit = new AdministratorPaymentAuditRepositoryFake();
        $transaction = new AdministratorPaymentTransactionFake();
        $service = new AdministratorPaymentService(
            $repository,
            $reader,
            $audit,
            $transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable(
                '2026-07-29 12:00:00 UTC',
            ),
        );

        $result = $service->proof(44, 91, '127.0.0.1', 'Test Agent');

        self::assertSame($proof, $result);
        self::assertSame($record->payment()->proofPath(), $reader->path);
        self::assertSame(1, $transaction->beginCount);
        self::assertSame(1, $transaction->commitCount);
        self::assertSame(0, $transaction->rollBackCount);
        self::assertCount(1, $audit->entries);
        $entry = $audit->entries[0];
        self::assertSame(91, $entry->userId());
        self::assertSame('payment_proof_accessed', $entry->eventType());
        self::assertSame('payment', $entry->entityType());
        self::assertSame(44, $entry->entityId());
        self::assertNull($entry->metadataJson());
        self::assertStringNotContainsString('payment-proofs', $entry->description());
        self::assertStringNotContainsString(str_repeat('a', 64), $entry->description());
    }

    public function testMissingProofDoesNotCreateAuditRecord(): void
    {
        $repository = $this->createStub(AdministratorPaymentCatalogRepository::class);
        $repository->method('findById')->willReturn(self::record(null));
        $reader = new AdministratorPaymentProofReaderFake(
            new RetrievedPaymentProof('%PDF-1.4 proof', 'pdf', 'application/pdf'),
        );
        $audit = new AdministratorPaymentAuditRepositoryFake();
        $transaction = new AdministratorPaymentTransactionFake();
        $service = new AdministratorPaymentService(
            $repository,
            $reader,
            $audit,
            $transaction,
        );

        self::assertNull($service->proof(44, 91, null, null));
        self::assertNull($reader->path);
        self::assertSame([], $audit->entries);
        self::assertSame(0, $transaction->beginCount);
    }

    public function testExistingTransactionIsRejectedWithoutReadingOrRollingBackIt(): void
    {
        $repository = $this->createMock(AdministratorPaymentCatalogRepository::class);
        $repository->expects(self::never())->method('findById');
        $reader = new AdministratorPaymentProofReaderFake(null);
        $transaction = new AdministratorPaymentTransactionFake(true);
        $service = new AdministratorPaymentService(
            $repository,
            $reader,
            new AdministratorPaymentAuditRepositoryFake(),
            $transaction,
        );

        try {
            $service->proof(44, 91, null, null);
            self::fail('Expected the unrelated transaction to be rejected.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }

        self::assertNull($reader->path);
        self::assertSame(0, $transaction->beginCount);
        self::assertSame(0, $transaction->commitCount);
        self::assertSame(0, $transaction->rollBackCount);
        self::assertTrue($transaction->inProgress());
    }

    public function testAuditFailureRollsBackAndProofIsNotReturned(): void
    {
        $repository = $this->createStub(AdministratorPaymentCatalogRepository::class);
        $repository->method('findById')->willReturn(self::record());
        $reader = new AdministratorPaymentProofReaderFake(
            new RetrievedPaymentProof('%PDF-1.4 proof', 'pdf', 'application/pdf'),
        );
        $audit = new AdministratorPaymentAuditRepositoryFake(true);
        $transaction = new AdministratorPaymentTransactionFake();
        $service = new AdministratorPaymentService(
            $repository,
            $reader,
            $audit,
            $transaction,
        );

        try {
            $service->proof(44, 91, null, null);
            self::fail('Expected audited proof access to fail closed.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Administrator payment operation failed.', $exception->getMessage());
        }

        self::assertSame(1, $transaction->beginCount);
        self::assertSame(0, $transaction->commitCount);
        self::assertSame(1, $transaction->rollBackCount);
    }

    private static function service(
        AdministratorPaymentCatalogRepository $repository,
    ): AdministratorPaymentService {
        return new AdministratorPaymentService(
            $repository,
            new AdministratorPaymentProofReaderFake(null),
            new AdministratorPaymentAuditRepositoryFake(),
            new AdministratorPaymentTransactionFake(),
        );
    }

    private static function record(?string $proofPath = 'payment-proofs/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf'): AdministratorPaymentRecord
    {
        $createdAt = new DateTimeImmutable('2026-07-29 06:00:00 UTC');
        $payment = new Payment(
            44,
            31,
            2,
            Payment::CURRENCY_NPR,
            '18000.00',
            'BANK-REFERENCE-44',
            $proofPath,
            'Guest note.',
            Payment::STATUS_SUBMITTED,
            new DateTimeImmutable('2026-07-29 06:05:00 UTC'),
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
        );

        return new AdministratorPaymentRecord(
            $payment,
            'TGH-2026-PAYMENT00000000001',
            'Guest Example',
            'guest@example.test',
            '+9779812345678',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            Booking::STATUS_PENDING,
            '18000.00',
            new DateTimeImmutable('2026-07-29 12:00:00 UTC'),
            'Bank transfer',
            'Account 123',
            true,
            true,
        );
    }
}

final class AdministratorPaymentProofReaderFake implements PaymentProofReader
{
    public ?string $path = null;

    public function __construct(private readonly ?RetrievedPaymentProof $proof)
    {
    }

    public function read(string $relativePath): ?RetrievedPaymentProof
    {
        $this->path = $relativePath;

        return $this->proof;
    }
}

final class AdministratorPaymentAuditRepositoryFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function append(AuditLogEntry $entry): void
    {
        if ($this->fail) {
            throw new RuntimeException('private database detail');
        }

        $this->entries[] = $entry;
    }
}

final class AdministratorPaymentTransactionFake implements BookingTransaction
{
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollBackCount = 0;
    private bool $active;

    public function __construct(bool $active = false)
    {
        $this->active = $active;
    }

    public function begin(): void
    {
        $this->beginCount++;
        $this->active = true;
    }

    public function commit(): void
    {
        $this->commitCount++;
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->rollBackCount++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
