<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AvailabilityException;
use App\Exceptions\BookingException;
use App\Models\StayPeriod;

final readonly class GuestBookingRequest
{
    private function __construct(
        private int $roomId,
        private string $guestFullName,
        private string $guestEmail,
        private string $guestPhone,
        private StayPeriod $stayPeriod,
        private int $adults,
        private int $children,
        private ?string $specialRequest,
        private ?string $policyVersion,
    ) {
    }

    public static function fromInput(
        mixed $roomId,
        mixed $guestFullName,
        mixed $guestEmail,
        mixed $guestPhone,
        mixed $checkIn,
        mixed $checkOut,
        mixed $adults,
        mixed $children,
        mixed $specialRequest,
        mixed $termsAccepted,
        mixed $policyVersion,
    ): self {
        try {
            $normalizedRoomId = AvailabilityInput::positiveInteger($roomId, 'Room');
            $availability = AvailabilityRequest::fromInput(
                $checkIn,
                $checkOut,
                $adults,
                $children,
            );
        } catch (AvailabilityException $exception) {
            throw BookingException::validation($exception->getMessage());
        }

        if ($termsAccepted !== true) {
            throw BookingException::validation('Terms must be accepted.');
        }

        return new self(
            $normalizedRoomId,
            self::normalizeFullName($guestFullName),
            self::normalizeEmail($guestEmail),
            self::normalizePhone($guestPhone),
            $availability->stayPeriod(),
            $availability->adults(),
            $availability->children(),
            self::normalizeSpecialRequest($specialRequest),
            self::normalizePolicyVersion($policyVersion),
        );
    }

    public function roomId(): int
    {
        return $this->roomId;
    }

    public function guestFullName(): string
    {
        return $this->guestFullName;
    }

    public function guestEmail(): string
    {
        return $this->guestEmail;
    }

    public function guestPhone(): string
    {
        return $this->guestPhone;
    }

    public function stayPeriod(): StayPeriod
    {
        return $this->stayPeriod;
    }

    public function adults(): int
    {
        return $this->adults;
    }

    public function children(): int
    {
        return $this->children;
    }

    public function specialRequest(): ?string
    {
        return $this->specialRequest;
    }

    public function policyVersion(): ?string
    {
        return $this->policyVersion;
    }

    private static function normalizeFullName(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw BookingException::validation('Guest full name is invalid.');
        }

        if (preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            throw BookingException::validation('Guest full name is invalid.');
        }

        $normalized = preg_replace('/[\p{Zs}\t ]+/u', ' ', trim($value));

        if (!is_string($normalized)) {
            throw BookingException::validation('Guest full name is invalid.');
        }

        $length = mb_strlen($normalized, 'UTF-8');

        if (
            $length < 2
            || $length > 100
            || preg_match('/\p{L}/u', $normalized) !== 1
        ) {
            throw BookingException::validation('Guest full name is invalid.');
        }

        return $normalized;
    }

    private static function normalizeEmail(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw BookingException::validation('Guest email is invalid.');
        }

        $normalized = strtolower(trim($value));

        if (
            $normalized === ''
            || strlen($normalized) > 150
            || preg_match('/[\x00-\x20\x7F]/', $normalized) === 1
            || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw BookingException::validation('Guest email is invalid.');
        }

        return $normalized;
    }

    private static function normalizePhone(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw BookingException::validation('Guest phone is invalid.');
        }

        $normalized = trim($value);
        $digitCount = preg_match_all('/[0-9]/', $normalized);

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > 20
            || preg_match('/^\+?[0-9][0-9 ()-]*$/D', $normalized) !== 1
            || $digitCount === false
            || $digitCount < 7
            || $digitCount > 15
        ) {
            throw BookingException::validation('Guest phone is invalid.');
        }

        return $normalized;
    }

    private static function normalizeSpecialRequest(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw BookingException::validation('Special request is invalid.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($value));

        if ($normalized === '') {
            return null;
        }

        if (
            strlen($normalized) > 65535
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw BookingException::validation('Special request is invalid.');
        }

        return $normalized;
    }

    private static function normalizePolicyVersion(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw BookingException::validation('Policy version is invalid.');
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > 50
            || preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1
        ) {
            throw BookingException::validation('Policy version is invalid.');
        }

        return $normalized;
    }
}
