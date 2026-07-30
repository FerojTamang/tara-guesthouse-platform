<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PaymentMethod;
use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class PaymentMethodTest extends TestCase
{
    public function testItExposesImmutableConfiguredMethodData(): void
    {
        $createdAt = self::timestamp('2026-07-28 06:00:00');
        $updatedAt = self::timestamp('2026-07-28 07:00:00');
        $method = new PaymentMethod(
            7,
            'Bank Transfer',
            'Transfer the exact booking total.',
            'TEST-ACCOUNT',
            'payment-methods/bank-transfer.png',
            true,
            true,
            $createdAt,
            $updatedAt,
        );

        self::assertTrue((new ReflectionClass($method))->isReadOnly());
        self::assertSame(7, $method->id());
        self::assertSame('Bank Transfer', $method->name());
        self::assertSame('Transfer the exact booking total.', $method->instructions());
        self::assertSame('TEST-ACCOUNT', $method->accountReference());
        self::assertSame('payment-methods/bank-transfer.png', $method->qrImagePath());
        self::assertTrue($method->requiresProof());
        self::assertTrue($method->isActive());
        self::assertSame($createdAt, $method->createdAt());
        self::assertSame($updatedAt, $method->updatedAt());

        $property = new ReflectionProperty($method, 'name');
        $this->expectException(Error::class);
        $property->setValue($method, 'Changed');
    }

    public function testNullableHistoricalFieldsAndInactiveStateArePreserved(): void
    {
        $timestamp = self::timestamp('2026-07-28 06:00:00');
        $method = new PaymentMethod(
            1,
            'Cash at Property',
            null,
            null,
            null,
            false,
            false,
            $timestamp,
            $timestamp,
        );

        self::assertNull($method->instructions());
        self::assertNull($method->accountReference());
        self::assertNull($method->qrImagePath());
        self::assertFalse($method->requiresProof());
        self::assertFalse($method->isActive());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidData')]
    public function testItRejectsInvalidPersistenceData(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment method data is invalid.');

        self::method($override);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidData(): iterable
    {
        yield 'invalid identifier' => [['id' => 0]];
        yield 'blank name' => [['name' => '']];
        yield 'untrimmed name' => [['name' => ' Bank Transfer ']];
        yield 'overlength name' => [['name' => str_repeat('a', 101)]];
        yield 'absolute QR path' => [['qrImagePath' => '/payment-methods/bank.png']];
        yield 'parent QR path' => [['qrImagePath' => 'payment-methods/../bank.png']];
        yield 'backslash QR path' => [['qrImagePath' => 'payment-methods\\bank.png']];
        yield 'non UTC created timestamp' => [[
            'createdAt' => new DateTimeImmutable('2026-07-28 06:00:00 +05:45'),
        ]];
        yield 'non UTC updated timestamp' => [[
            'updatedAt' => new DateTimeImmutable('2026-07-28 06:00:00 +05:45'),
        ]];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function method(array $override): PaymentMethod
    {
        $timestamp = self::timestamp('2026-07-28 06:00:00');

        return new PaymentMethod(...array_replace([
            'id' => 1,
            'name' => 'Bank Transfer',
            'instructions' => null,
            'accountReference' => null,
            'qrImagePath' => null,
            'requiresProof' => true,
            'active' => true,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ], $override));
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
