<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GuestPaymentException;
use App\Models\Booking;
use App\Models\GuestPaymentPageContext;
use App\Models\PaymentMethod;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\PaymentMethodCatalogRepository;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Validates the private signed continuation and builds safe payment-page data.
 */
final class GuestPaymentPageService
{
    /** @var Closure(): mixed */
    private readonly Closure $clock;

    /** @param null|callable(): mixed $clock */
    public function __construct(
        private readonly BookingCatalogRepository $bookings,
        private readonly PaymentMethodCatalogRepository $paymentMethods,
        private readonly BookingAccessToken $tokens,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable(
                'now',
                new DateTimeZone('UTC'),
            )
            : Closure::fromCallable($clock);
    }

    public function load(
        string $bookingReference,
        #[\SensitiveParameter] string $bookingAccessToken,
    ): GuestPaymentPageContext {
        try {
            $validatedAt = ($this->clock)();

            if (!$validatedAt instanceof DateTimeImmutable || $validatedAt->getOffset() !== 0) {
                throw new RuntimeException('The payment page clock is invalid.');
            }

            $booking = $this->bookings->findByReference($bookingReference);

            if (!$booking instanceof Booking) {
                throw GuestPaymentException::accessDenied();
            }

            if (!$this->tokens->validates(
                $bookingAccessToken,
                $booking->bookingReference(),
                $booking->confirmationDeadlineAt(),
                $validatedAt,
            )) {
                throw GuestPaymentException::accessDenied();
            }

            if (
                $booking->bookingStatus() !== Booking::STATUS_PENDING
                || $validatedAt >= $booking->confirmationDeadlineAt()
            ) {
                throw GuestPaymentException::bookingConflict();
            }

            $methods = $this->paymentMethods->findAllActive();

            foreach ($methods as $method) {
                if (!$method instanceof PaymentMethod || !$method->isActive()) {
                    throw new RuntimeException('The active payment methods are invalid.');
                }
            }

            return new GuestPaymentPageContext($booking, $methods, $validatedAt);
        } catch (GuestPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }
}
