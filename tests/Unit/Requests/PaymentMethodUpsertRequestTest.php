<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\PaymentMethodException;
use App\Requests\PaymentMethodUpsertRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentMethodUpsertRequestTest extends TestCase
{
    public function testItNormalizesValidatedPaymentMethodInput(): void
    {
        $request = PaymentMethodUpsertRequest::fromInput(
            ' Bank transfer ',
            " Transfer the exact amount.\r\nUse the booking reference. ",
            ' TGH BANK 001 ',
            '1',
            '0',
        );

        self::assertSame('Bank transfer', $request->name());
        self::assertSame(
            "Transfer the exact amount.\nUse the booking reference.",
            $request->instructions(),
        );
        self::assertSame('TGH BANK 001', $request->accountReference());
        self::assertTrue($request->requiresProof());
        self::assertFalse($request->isActive());
    }

    public function testBlankOptionalValuesBecomeNull(): void
    {
        $request = PaymentMethodUpsertRequest::fromInput(
            'Cash deposit',
            " \r\n ",
            '   ',
            0,
            true,
        );

        self::assertNull($request->instructions());
        self::assertNull($request->accountReference());
        self::assertFalse($request->requiresProof());
        self::assertTrue($request->isActive());
    }

    #[DataProvider('invalidInput')]
    public function testItRejectsInvalidInput(string $field, array $arguments): void
    {
        try {
            PaymentMethodUpsertRequest::fromInput(...$arguments);
            self::fail('Invalid payment-method input was accepted.');
        } catch (PaymentMethodException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, array{mixed, mixed, mixed, mixed, mixed}}>
     */
    public static function invalidInput(): iterable
    {
        $valid = [
            'Bank transfer',
            'Transfer the exact booking total.',
            'BANK-001',
            '1',
            '1',
        ];

        yield 'blank name' => ['Name', ['', ...array_slice($valid, 1)]];
        yield 'controlled name' => ['Name', ["Bank\ntransfer", ...array_slice($valid, 1)]];
        yield 'long name' => ['Name', [str_repeat('a', 101), ...array_slice($valid, 1)]];
        yield 'invalid name type' => ['Name', [7, ...array_slice($valid, 1)]];
        yield 'controlled instructions' => ['Instructions', [
            $valid[0],
            "Safe\0unsafe",
            ...array_slice($valid, 2),
        ]];
        yield 'hidden instructions' => ['Instructions', [
            $valid[0],
            "Visible\u{200B}hidden",
            ...array_slice($valid, 2),
        ]];
        yield 'long instructions' => ['Instructions', [
            $valid[0],
            str_repeat('a', 5001),
            ...array_slice($valid, 2),
        ]];
        yield 'multiline account reference' => ['Account reference', [
            $valid[0],
            $valid[1],
            "BANK\n001",
            ...array_slice($valid, 3),
        ]];
        yield 'long account reference' => ['Account reference', [
            $valid[0],
            $valid[1],
            str_repeat('a', 256),
            ...array_slice($valid, 3),
        ]];
        yield 'implicit proof requirement' => ['Proof requirement', [
            $valid[0],
            $valid[1],
            $valid[2],
            null,
            $valid[4],
        ]];
        yield 'unsupported proof requirement' => ['Proof requirement', [
            $valid[0],
            $valid[1],
            $valid[2],
            'yes',
            $valid[4],
        ]];
        yield 'implicit active state' => ['Active state', [
            $valid[0],
            $valid[1],
            $valid[2],
            $valid[3],
            null,
        ]];
        yield 'unsupported active state' => ['Active state', [
            $valid[0],
            $valid[1],
            $valid[2],
            $valid[3],
            'yes',
        ]];
    }
}
