<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PaymentMethodException;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Requests\PaymentMethodUpsertRequest;
use App\Services\PaymentMethodManagementService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentMethodManagementServiceTest extends TestCase
{
    public function testItListsAllConfiguredMethods(): void
    {
        $methods = [$this->method(1, false), $this->method(2, true)];
        $repository = $this->createMock(PaymentMethodCatalogRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn($methods);

        self::assertSame(
            $methods,
            (new PaymentMethodManagementService($repository))->listAll(),
        );
    }

    public function testItLoadsOneMethodAndRejectsAMissingIdentifier(): void
    {
        $stored = $this->method();
        $repository = $this->createMock(PaymentMethodCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('findById')
            ->willReturnMap([[7, $stored], [99, null]]);
        $service = new PaymentMethodManagementService($repository);

        self::assertSame($stored, $service->get(7));

        try {
            $service->get(99);
            self::fail('Expected a missing payment-method failure.');
        } catch (PaymentMethodException $exception) {
            self::assertTrue($exception->isNotFound());
            self::assertSame(
                'The requested payment method was not found.',
                $exception->getMessage(),
            );
        }
    }

    public function testCreateUsesValidatedValuesAndDoesNotInventAQrPath(): void
    {
        $request = $this->request();
        $stored = $this->method();
        $repository = $this->createMock(PaymentMethodCatalogRepository::class);
        $repository->expects(self::once())->method('create')->with(
            'Bank transfer',
            "Transfer the exact amount.\nUse the booking reference.",
            'BANK-001',
            null,
            true,
            true,
        )->willReturn($stored);

        self::assertSame(
            $stored,
            (new PaymentMethodManagementService($repository))->create($request),
        );
    }

    public function testUpdatePreservesTheExistingQrPath(): void
    {
        $request = $this->request(active: '0');
        $current = $this->method(qrImagePath: 'payment-methods/existing.png');
        $updated = $this->method(active: false, qrImagePath: 'payment-methods/existing.png');
        $repository = $this->createMock(PaymentMethodCatalogRepository::class);
        $repository->expects(self::once())->method('findById')->with(7)->willReturn($current);
        $repository->expects(self::once())->method('update')->with(
            7,
            'Bank transfer',
            "Transfer the exact amount.\nUse the booking reference.",
            'BANK-001',
            'payment-methods/existing.png',
            true,
            false,
        )->willReturn($updated);

        self::assertSame(
            $updated,
            (new PaymentMethodManagementService($repository))->update(7, $request),
        );
    }

    public function testMissingUpdateBecomesANotFoundFailureWithoutWriting(): void
    {
        $repository = $this->createMock(PaymentMethodCatalogRepository::class);
        $repository->expects(self::once())->method('findById')->with(99)->willReturn(null);
        $repository->expects(self::never())->method('update');

        try {
            (new PaymentMethodManagementService($repository))->update(99, $this->request());
            self::fail('Expected a missing payment-method failure.');
        } catch (PaymentMethodException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testItActivatesAndDeactivatesThroughExplicitOperations(): void
    {
        $active = $this->method(active: true);
        $inactive = $this->method(active: false);
        $repository = $this->createMock(PaymentMethodCatalogRepository::class);
        $repository->expects(self::exactly(2))->method('setActive')
            ->willReturnMap([[7, true, $active], [7, false, $inactive]]);
        $service = new PaymentMethodManagementService($repository);

        self::assertSame($active, $service->activate(7));
        self::assertSame($inactive, $service->deactivate(7));
    }

    public function testMissingStateTargetBecomesANotFoundFailure(): void
    {
        $repository = $this->createStub(PaymentMethodCatalogRepository::class);
        $repository->method('setActive')->willReturn(null);

        try {
            (new PaymentMethodManagementService($repository))->deactivate(99);
            self::fail('Expected a missing payment-method failure.');
        } catch (PaymentMethodException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testUnexpectedRepositoryFailuresBecomeOneSafeSystemFailure(): void
    {
        $repository = $this->createStub(PaymentMethodCatalogRepository::class);
        $repository->method('findAll')
            ->willThrowException(new RuntimeException('sensitive SQL details'));

        try {
            (new PaymentMethodManagementService($repository))->listAll();
            self::fail('Expected a safe payment-method failure.');
        } catch (PaymentMethodException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Payment method operation failed.', $exception->getMessage());
            self::assertStringNotContainsString(
                'sensitive SQL details',
                $exception->getMessage(),
            );
        }
    }

    private function request(mixed $active = '1'): PaymentMethodUpsertRequest
    {
        return PaymentMethodUpsertRequest::fromInput(
            ' Bank transfer ',
            " Transfer the exact amount.\r\nUse the booking reference. ",
            ' BANK-001 ',
            '1',
            $active,
        );
    }

    private function method(
        int $id = 7,
        bool $active = true,
        ?string $qrImagePath = null,
    ): PaymentMethod {
        $timestamp = new DateTimeImmutable('2026-07-28 10:11:12 UTC');

        return new PaymentMethod(
            $id,
            'Bank transfer',
            "Transfer the exact amount.\nUse the booking reference.",
            'BANK-001',
            $qrImagePath,
            true,
            $active,
            $timestamp,
            $timestamp,
        );
    }
}
