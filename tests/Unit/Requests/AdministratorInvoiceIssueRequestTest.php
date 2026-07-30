<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\AdministratorInvoiceException;
use App\Requests\AdministratorInvoiceIssueRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorInvoiceIssueRequestTest extends TestCase
{
    public function testEmptyPayloadIsAccepted(): void
    {
        self::assertInstanceOf(
            AdministratorInvoiceIssueRequest::class,
            AdministratorInvoiceIssueRequest::fromPayload([]),
        );
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('serverControlledPayloads')]
    public function testServerControlledFieldsAreRejected(array $payload): void
    {
        try {
            AdministratorInvoiceIssueRequest::fromPayload($payload);
            self::fail('Expected invoice payload rejection.');
        } catch (AdministratorInvoiceException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame(
                'Invoice totals and numbers are derived by the server.',
                $exception->getMessage(),
            );
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function serverControlledPayloads(): iterable
    {
        yield 'invoice number' => [['invoice_number' => 'TGH-INV-2026-000001']];
        yield 'total' => [['total_amount' => '1.00']];
        yield 'tax' => [['tax_amount' => '0.00']];
        yield 'currency' => [['currency' => 'USD']];
        yield 'unknown field' => [['anything' => true]];
    }
}
