<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Requests\GuestBookingRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Supplies secure references and configured deadlines to transactional creation.
 */
final class BookingSubmissionService implements BookingSubmitter
{
    private const MAX_REFERENCE_ATTEMPTS = 5;

    /** @var Closure(): mixed */
    private readonly Closure $clock;

    /**
     * @param null|callable(): mixed $clock
     */
    public function __construct(
        private readonly BookingCreator $creator,
        private readonly BookingReferenceGenerator $references,
        private readonly BookingDeadlinePolicy $deadlines,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null
            ? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
            : Closure::fromCallable($clock);
    }

    public function submit(GuestBookingRequest $request): Booking
    {
        try {
            $acceptedAt = ($this->clock)();

            if (!$acceptedAt instanceof DateTimeImmutable || $acceptedAt->getOffset() !== 0) {
                throw new RuntimeException('The booking submission clock is invalid.');
            }

            $deadline = $this->deadlines->deadlineFrom($acceptedAt);

            for ($attempt = 1; $attempt <= self::MAX_REFERENCE_ATTEMPTS; $attempt++) {
                $reference = $this->references->generate($acceptedAt);

                try {
                    return $this->creator->createPending(
                        $request,
                        $reference,
                        $deadline,
                    );
                } catch (BookingException $exception) {
                    if (!$exception->isDuplicateReference()) {
                        throw $exception;
                    }

                    if ($attempt === self::MAX_REFERENCE_ATTEMPTS) {
                        throw BookingException::failure($exception);
                    }
                }
            }

            throw BookingException::failure();
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }
}
