<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\AdministratorPaymentException;
use App\Models\Payment;

/**
 * Validates the narrow Administrator-controlled payment review action.
 */
final readonly class AdministratorPaymentStatusRequest
{
    public const MAXIMUM_NOTE_CHARACTERS = 1000;

    private const TARGETS = [
        Payment::STATUS_VERIFIED,
        Payment::STATUS_REJECTED,
    ];

    private function __construct(
        private string $targetStatus,
        private string $verificationNote,
    ) {
    }

    public static function fromInput(
        mixed $targetStatus,
        mixed $verificationNote,
        bool $verificationNoteProvided,
    ): self {
        if (
            !is_string($targetStatus)
            || $targetStatus === ''
            || $targetStatus !== trim($targetStatus)
            || !in_array($targetStatus, self::TARGETS, true)
        ) {
            throw AdministratorPaymentException::validation(
                'Payment status action is invalid.',
            );
        }

        if (
            !$verificationNoteProvided
            || !is_string($verificationNote)
            || !mb_check_encoding($verificationNote, 'UTF-8')
            || preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u',
                $verificationNote,
            ) === 1
        ) {
            throw AdministratorPaymentException::validation(
                'A verification or rejection note is required.',
            );
        }

        $normalizedNote = str_replace(
            ["\r\n", "\r"],
            "\n",
            trim($verificationNote),
        );

        if (
            $normalizedNote === ''
            || mb_strlen($normalizedNote, 'UTF-8') > self::MAXIMUM_NOTE_CHARACTERS
        ) {
            throw AdministratorPaymentException::validation(
                'A verification or rejection note is required and must not exceed 1,000 characters.',
            );
        }

        return new self($targetStatus, $normalizedNote);
    }

    public function targetStatus(): string
    {
        return $this->targetStatus;
    }

    public function verificationNote(): string
    {
        return $this->verificationNote;
    }
}
