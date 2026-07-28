<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PublicPaymentMethodException;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Services\PublicPaymentMethodService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicPaymentMethodServiceTest extends TestCase
{
    public function testItMapsOnlyActiveMethodsToTheApprovedPublicFields(): void
    {
        $repository = $this->createMock(PaymentMethodCatalogRepository::class);
        $repository->expects(self::once())
            ->method('findAllActive')
            ->willReturn([
                self::method(
                    7,
                    'Bank Transfer',
                    'Transfer the exact booking total.',
                    'TEST-ACCOUNT',
                    'payment-methods/bank.png',
                    true,
                    true,
                ),
                self::method(
                    9,
                    'Inactive fixture',
                    null,
                    null,
                    null,
                    false,
                    false,
                ),
            ]);
        $repository->expects(self::never())->method('findAll');
        $service = new PublicPaymentMethodService($repository);

        self::assertSame([
            [
                'payment_method_id' => 7,
                'name' => 'Bank Transfer',
                'instructions' => 'Transfer the exact booking total.',
                'account_reference' => 'TEST-ACCOUNT',
                'qr_image_path' => 'payment-methods/bank.png',
                'requires_proof' => true,
            ],
        ], $service->listActive());
    }

    public function testEmptyActiveCatalogueProducesAnEmptyList(): void
    {
        $repository = $this->createStub(PaymentMethodCatalogRepository::class);
        $repository->method('findAllActive')->willReturn([]);

        self::assertSame(
            [],
            (new PublicPaymentMethodService($repository))->listActive(),
        );
    }

    public function testRepositoryFailuresBecomeSafePublicFailures(): void
    {
        $repository = $this->createStub(PaymentMethodCatalogRepository::class);
        $repository->method('findAllActive')->willThrowException(
            new RuntimeException('SQLSTATE private payment details'),
        );
        $service = new PublicPaymentMethodService($repository);

        try {
            $service->listActive();
            self::fail('Expected a safe public payment-method failure.');
        } catch (PublicPaymentMethodException $exception) {
            self::assertSame(
                'Public payment method catalogue failed.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }
    }

    private static function method(
        int $id,
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): PaymentMethod {
        $timestamp = new DateTimeImmutable('2026-07-28 06:00:00 UTC');

        return new PaymentMethod(
            $id,
            $name,
            $instructions,
            $accountReference,
            $qrImagePath,
            $requiresProof,
            $active,
            $timestamp,
            $timestamp,
        );
    }
}
