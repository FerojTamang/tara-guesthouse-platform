<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\BookingException;
use App\Requests\GuestBookingRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuestBookingRequestTest extends TestCase
{
    public function testItNormalizesTheApprovedGuestSnapshot(): void
    {
        $request = self::makeBookingRequest([
            'guestFullName' => "  Asha\u{00A0}  Sharma  ",
            'guestEmail' => ' ASHA@EXAMPLE.TEST ',
            'guestPhone' => ' +977 (980) 000-0000 ',
            'specialRequest' => " Late arrival.\r\nQuiet room. ",
            'policyVersion' => ' booking-policy-v1 ',
        ]);

        self::assertSame(12, $request->roomId());
        self::assertSame('Asha Sharma', $request->guestFullName());
        self::assertSame('asha@example.test', $request->guestEmail());
        self::assertSame('+977 (980) 000-0000', $request->guestPhone());
        self::assertSame('2026-08-10', $request->stayPeriod()->checkIn()->format('Y-m-d'));
        self::assertSame('2026-08-12', $request->stayPeriod()->checkOut()->format('Y-m-d'));
        self::assertSame(2, $request->adults());
        self::assertSame(0, $request->children());
        self::assertSame("Late arrival.\nQuiet room.", $request->specialRequest());
        self::assertSame('booking-policy-v1', $request->policyVersion());
    }

    public function testOptionalTextFieldsMayBeAbsent(): void
    {
        $request = self::makeBookingRequest([
            'specialRequest' => '   ',
            'policyVersion' => null,
        ]);

        self::assertNull($request->specialRequest());
        self::assertNull($request->policyVersion());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidInput')]
    public function testItRejectsInvalidInput(array $override): void
    {
        try {
            self::makeBookingRequest($override);
            self::fail('Expected booking validation to fail.');
        } catch (BookingException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertFalse($exception->isSystemFailure());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidInput(): iterable
    {
        yield 'room array' => [['roomId' => [12]]];
        yield 'zero room' => [['roomId' => 0]];
        yield 'blank name' => [['guestFullName' => '']];
        yield 'short name' => [['guestFullName' => 'A']];
        yield 'name without letter' => [['guestFullName' => '---']];
        yield 'name control' => [['guestFullName' => "Asha\nSharma"]];
        yield 'long name' => [['guestFullName' => str_repeat('A', 101)]];
        yield 'invalid email' => [['guestEmail' => 'not-an-email']];
        yield 'email control' => [['guestEmail' => "asha@\nexample.test"]];
        yield 'blank phone' => [['guestPhone' => '']];
        yield 'short phone' => [['guestPhone' => '123456']];
        yield 'phone letters' => [['guestPhone' => '+977-CALL-NOW']];
        yield 'invalid check-in' => [['checkIn' => '2026-02-30']];
        yield 'same-day checkout' => [['checkOut' => '2026-08-10']];
        yield 'zero adults' => [['adults' => 0]];
        yield 'negative children' => [['children' => -1]];
        yield 'special request array' => [['specialRequest' => ['quiet']]];
        yield 'special request control' => [['specialRequest' => "Quiet\0room"]];
        yield 'long policy version' => [['policyVersion' => str_repeat('p', 51)]];
        yield 'terms false' => [['termsAccepted' => false]];
        yield 'terms string' => [['termsAccepted' => 'true']];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function makeBookingRequest(array $override = []): GuestBookingRequest
    {
        $input = array_replace([
            'roomId' => 12,
            'guestFullName' => 'Asha Sharma',
            'guestEmail' => 'asha@example.test',
            'guestPhone' => '+9779800000000',
            'checkIn' => '2026-08-10',
            'checkOut' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'specialRequest' => 'Late arrival.',
            'termsAccepted' => true,
            'policyVersion' => 'booking-policy-v1',
        ], $override);

        return GuestBookingRequest::fromInput(
            $input['roomId'],
            $input['guestFullName'],
            $input['guestEmail'],
            $input['guestPhone'],
            $input['checkIn'],
            $input['checkOut'],
            $input['adults'],
            $input['children'],
            $input['specialRequest'],
            $input['termsAccepted'],
            $input['policyVersion'],
        );
    }
}
