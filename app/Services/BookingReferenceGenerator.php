<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BookingException;
use Closure;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Generates a secure, non-sequential public reference within VARCHAR(30).
 */
final class BookingReferenceGenerator
{
    private const RANDOM_BYTES = 12;

    private const ENCODED_CHARACTERS = 20;

    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @var Closure(int): mixed */
    private readonly Closure $entropy;

    /**
     * @param null|callable(int): mixed $entropy
     */
    public function __construct(?callable $entropy = null)
    {
        $this->entropy = $entropy === null
            ? static fn (int $length): string => random_bytes($length)
            : Closure::fromCallable($entropy);
    }

    public function generate(DateTimeImmutable $generatedAt): string
    {
        try {
            if ($generatedAt->getOffset() !== 0) {
                throw new RuntimeException('The booking reference clock is invalid.');
            }

            $bytes = ($this->entropy)(self::RANDOM_BYTES);

            if (!is_string($bytes) || strlen($bytes) !== self::RANDOM_BYTES) {
                throw new RuntimeException('The booking reference entropy is invalid.');
            }

            $encoded = $this->base32($bytes);

            if (strlen($encoded) !== self::ENCODED_CHARACTERS) {
                throw new RuntimeException('The booking reference encoding is invalid.');
            }

            return 'TGH-' . $generatedAt->format('Y') . '-' . $encoded;
        } catch (BookingException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw BookingException::failure($failure);
        }
    }

    private function base32(string $bytes): string
    {
        $output = '';
        $buffer = 0;
        $bits = 0;

        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::ALPHABET[($buffer >> $bits) & 31];
                $buffer &= $bits === 0 ? 0 : (1 << $bits) - 1;
            }
        }

        if ($bits > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }
}
