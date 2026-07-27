<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use DateTimeImmutable;
use Throwable;

/**
 * Issues an opaque HMAC-SHA256 token scoped to one booking follow-up window.
 */
final readonly class BookingAccessToken
{
    private const VERSION = 'v1';

    private const SCOPE = 'booking_follow_up';

    public function __construct(private string $key)
    {
        if (strlen($key) < 32) {
            throw BookingException::failure();
        }
    }

    public function issue(string $reference, DateTimeImmutable $expiresAt): string
    {
        try {
            $signature = hash_hmac(
                'sha256',
                $this->payload($reference, $expiresAt),
                $this->key,
                true,
            );

            return self::VERSION . '.' . rtrim(
                strtr(base64_encode($signature), '+/', '-_'),
                '=',
            );
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    public function validates(
        string $token,
        string $reference,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $asOf,
    ): bool {
        try {
            if (
                $asOf->getOffset() !== 0
                || $expiresAt->getOffset() !== 0
                || $asOf >= $expiresAt
                || preg_match('/^v1\.[A-Za-z0-9_-]{43}$/D', $token) !== 1
            ) {
                return false;
            }

            return hash_equals($this->issue($reference, $expiresAt), $token);
        } catch (Throwable) {
            return false;
        }
    }

    private function payload(string $reference, DateTimeImmutable $expiresAt): string
    {
        if (
            $reference === ''
            || $reference !== trim($reference)
            || strlen($reference) > 30
            || preg_match('/[\x00-\x20\x7F]/', $reference) === 1
            || $expiresAt->getOffset() !== 0
        ) {
            throw BookingException::failure();
        }

        return self::VERSION
            . "\n"
            . self::SCOPE
            . "\n"
            . $reference
            . "\n"
            . $expiresAt->format('Y-m-d\TH:i:s\Z');
    }
}
