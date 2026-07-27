<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Requests\AdministratorBookingStatusRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorBookingStatusRequestTest extends TestCase
{
    #[DataProvider('reasonActions')]
    public function testReasonActionsNormalizeRequiredPlainText(
        string $status,
    ): void {
        $request = AdministratorBookingStatusRequest::fromInput(
            $status,
            "  Operational reason.\r\nSecond line.  ",
            true,
        );

        self::assertSame($status, $request->targetStatus());
        self::assertSame(
            "Operational reason.\nSecond line.",
            $request->reason(),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function reasonActions(): iterable
    {
        yield 'reject' => [Booking::STATUS_REJECTED];
        yield 'cancel' => [Booking::STATUS_CANCELLED];
    }

    #[DataProvider('reasonlessActions')]
    public function testOperationalActionsRequireReasonToBeOmitted(
        string $status,
    ): void {
        $request = AdministratorBookingStatusRequest::fromInput(
            $status,
            null,
            false,
        );

        self::assertSame($status, $request->targetStatus());
        self::assertNull($request->reason());
    }

    /** @return iterable<string, array{string}> */
    public static function reasonlessActions(): iterable
    {
        yield 'check in' => [Booking::STATUS_CHECKED_IN];
        yield 'check out' => [Booking::STATUS_CHECKED_OUT];
    }

    #[DataProvider('invalidTargets')]
    public function testOnlyNarrowAdministratorTargetsAreAccepted(
        mixed $status,
    ): void {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Booking status action is invalid.');

        AdministratorBookingStatusRequest::fromInput(
            $status,
            null,
            false,
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTargets(): iterable
    {
        yield 'missing' => [null];
        yield 'nested' => [['cancelled']];
        yield 'pending' => [Booking::STATUS_PENDING];
        yield 'confirmed' => [Booking::STATUS_CONFIRMED];
        yield 'terminal case mismatch' => ['CANCELLED'];
        yield 'unsupported' => ['completed'];
        yield 'surrounding whitespace' => [' cancelled '];
    }

    #[DataProvider('invalidReasons')]
    public function testCancelAndRejectRequireAValidReason(
        mixed $reason,
        bool $provided,
    ): void {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage(
            'A reason is required for this booking action.',
        );

        AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_CANCELLED,
            $reason,
            $provided,
        );
    }

    /** @return iterable<string, array{mixed, bool}> */
    public static function invalidReasons(): iterable
    {
        yield 'omitted' => [null, false];
        yield 'null' => [null, true];
        yield 'empty' => ['', true];
        yield 'whitespace' => ['   ', true];
        yield 'nested' => [['reason'], true];
        yield 'control' => ["Reason\x00", true];
        yield 'too long' => [str_repeat('a', 1001), true];
    }

    #[DataProvider('unexpectedReasons')]
    public function testCheckInAndOutRejectAnySuppliedReason(
        mixed $reason,
    ): void {
        $this->expectException(BookingException::class);
        $this->expectExceptionMessage(
            'A reason is not accepted for this booking action.',
        );

        AdministratorBookingStatusRequest::fromInput(
            Booking::STATUS_CHECKED_IN,
            $reason,
            true,
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function unexpectedReasons(): iterable
    {
        yield 'empty string' => [''];
        yield 'text' => ['Not required'];
        yield 'nested' => [['text']];
    }
}
