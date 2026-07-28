<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\GuestPaymentException;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Requests\GuestPaymentRequest;
use App\Services\BookingAccessToken;
use App\Services\GuestPaymentAccessValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GuestPaymentAccessValidatorTest extends TestCase
{
    private const KEY = 'guest-payment-access-test-key-32-bytes-minimum';

    /** @var array<string, mixed> */
    private array $proofPolicy;

    protected function setUp(): void
    {
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        $this->proofPolicy = $configuration['payment_proofs'];
    }

    public function testItValidatesSignedAccessAndCurrentPaymentPrerequisites(): void
    {
        $booking = $this->booking();
        $method = $this->method();
        $request = $this->request($booking);
        $bookings = $this->createMock(BookingCatalogRepository::class);
        $bookings->expects(self::once())
            ->method('findByReference')
            ->with($booking->bookingReference())
            ->willReturn($booking);
        $methods = $this->createMock(PaymentMethodCatalogRepository::class);
        $methods->expects(self::once())
            ->method('findById')
            ->with(7)
            ->willReturn($method);
        $validatedAt = self::timestamp('2026-08-01 12:00:00');
        $access = (new GuestPaymentAccessValidator(
            $bookings,
            $methods,
            new BookingAccessToken(self::KEY),
            static fn (): DateTimeImmutable => $validatedAt,
        ))->validate($request);

        self::assertSame($request, $access->request());
        self::assertSame($booking, $access->booking());
        self::assertSame($method, $access->paymentMethod());
        self::assertSame($validatedAt, $access->validatedAt());
    }

    public function testUnknownBookingAndWrongTokenUseTheSameSafeAccessFailure(): void
    {
        $booking = $this->booking();

        foreach ([
            [null, $this->request($booking)],
            [$booking, GuestPaymentRequest::fromInput(
                $booking->bookingReference(),
                'v1.' . str_repeat('B', 43),
                7,
                '18000.00',
                null,
                null,
                null,
                $this->proofPolicy,
            )],
        ] as [$storedBooking, $request]) {
            $bookings = $this->createStub(BookingCatalogRepository::class);
            $bookings->method('findByReference')->willReturn($storedBooking);
            $methods = $this->createMock(PaymentMethodCatalogRepository::class);
            $methods->expects(self::never())->method('findById');

            try {
                $this->validator($bookings, $methods)->validate($request);
                self::fail('Invalid signed booking access was accepted.');
            } catch (GuestPaymentException $exception) {
                self::assertTrue($exception->isAccessDenied());
                self::assertSame('Payment access is invalid.', $exception->getMessage());
                self::assertStringNotContainsString($request->bookingReference(), $exception->getMessage());
            }
        }
    }

    public function testNonPendingAndExpiredBookingsCannotAcceptPayment(): void
    {
        $confirmed = $this->booking(status: Booking::STATUS_CONFIRMED);
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($confirmed);
        $methods = $this->createMock(PaymentMethodCatalogRepository::class);
        $methods->expects(self::never())->method('findById');

        try {
            $this->validator($bookings, $methods)->validate($this->request($confirmed));
            self::fail('Confirmed booking accepted a new payment attempt.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('The booking cannot accept a payment.', $exception->getMessage());
        }

        $pending = $this->booking();
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($pending);

        try {
            (new GuestPaymentAccessValidator(
                $bookings,
                $methods,
                new BookingAccessToken(self::KEY),
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 18:00:00'),
            ))->validate($this->request($pending));
            self::fail('Expired signed access was accepted.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isAccessDenied());
        }
    }

    public function testInactiveOrMissingMethodIsUnavailable(): void
    {
        foreach ([null, $this->method(active: false)] as $storedMethod) {
            $booking = $this->booking();
            $bookings = $this->createStub(BookingCatalogRepository::class);
            $bookings->method('findByReference')->willReturn($booking);
            $methods = $this->createStub(PaymentMethodCatalogRepository::class);
            $methods->method('findById')->willReturn($storedMethod);

            try {
                $this->validator($bookings, $methods)->validate($this->request($booking));
                self::fail('Unavailable payment method was accepted.');
            } catch (GuestPaymentException $exception) {
                self::assertTrue($exception->isConflict());
                self::assertSame(
                    'The selected payment method is not available.',
                    $exception->getMessage(),
                );
            }
        }
    }

    public function testAmountMustExactlyMatchTheNprBookingSnapshot(): void
    {
        $booking = $this->booking();
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($booking);
        $methods = $this->createStub(PaymentMethodCatalogRepository::class);
        $methods->method('findById')->willReturn($this->method());
        $request = $this->request($booking, amount: '17999.99');

        try {
            $this->validator($bookings, $methods)->validate($request);
            self::fail('A partial payment amount was accepted.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame(
                'Payment amount must match the booking total.',
                $exception->getMessage(),
            );
        }
    }

    public function testConfiguredProofRequirementIsEnforced(): void
    {
        $booking = $this->booking();
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturn($booking);
        $methods = $this->createStub(PaymentMethodCatalogRepository::class);
        $methods->method('findById')->willReturn($this->method(requiresProof: true));

        try {
            $this->validator($bookings, $methods)->validate($this->request($booking));
            self::fail('Missing required payment proof was accepted.');
        } catch (GuestPaymentException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Payment proof is required.', $exception->getMessage());
        }
    }

    public function testRepositoryAndClockFailuresBecomeSafeSystemFailures(): void
    {
        $booking = $this->booking();
        $request = $this->request($booking);
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willThrowException(
            new RuntimeException('SQLSTATE private details'),
        );
        $methods = $this->createStub(PaymentMethodCatalogRepository::class);

        foreach ([
            $this->validator($bookings, $methods),
            new GuestPaymentAccessValidator(
                $this->createStub(BookingCatalogRepository::class),
                $methods,
                new BookingAccessToken(self::KEY),
                static fn (): string => 'not a clock',
            ),
        ] as $validator) {
            try {
                $validator->validate($request);
                self::fail('Expected safe Guest payment system failure.');
            } catch (GuestPaymentException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Payment operation failed.', $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }
    }

    private function validator(
        BookingCatalogRepository $bookings,
        PaymentMethodCatalogRepository $methods,
    ): GuestPaymentAccessValidator {
        return new GuestPaymentAccessValidator(
            $bookings,
            $methods,
            new BookingAccessToken(self::KEY),
            static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
        );
    }

    private function request(
        Booking $booking,
        string $amount = '18000.00',
    ): GuestPaymentRequest {
        $token = (new BookingAccessToken(self::KEY))->issue(
            $booking->bookingReference(),
            $booking->confirmationDeadlineAt(),
        );

        return GuestPaymentRequest::fromInput(
            $booking->bookingReference(),
            $token,
            7,
            $amount,
            'BANK-001',
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
