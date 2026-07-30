<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Payment;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PaymentTest extends TestCase
{
    public function testItExposesTheCompleteImmutableSubmittedAttempt(): void
    {
        $submittedAt = self::timestamp('2026-07-28 06:15:00');
        $createdAt = self::timestamp('2026-07-28 06:15:00');
        $updatedAt = self::timestamp('2026-07-28 06:16:00');
        $payment = new Payment(
            9,
            17,
            3,
            Payment::CURRENCY_NPR,
            '10000.00',
            'BANK-REFERENCE-001',
            'payment-proofs/fixture.pdf',
            'Paid from the Guest bank account.',
            Payment::STATUS_SUBMITTED,
            $submittedAt,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $updatedAt,
        );

        self::assertTrue((new ReflectionClass($payment))->isReadOnly());
        self::assertSame(9, $payment->id());
        self::assertSame(17, $payment->bookingId());
        self::assertSame(3, $payment->paymentMethodId());
        self::assertSame(Payment::CURRENCY_NPR, $payment->currency());
        self::assertSame('10000.00', $payment->amount());
        self::assertSame('BANK-REFERENCE-001', $payment->transactionReference());
        self::assertSame('payment-proofs/fixture.pdf', $payment->proofPath());
        self::assertSame('Paid from the Guest bank account.', $payment->guestNotes());
        self::assertSame(Payment::STATUS_SUBMITTED, $payment->paymentStatus());
        self::assertSame($submittedAt, $payment->submittedAt());
        self::assertNull($payment->verifiedAt());
        self::assertNull($payment->verifiedBy());
        self::assertNull($payment->rejectedAt());
        self::assertNull($payment->rejectedBy());
        self::assertNull($payment->verificationNote());
        self::assertSame($createdAt, $payment->createdAt());
        self::assertSame($updatedAt, $payment->updatedAt());
        self::assertFalse($payment->isPending());
        self::assertTrue($payment->isSubmitted());
        self::assertFalse($payment->isVerified());
        self::assertFalse($payment->isRejected());
        self::assertFalse($payment->isRefunded());
    }

    public function testCanonicalStatusesAndNullableReviewFieldsAreSupported(): void
    {
        foreach ([
            Payment::STATUS_PENDING,
            Payment::STATUS_SUBMITTED,
            Payment::STATUS_VERIFIED,
            Payment::STATUS_REJECTED,
            Payment::STATUS_REFUNDED,
        ] as $status) {
            $payment = self::payment([
                'paymentStatus' => $status,
                'submittedAt' => null,
                'verifiedAt' => null,
                'verifiedBy' => null,
                'rejectedAt' => null,
                'rejectedBy' => null,
                'verificationNote' => null,
            ]);

            self::assertSame($status, $payment->paymentStatus());
            self::assertSame($status === Payment::STATUS_PENDING, $payment->isPending());
            self::assertSame($status === Payment::STATUS_SUBMITTED, $payment->isSubmitted());
            self::assertSame($status === Payment::STATUS_VERIFIED, $payment->isVerified());
            self::assertSame($status === Payment::STATUS_REJECTED, $payment->isRejected());
            self::assertSame($status === Payment::STATUS_REFUNDED, $payment->isRefunded());
        }
    }

    public function testReviewedActorAndTimestampFieldsArePreserved(): void
    {
        $reviewedAt = self::timestamp('2026-07-28 07:00:00');
        $verified = self::payment([
            'paymentStatus' => Payment::STATUS_VERIFIED,
            'verifiedAt' => $reviewedAt,
            'verifiedBy' => 4,
            'verificationNote' => 'Verified against the supplied proof.',
        ]);
        $rejected = self::payment([
            'paymentStatus' => Payment::STATUS_REJECTED,
            'rejectedAt' => $reviewedAt,
            'rejectedBy' => 5,
            'verificationNote' => 'Reference did not match.',
        ]);

        self::assertSame($reviewedAt, $verified->verifiedAt());
        self::assertSame(4, $verified->verifiedBy());
        self::assertSame('Verified against the supplied proof.', $verified->verificationNote());
        self::assertSame($reviewedAt, $rejected->rejectedAt());
        self::assertSame(5, $rejected->rejectedBy());
        self::assertSame('Reference did not match.', $rejected->verificationNote());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidData')]
    public function testItRejectsInvalidPersistenceData(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment data is invalid.');

        self::payment($override);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidData(): iterable
    {
        yield 'invalid identifier' => [['id' => 0]];
        yield 'invalid booking identifier' => [['bookingId' => 0]];
        yield 'invalid method identifier' => [['paymentMethodId' => 0]];
        yield 'invalid currency' => [['currency' => 'USD']];
        yield 'zero amount' => [['amount' => '0.00']];
        yield 'malformed amount' => [['amount' => '10000']];
        yield 'overprecision amount' => [['amount' => '10000.000']];
        yield 'overlength reference' => [['transactionReference' => str_repeat('a', 101)]];
        yield 'absolute proof path' => [['proofPath' => '/payment-proofs/proof.pdf']];
        yield 'parent proof path' => [['proofPath' => 'payment-proofs/../proof.pdf']];
        yield 'backslash proof path' => [['proofPath' => 'payment-proofs\\proof.pdf']];
        yield 'unknown status' => [['paymentStatus' => 'completed']];
        yield 'case changed status' => [['paymentStatus' => 'SUBMITTED']];
        yield 'non UTC submitted timestamp' => [[
            'submittedAt' => new DateTimeImmutable('2026-07-28 06:15:00 +05:45'),
        ]];
        yield 'invalid verifier identifier' => [['verifiedBy' => 0]];
        yield 'invalid rejector identifier' => [['rejectedBy' => 0]];
        yield 'non UTC created timestamp' => [[
            'createdAt' => new DateTimeImmutable('2026-07-28 06:15:00 +05:45'),
        ]];
        yield 'non UTC updated timestamp' => [[
            'updatedAt' => new DateTimeImmutable('2026-07-28 06:15:00 +05:45'),
        ]];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function payment(array $override = []): Payment
    {
        $timestamp = self::timestamp('2026-07-28 06:15:00');

        return new Payment(...array_replace([
            'id' => 1,
            'bookingId' => 2,
            'paymentMethodId' => 3,
            'currency' => Payment::CURRENCY_NPR,
            'amount' => '10000.00',
            'transactionReference' => null,
            'proofPath' => null,
            'guestNotes' => null,
            'paymentStatus' => Payment::STATUS_SUBMITTED,
            'submittedAt' => $timestamp,
            'verifiedAt' => null,
            'verifiedBy' => null,
            'rejectedAt' => null,
            'rejectedBy' => null,
            'verificationNote' => null,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ], $override));
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
