<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class PaymentMethodRepositoryTest extends DatabaseTestCase
{
    private PaymentMethodRepository $repository;

    /** @var array<string, int> */
    private array $methodIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $this->methodIds['bank'] = $this->insertMethod(
            'Bank Transfer',
            'Transfer the exact booking total.',
            'TEST-BANK-ACCOUNT',
            'payment-methods/bank.png',
            true,
            true,
            '2026-07-28 06:00:00',
            '2026-07-28 06:30:00',
        );
        $this->methodIds['cash'] = $this->insertMethod(
            'Cash at Property',
            null,
            null,
            null,
            false,
            false,
            '2026-07-28 07:00:00',
            '2026-07-28 07:30:00',
        );
        $this->repository = new PaymentMethodRepository($this->connection);
    }

    public function testItFindsAndMapsTheCompletePaymentMethod(): void
    {
        $method = $this->repository->findById($this->methodIds['bank']);

        self::assertInstanceOf(PaymentMethod::class, $method);
        self::assertSame($this->methodIds['bank'], $method->id());
        self::assertSame('Bank Transfer', $method->name());
        self::assertSame('Transfer the exact booking total.', $method->instructions());
        self::assertSame('TEST-BANK-ACCOUNT', $method->accountReference());
        self::assertSame('payment-methods/bank.png', $method->qrImagePath());
        self::assertTrue($method->requiresProof());
        self::assertTrue($method->isActive());
        self::assertEquals(
            new DateTimeImmutable('2026-07-28 06:00:00 UTC'),
            $method->createdAt(),
        );
        self::assertEquals(
            new DateTimeImmutable('2026-07-28 06:30:00 UTC'),
            $method->updatedAt(),
        );
    }

    public function testItListsAllAndOnlyActiveMethodsInStableNameOrder(): void
    {
        $all = $this->repository->findAll();
        $active = $this->repository->findAllActive();

        self::assertSame(
            ['Bank Transfer', 'Cash at Property'],
            array_map(
                static fn (PaymentMethod $method): string => $method->name(),
                $all,
            ),
        );
        self::assertSame(
            [true, false],
            array_map(
                static fn (PaymentMethod $method): bool => $method->isActive(),
                $all,
            ),
        );
        self::assertCount(1, $active);
        self::assertSame('Bank Transfer', $active[0]->name());
    }

    public function testInvalidAndMissingIdentifiersReturnNullWithoutMutation(): void
    {
        $before = $this->snapshot();

        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(-1));
        self::assertNull($this->repository->findById(999999));
        self::assertNull($this->repository->update(
            0,
            'Missing',
            null,
            null,
            null,
            false,
            false,
        ));
        self::assertNull($this->repository->update(
            999999,
            'Missing',
            null,
            null,
            null,
            false,
            false,
        ));
        self::assertNull($this->repository->setActive(0, false));
        self::assertNull($this->repository->setActive(999999, false));

        self::assertSame($before, $this->snapshot());
    }

    public function testItCreatesUpdatesAndTogglesConfiguredMethods(): void
    {
        $created = $this->repository->create(
            'Mobile Wallet',
            'Use the configured wallet reference.',
            'TEST-WALLET',
            null,
            true,
            true,
        );

        self::assertGreaterThan(0, $created->id());
        self::assertSame('Mobile Wallet', $created->name());
        self::assertSame('Use the configured wallet reference.', $created->instructions());
        self::assertSame('TEST-WALLET', $created->accountReference());
        self::assertNull($created->qrImagePath());
        self::assertTrue($created->requiresProof());
        self::assertTrue($created->isActive());

        $updated = $this->repository->update(
            $created->id(),
            'Wallet Transfer',
            null,
            'UPDATED-WALLET',
            'payment-methods/wallet.png',
            false,
            false,
        );

        self::assertInstanceOf(PaymentMethod::class, $updated);
        self::assertSame('Wallet Transfer', $updated->name());
        self::assertNull($updated->instructions());
        self::assertSame('UPDATED-WALLET', $updated->accountReference());
        self::assertSame('payment-methods/wallet.png', $updated->qrImagePath());
        self::assertFalse($updated->requiresProof());
        self::assertFalse($updated->isActive());

        $active = $this->repository->setActive($updated->id(), true);
        self::assertInstanceOf(PaymentMethod::class, $active);
        self::assertTrue($active->isActive());
        self::assertSame($updated->name(), $active->name());
        self::assertSame($updated->accountReference(), $active->accountReference());
    }

    public function testDatabaseFailuresExposeOnlyStableMessages(): void
    {
        $this->dropTables(['payments', 'payment_methods']);

        try {
            $this->repository->findById($this->methodIds['bank']);
            self::fail('Expected a sanitized payment-method lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment method lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->findAll();
            self::fail('Expected a sanitized payment-method listing failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment method listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->create(
                'Unavailable Method',
                null,
                null,
                null,
                false,
                false,
            );
            self::fail('Expected a sanitized payment-method write failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The payment method write failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function insertMethod(
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
        string $createdAt,
        string $updatedAt,
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO payment_methods ('
            . 'name, instructions, account_reference, qr_image_path, '
            . 'requires_proof, is_active, created_at, updated_at'
            . ') VALUES ('
            . ':name, :instructions, :account_reference, :qr_image_path, '
            . ':requires_proof, :is_active, :created_at, :updated_at'
            . ')',
        );
        $statement->execute([
            'name' => $name,
            'instructions' => $instructions,
            'account_reference' => $accountReference,
            'qr_image_path' => $qrImagePath,
            'requires_proof' => $requiresProof ? 1 : 0,
            'is_active' => $active ? 1 : 0,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    private function snapshot(): array
    {
        $rows = $this->connection->query(
            'SELECT id, name, instructions, account_reference, qr_image_path, '
            . 'requires_proof, is_active, created_at, updated_at '
            . 'FROM payment_methods ORDER BY id',
        )->fetchAll();

        self::assertIsArray($rows);

        return $rows;
    }
}
