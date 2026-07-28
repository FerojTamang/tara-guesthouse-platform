<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\PublicPaymentMethodException;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Services\PublicPaymentMethodService;
use App\Services\PublicPaymentMethodServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicPaymentMethodServiceProviderTest extends TestCase
{
    public function testItBuildsTheServiceLazilyAndCachesIt(): void
    {
        $calls = 0;
        $repository = $this->createStub(PaymentMethodCatalogRepository::class);
        $provider = new PublicPaymentMethodServiceProvider(
            static function () use (&$calls, $repository): PublicPaymentMethodService {
                $calls++;

                return new PublicPaymentMethodService($repository);
            },
        );

        self::assertSame(0, $calls);
        $first = $provider->service();
        $second = $provider->service();

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
    }

    public function testInvalidFactoryResultFailsSafely(): void
    {
        $provider = new PublicPaymentMethodServiceProvider(
            static fn (): object => new \stdClass(),
        );

        $this->expectException(PublicPaymentMethodException::class);
        $this->expectExceptionMessage('Public payment method catalogue failed.');

        $provider->service();
    }

    public function testUnexpectedFactoryFailureIsSanitized(): void
    {
        $provider = new PublicPaymentMethodServiceProvider(
            static function (): never {
                throw new RuntimeException('sensitive database connection details');
            },
        );

        try {
            $provider->service();
            self::fail('Expected a safe public payment-method failure.');
        } catch (PublicPaymentMethodException $exception) {
            self::assertSame(
                'Public payment method catalogue failed.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('sensitive', $exception->getMessage());
        }
    }
}
