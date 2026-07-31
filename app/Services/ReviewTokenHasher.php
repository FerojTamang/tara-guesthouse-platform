<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReviewException;
use Closure;
use SensitiveParameter;
use Throwable;

/** Generates high-entropy review tokens and stores only deterministic hashes. */
final class ReviewTokenHasher
{
    private const TOKEN_BYTES = 32;
    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/D';

    /** @var Closure(int): mixed */
    private readonly Closure $randomBytes;

    /** @param null|callable(int): mixed $randomBytes */
    public function __construct(?callable $randomBytes = null)
    {
        $this->randomBytes = Closure::fromCallable(
            $randomBytes ?? static fn (int $length): string => random_bytes($length),
        );
    }

    public function generate(): string
    {
        try {
            $bytes = ($this->randomBytes)(self::TOKEN_BYTES);

            if (!is_string($bytes) || strlen($bytes) !== self::TOKEN_BYTES) {
                throw ReviewException::failure();
            }

            return bin2hex($bytes);
        } catch (ReviewException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ReviewException::failure($failure);
        }
    }

    public function hash(#[SensitiveParameter] string $token): string
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            throw ReviewException::invalidInput('The review token is invalid.');
        }

        return hash('sha256', $token);
    }
}
