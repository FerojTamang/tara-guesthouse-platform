<?php

declare(strict_types=1);

namespace App\Requests;

use App\Core\UploadedFile;
use App\Exceptions\GuestPaymentException;
use App\Exceptions\PaymentProofException;
use App\Models\Payment;
use LogicException;

/**
 * Holds normalized Guest payment input before booking and method authorization.
 */
final readonly class GuestPaymentRequest
{
    private function __construct(
        private string $bookingReference,
        private string $bookingAccessToken,
        private int $paymentMethodId,
        private string $amount,
        private ?string $transactionReference,
        private ?string $guestNotes,
        private ?PaymentProofUploadRequest $proof,
    ) {
    }

    /**
     * @param array<string, mixed> $proofConfiguration
     */
    public static function fromInput(
        mixed $bookingReference,
        #[\SensitiveParameter] mixed $bookingAccessToken,
        mixed $paymentMethodId,
        mixed $amount,
        mixed $transactionReference,
        mixed $guestNotes,
        mixed $proofUpload,
        array $proofConfiguration,
    ): self {
        try {
            return new self(
                self::normalizeBookingReference($bookingReference),
                self::normalizeBookingAccessToken($bookingAccessToken),
                self::positiveIdentifier($paymentMethodId),
                self::normalizeAmount($amount),
                self::optionalSingleLine(
                    $transactionReference,
                    100,
                    'Transaction reference is invalid.',
                ),
                self::optionalNotes($guestNotes),
                self::normalizeProof($proofUpload, $proofConfiguration),
            );
        } catch (GuestPaymentException $exception) {
            throw $exception;
        } catch (PaymentProofException $exception) {
            if ($exception->isValidationFailure()) {
                throw GuestPaymentException::validation($exception->getMessage());
            }

            throw GuestPaymentException::failure($exception);
        } catch (\Throwable $failure) {
            throw GuestPaymentException::failure($failure);
        }
    }

    public function bookingReference(): string
    {
        return $this->bookingReference;
    }

    public function bookingAccessToken(): string
    {
        return $this->bookingAccessToken;
    }

    public function paymentMethodId(): int
    {
        return $this->paymentMethodId;
    }

    public function currency(): string
    {
        return Payment::CURRENCY_NPR;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function transactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function guestNotes(): ?string
    {
        return $this->guestNotes;
    }

    public function proof(): ?PaymentProofUploadRequest
    {
        return $this->proof;
    }

    public function hasProof(): bool
    {
        return $this->proof !== null;
    }

    /**
     * Omits the signed booking-access token and upload internals from diagnostics.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'bookingReference' => $this->bookingReference,
            'paymentMethodId' => $this->paymentMethodId,
            'currency' => Payment::CURRENCY_NPR,
            'amount' => $this->amount,
            'transactionReference' => $this->transactionReference,
            'guestNotes' => $this->guestNotes,
            'hasProof' => $this->proof !== null,
        ];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Guest payment request serialization is not supported.');
    }

    /** @param array<mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Guest payment request unserialization is not supported.');
    }

    private static function normalizeBookingReference(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw GuestPaymentException::validation('Booking reference is invalid.');
        }

        $normalized = trim($value);

        if (
            $normalized === ''
            || strlen($normalized) > 30
            || preg_match('/[\x00-\x20\x7F]/', $normalized) === 1
        ) {
            throw GuestPaymentException::validation('Booking reference is invalid.');
        }

        return $normalized;
    }

    private static function normalizeBookingAccessToken(mixed $value): string
    {
        if (
            !is_string($value)
            || preg_match('/^v1\.[A-Za-z0-9_-]{43}$/D', $value) !== 1
        ) {
            throw GuestPaymentException::validation('Booking access is invalid.');
        }

        return $value;
    }

    private static function positiveIdentifier(mixed $value): int
    {
        if (is_int($value)) {
            $identifier = $value;
        } elseif (
            is_string($value)
            && preg_match('/^[1-9][0-9]*$/D', $value) === 1
        ) {
            $identifier = filter_var($value, FILTER_VALIDATE_INT);
        } else {
            throw GuestPaymentException::validation('Payment method is invalid.');
        }

        if (!is_int($identifier) || $identifier <= 0) {
            throw GuestPaymentException::validation('Payment method is invalid.');
        }

        return $identifier;
    }

    private static function normalizeAmount(mixed $value): string
    {
        if (!is_string($value)) {
            throw GuestPaymentException::validation('Payment amount is invalid.');
        }

        $normalized = trim($value);

        if (
            preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $normalized) !== 1
            || $normalized === '0.00'
        ) {
            throw GuestPaymentException::validation('Payment amount is invalid.');
        }

        return $normalized;
    }

    private static function optionalSingleLine(
        mixed $value,
        int $maximumCharacters,
        string $message,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw GuestPaymentException::validation($message);
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (
            mb_strlen($normalized, 'UTF-8') > $maximumCharacters
            || preg_match('/[\p{Cc}\p{Cf}]/u', $normalized) === 1
        ) {
            throw GuestPaymentException::validation($message);
        }

        return $normalized;
    }

    private static function optionalNotes(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw GuestPaymentException::validation('Guest notes are invalid.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($value));

        if ($normalized === '') {
            return null;
        }

        if (
            strlen($normalized) > 65535
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw GuestPaymentException::validation('Guest notes are invalid.');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function normalizeProof(
        mixed $value,
        array $configuration,
    ): ?PaymentProofUploadRequest {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UploadedFile && $value->error() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return PaymentProofUploadRequest::fromInput($value, $configuration);
    }
}
