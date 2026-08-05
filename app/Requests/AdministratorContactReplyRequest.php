<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\ContactException;

/** Validates one plain-text Administrator reply to a retained inquiry. */
final readonly class AdministratorContactReplyRequest
{
    private function __construct(private string $replyBody)
    {
    }

    public static function fromInput(mixed $replyBody): self
    {
        if (!is_string($replyBody) || !mb_check_encoding($replyBody, 'UTF-8')) {
            throw ContactException::invalidInput('Reply message is invalid.');
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($replyBody));

        if (
            $normalized === ''
            || mb_strlen($normalized, 'UTF-8') > 5000
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $normalized) === 1
        ) {
            throw ContactException::invalidInput(
                'Reply message must contain between 1 and 5000 valid characters.',
            );
        }

        return new self($normalized);
    }

    public function replyBody(): string
    {
        return $this->replyBody;
    }
}
