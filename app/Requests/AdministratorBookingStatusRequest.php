<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\BookingException;
use App\Models\Booking;

/**
 * Validates the narrow Administrator-controlled Version 1 status actions.
 */
final readonly class AdministratorBookingStatusRequest
{
    private const ADMINISTRATOR_TARGETS = [
        Booking::STATUS_REJECTED,
        Booking::STATUS_CANCELLED,
        Booking::STATUS_CHECKED_IN,
        Booking::STATUS_CHECKED_OUT,
    ];

    private const REASON_TARGETS = [
        Booking::STATUS_REJECTED,
        Booking::STATUS_CANCELLED,
    ];

    private function __construct(
        private string $targetStatus,
        private ?string $reason,
    ) {
    }

    public static function fromInput(
        mixed $targetStatus,
        mixed $reason,
        bool $reasonProvided,
    ): self {
        if (
            !is_string($targetStatus)
            || $targetStatus === ''
            || $targetStatus !== trim($targetStatus)
            || !in_array($targetStatus, self::ADMINISTRATOR_TARGETS, true)
        ) {
            throw BookingException::validation(
                'Booking status action is invalid.',
            );
        }

        $requiresReason = in_array(
            $targetStatus,
            self::REASON_TARGETS,
            true,
        );

        if (!$requiresReason) {
            if ($reasonProvided && $reason !== null) {
                throw BookingException::validation(
                    'A reason is not accepted for this booking action.',
                );
            }

            return new self($targetStatus, null);
        }

        if (
            !$reasonProvided
            || !is_string($reason)
            || !mb_check_encoding($reason, 'UTF-8')
        ) {
            throw BookingException::validation(
                'A reason is required for this booking action.',
            );
        }

        if (
            preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u',
                $reason,
            ) === 1
        ) {
            throw BookingException::validation(
                'A reason is required for this booking action.',
            );
        }

        $normalizedReason = str_replace(
            ["\r\n", "\r"],
            "\n",
            trim($reason),
        );

        if (
            $normalizedReason === ''
            || mb_strlen($normalizedReason, 'UTF-8') > 1000
        ) {
            throw BookingException::validation(
                'A reason is required for this booking action.',
            );
        }

        return new self($targetStatus, $normalizedReason);
    }

    public function targetStatus(): string
    {
        return $this->targetStatus;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
