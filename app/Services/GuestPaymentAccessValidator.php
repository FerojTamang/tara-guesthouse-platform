<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GuestPaymentException;
use App\Models\Booking;
use App\Models\GuestPaymentAccess;
use App\Models\PaymentMethod;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Requests\GuestPaymentRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Validates signed Guest access and current read-only payment prerequisites.
 *
 * S3-008 remains responsible for locking and rechecking these facts inside the
 * payment-submission transaction.
 */
final class GuestPaymentAccessValidator
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
            ? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : Closure::fromCallable($clock);
    }

    public function validate(GuestPaymentRequest $request): GuestPaymentAccess
    {
        try {
            $validatedAt = ($this->clock)();

            if (!$validatedAt instanceof DateTimeImmutable || $validatedAt->getOffset() !== 0) {
                throw new RuntimeException('The Guest payment clock returned invalid data.');
            }

            $booking = $this->bookings->findByReference($request->bookingReference());

            if (!$booking instanceof Booking) {
                throw GuestPaymentException::accessDenied();
            }

            if (!$this->tokens->validates(
                $request->bookingAccessToken(),
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

            $paymentMethod = $this->paymentMethods->findById($request->paymentMethodId());

            if (!$paymentMethod instanceof PaymentMethod || !$paymentMethod->isActive()) {
                throw GuestPaymentException::paymentMethodUnavailable();
            }

            if (
                $request->currency() !== Booking::CURRENCY_NPR
                || $booking->currency() !== Booking::CURRENCY_NPR
                || $request->amount() !== $booking->totalAmount()
            ) {
                throw GuestPaymentException::validation(
                    'Payment amount must match the booking total.',
                );
            }

            if ($paymentMethod->requiresProof() && !$request->hasProof()) {
                throw GuestPaymentException::validation('Payment proof is required.');
            }

            return new GuestPaymentAccess(
                $request,
                $booking,
                $paymentMethod,
                $validatedAt,
            );
        } catch (GuestPaymentException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }
}
