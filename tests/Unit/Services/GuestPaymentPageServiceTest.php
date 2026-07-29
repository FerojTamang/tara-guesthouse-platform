<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GuestPaymentException;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Services\BookingAccessToken;
use App\Services\GuestPaymentPageService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GuestPaymentPageServiceTest extends TestCase
{
    private const KEY = 'guest-payment-page-test-key-32-bytes-minimum';

    public function testItValidatesContinuationAndReturnsOnlyActiveMethods(): void
    {
        $booking = $this->booking();
        $methods = [$this->method(7, 'Bank Transfer'), $this->method(8, 'Wallet')];
        $bookings = $this->createMock(BookingCatalogRepository::class);
        $bookings->expects(self::once())
            ->method('findByReference')
            ->with($booking->bookingReference())
            ->willReturn($booking);
        $paymentMethods = $this->createMock(PaymentMethodCatalogRepository::class);
        $paymentMethods->expects(self::once())
            ->method('findAllActive')
            ->willReturn($methods);
        $validatedAt = self::timestamp('2026-08-01 12:00:00');
        $tokens = new BookingAccessToken(self::KEY);

        $context = (new GuestPaymentPageService(
            $bookings,
            $paymentMethods,
            $tokens,
            static fn (): DateTimeImmutable => $validatedAt,
        ))->load(
            $booking->bookingReference(),
            $tokens->issue(
                $booking->bookingReference(),
                $booking->confirmationDeadlineAt(),
            ),
        );

        self::assertSame($booking, $context->booking());
        self::assertSame($methods, $context->paymentMethods());
        self::assertSame($validatedAt, $context->validatedAt());
    }

    public function testUnknownBookingAndWrongTokenUseTheSameSafeFailure(): void
    {
        $booking = $this->booking();
        $tokens = new BookingAccessToken(self::KEY);

        foreach ([null, $booking] as $storedBooking) {
            $bookings = $this->createStub(BookingCatalogRepository::class);
            $bookings->method('findByReference')->willReturn($storedBooking);
            $paymentMethods = $this->createMock(PaymentMethodCatalogRepository::class);
            $paymentMethods->expects(self::never())->method('findAllActive');
            $token = $storedBooking === null
                ? $tokens->issue(
                    $booking->bookingReference(),
                    $booking->confirmationDeadlineAt(),
                )
                : 'v1.' . str_repeat('B', 43);

            try {
                $this->service($bookings, $paymentMethods)->load(
                    $booking->bookingReference(),
                    $token,
                );
                self::fail('Invalid Guest payment continuation was accepted.');
            } catch (GuestPaymentException $exception) {
                self::assertTrue($exception->isAccessDenied());
                self::assertSame('Payment access is invalid.', $exception->getMessage());
                self::assertStringNotContainsString($token, $exception->getMessage());
            }
        }
    }

    public function testNonPendingOrExpiredBookingClearsThePageWorkflow(): void
    {
        $confirmed = $this->booking(Booking::STATUS_CONFIRMED);
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($confirmed);
        $paymentMethods = $this->createMock(PaymentMethodCatalogRepository::class);
        $paymentMethods->expects(self::never())->method('findAllActive');
        $tokens = new BookingAccessToken(self::KEY);

        try {
            $this->service($bookings, $paymentMethods)->load(
                $confirmed->bookingReference(),
                $tokens->issue(
                    $confirmed->bookingReference(),
                    $confirmed->confirmationDeadlineAt(),
                ),
            );
            self::fail('A confirmed booking reached the payment form.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isConflict());
        }

        $pending = $this->booking();
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($pending);

        try {
            (new GuestPaymentPageService(
                $bookings,
                $paymentMethods,
                $tokens,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 18:00:00'),
            ))->load(
                $pending->bookingReference(),
                $tokens->issue(
                    $pending->bookingReference(),
                    $pending->confirmationDeadlineAt(),
                ),
            );
            self::fail('An expired continuation reached the payment form.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isAccessDenied());
        }
    }

    public function testInvalidRepositoryDataAndClockFailuresAreSanitized(): void
    {
        $booking = $this->booking();
        $tokens = new BookingAccessToken(self::KEY);
        $token = $tokens->issue(
            $booking->bookingReference(),
            $booking->confirmationDeadlineAt(),
        );
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($booking);
        $paymentMethods = $this->createStub(PaymentMethodCatalogRepository::class);
        $paymentMethods->method('findAllActive')->willReturn([
            $this->method(7, 'Inactive', false),
        ]);

        foreach ([
            $this->service($bookings, $paymentMethods),
            new GuestPaymentPageService(
                $bookings,
                $paymentMethods,
                $tokens,
                static fn (): string => 'not a clock',
            ),
        ] as $service) {
            try {
                $service->load($booking->bookingReference(), $token);
                self::fail('Invalid payment page dependencies were accepted.');
            } catch (GuestPaymentException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Payment operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('clock', $exception->getMessage());
            }
        }

        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willThrowException(
            new RuntimeException('SQLSTATE private details'),
        );

        try {
            $this->service($bookings, $paymentMethods)->load(
                $booking->bookingReference(),
                $token,
            );
            self::fail('Repository failure was exposed.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    private function service(
        BookingCatalogRepository $bookings,
        PaymentMethodCatalogRepository $paymentMethods,
    ): GuestPaymentPageService {
        return new GuestPaymentPageService(
            $bookings,
            $paymentMethods,
            new BookingAccessToken(self::KEY),
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
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

    private function method(
        int $id,
        string $name,
        bool $active = true,
    ): PaymentMethod {
        $timestamp = self::timestamp('2026-07-28 06:00:00');

        return new PaymentMethod(
            $id,
            $name,
            'Use the configured account.',
            'TGH-ACCOUNT',
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
