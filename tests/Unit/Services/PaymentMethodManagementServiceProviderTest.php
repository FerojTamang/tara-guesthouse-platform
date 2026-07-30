<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PaymentMethodException;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Services\PaymentMethodManagementService;
use App\Services\PaymentMethodManagementServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PaymentMethodManagementServiceProviderTest extends TestCase
{
    public function testItBuildsTheServiceLazilyAndCachesIt(): void
    {
        $calls = 0;
        $repository = $this->createStub(PaymentMethodCatalogRepository::class);
        $provider = new PaymentMethodManagementServiceProvider(
            static function () use (&$calls, $repository): PaymentMethodManagementService {
                $calls++;

                return new PaymentMethodManagementService($repository);
            },
        );

        self::assertSame(0, $calls);
        $first = $provider->service();
        $second = $provider->service();

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
    }

    public function testInvalidFactoryResultBecomesASafeFailure(): void
    {
        $provider = new PaymentMethodManagementServiceProvider(
            static fn (): object => new \stdClass(),
        );

        $this->expectException(PaymentMethodException::class);
        $this->expectExceptionMessage('Payment method operation failed.');

        $provider->service();
    }

    public function testUnexpectedFactoryFailureIsSanitized(): void
    {
        $provider = new PaymentMethodManagementServiceProvider(
            static function (): never {
                throw new RuntimeException('sensitive connection details');
            },
        );

        try {
            $provider->service();
            self::fail('Expected a safe payment-method failure.');
        } catch (PaymentMethodException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Payment method operation failed.', $exception->getMessage());
            self::assertStringNotContainsString(
                'sensitive connection details',
                $exception->getMessage(),
            );
        }
    }
}
