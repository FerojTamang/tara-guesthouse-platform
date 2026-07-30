<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\AdministratorPaymentException;
use App\Models\Payment;
use App\Requests\AdministratorPaymentStatusRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorPaymentStatusRequestTest extends TestCase
{
    public function testItNormalizesApprovedReviewActions(): void
    {
        $request = AdministratorPaymentStatusRequest::fromInput(
            Payment::STATUS_VERIFIED,
            "  Reference and amount confirmed.\r\nProof reviewed.  ",
            true,
        );

        self::assertSame(Payment::STATUS_VERIFIED, $request->targetStatus());
        self::assertSame(
            "Reference and amount confirmed.\nProof reviewed.",
            $request->verificationNote(),
        );
    }

    #[DataProvider('invalidInputs')]
    public function testItRejectsInvalidOrUnapprovedInput(
        mixed $status,
        mixed $note,
        bool $noteProvided,
    ): void {
        try {
            AdministratorPaymentStatusRequest::fromInput(
                $status,
                $note,
                $noteProvided,
            );
            self::fail('Expected invalid payment review input.');
        } catch (AdministratorPaymentException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed, mixed, bool}> */
    public static function invalidInputs(): iterable
    {
        yield 'missing status' => [null, 'Reviewed.', true];
        yield 'pending target' => [Payment::STATUS_PENDING, 'Reviewed.', true];
        yield 'refunded target' => [Payment::STATUS_REFUNDED, 'Reviewed.', true];
        yield 'trimmed target required' => [' verified ', 'Reviewed.', true];
        yield 'missing note key' => [Payment::STATUS_VERIFIED, null, false];
        yield 'null note' => [Payment::STATUS_VERIFIED, null, true];
        yield 'empty note' => [Payment::STATUS_VERIFIED, '   ', true];
        yield 'control character' => [Payment::STATUS_REJECTED, "Invalid\0note", true];
        yield 'format control' => [Payment::STATUS_REJECTED, "Invalid\u{200B}note", true];
        yield 'too long' => [
            Payment::STATUS_VERIFIED,
            str_repeat('a', AdministratorPaymentStatusRequest::MAXIMUM_NOTE_CHARACTERS + 1),
            true,
        ];
        yield 'non string note' => [Payment::STATUS_REJECTED, ['note'], true];
    }
}
