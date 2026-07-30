<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Booking;
use App\Models\GuestPaymentAccess;
use App\Models\PaymentMethod;
use App\Requests\GuestPaymentRequest;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GuestPaymentAccessTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $proofPolicy;

    protected function setUp(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $this->proofPolicy = $configuration['payment_proofs'];
    }

    public function testItPreservesOneValidatedRequestBookingAndMethod(): void
    {
        $request = $this->request();
        $booking = $this->booking();
        $method = $this->method();
        $validatedAt = self::timestamp('2026-08-01 12:00:00');
        $access = new GuestPaymentAccess($request, $booking, $method, $validatedAt);

        self::assertSame($request, $access->request());
        self::assertSame($booking, $access->booking());
        self::assertSame($method, $access->paymentMethod());
        self::assertSame($validatedAt, $access->validatedAt());
    }

    public function testItRejectsMismatchedOrExpiredAccessFacts(): void
    {
        foreach ([
            [$this->request(methodId: 8), $this->booking(), $this->method(), self::timestamp('2026-08-01 12:00:00')],
            [$this->request(amount: '17000.00'), $this->booking(), $this->method(), self::timestamp('2026-08-01 12:00:00')],
            [$this->request(), $this->booking(status: Booking::STATUS_CONFIRMED), $this->method(), self::timestamp('2026-08-01 12:00:00')],
            [$this->request(), $this->booking(), $this->method(active: false), self::timestamp('2026-08-01 12:00:00')],
            [$this->request(), $this->booking(), $this->method(), self::timestamp('2026-08-01 18:00:00')],
        ] as $arguments) {
            try {
                new GuestPaymentAccess(...$arguments);
                self::fail('Invalid Guest payment access facts were accepted.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Guest payment access data is invalid.', $exception->getMessage());
            }
        }
    }

    private function request(int $methodId = 7, string $amount = '18000.00'): GuestPaymentRequest
    {
        return GuestPaymentRequest::fromInput(
            'TGH-7F3A9C2D',
            'v1.' . str_repeat('A', 43),
            $methodId,
            $amount,
            null,
            null,
            null,
            $this->proofPolicy,
        );
    }

    private function booking(string $status = Booking::STATUS_PENDING): Booking
    {
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
            '18000.00',
            '18000.00',
            $status,
            null,
            self::timestamp('2026-08-01 10:00:00'),
            'v1',
            self::timestamp('2026-08-01 18:00:00'),
            null,
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

    private function method(bool $active = true): PaymentMethod
    {
        $timestamp = self::timestamp('2026-07-28 06:00:00');

        return new PaymentMethod(
            7,
            'Bank Transfer',
            null,
            null,
            null,
            false,
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
