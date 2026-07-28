<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\AvailabilityException;
use App\Requests\AvailabilityRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AvailabilityRequestTest extends TestCase
{
    public function testItNormalizesDatesAndOccupancyWithoutApplyingUnapprovedStayLimits(): void
    {
        $request = AvailabilityRequest::fromInput(
            ' 2026-08-10 ',
            '2026-08-12',
            '002',
            '000',
        );

        self::assertSame('2026-08-10', $request->checkIn()->format('Y-m-d'));
        self::assertSame('2026-08-12', $request->checkOut()->format('Y-m-d'));
        self::assertSame(0, $request->checkIn()->getOffset());
        self::assertSame(0, $request->checkOut()->getOffset());
        self::assertSame(2, $request->nights());
        self::assertSame(2, $request->adults());
        self::assertSame(0, $request->children());
        self::assertSame(2, $request->totalGuests());
        self::assertSame($request->checkIn(), $request->stayPeriod()->checkIn());
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidInput')]
    public function testItRejectsMalformedDatesRangesAndOccupancy(array $override): void
    {
        try {
            self::request($override);
            self::fail('Expected availability validation to fail.');
        } catch (AvailabilityException $exception) {
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
        yield 'non-string check-in' => [['checkIn' => 20260810]];
        yield 'blank check-in' => [['checkIn' => '']];
        yield 'non-padded check-in' => [['checkIn' => '2026-8-10']];
        yield 'invalid calendar check-in' => [['checkIn' => '2026-02-30']];
        yield 'check-in contains time' => [['checkIn' => '2026-08-10 00:00:00']];
        yield 'invalid leap day' => [['checkIn' => '2025-02-29']];
        yield 'non-string check-out' => [['checkOut' => null]];
        yield 'same-day checkout' => [['checkOut' => '2026-08-10']];
        yield 'checkout before check-in' => [['checkOut' => '2026-08-09']];
        yield 'zero adults integer' => [['adults' => 0]];
        yield 'zero adults string' => [['adults' => '0']];
        yield 'negative adults' => [['adults' => '-1']];
        yield 'decimal adults' => [['adults' => '1.5']];
        yield 'boolean adults' => [['adults' => true]];
        yield 'overflow adults' => [['adults' => '2147483648']];
        yield 'negative children' => [['children' => '-1']];
        yield 'decimal children' => [['children' => 1.5]];
        yield 'blank children' => [['children' => '']];
        yield 'overflow children' => [['children' => str_repeat('9', 20)]];
    }

    /**
     * @param array<string, mixed> $override
     */
    private static function request(array $override = []): AvailabilityRequest
    {
        $input = array_replace([
            'checkIn' => '2026-08-10',
            'checkOut' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
        ], $override);

        return AvailabilityRequest::fromInput(
            $input['checkIn'],
            $input['checkOut'],
            $input['adults'],
            $input['children'],
        );
    }
}
