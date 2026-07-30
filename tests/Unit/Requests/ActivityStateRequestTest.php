<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\ActivityException;
use App\Requests\ActivityStateRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivityStateRequestTest extends TestCase
{
    public function testItAcceptsExactJsonBooleans(): void
    {
        self::assertTrue(ActivityStateRequest::fromInput(true, 'Active')->enabled());
        self::assertFalse(ActivityStateRequest::fromInput(false, 'Published')->enabled());
    }

    #[DataProvider('invalidStateProvider')]
    public function testItRejectsCoercibleOrStructuredValues(mixed $value): void
    {
        try {
            ActivityStateRequest::fromInput($value, 'Activity state');
            self::fail('Expected exact boolean validation to fail.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Activity state must be true or false.', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidStateProvider(): iterable
    {
        yield 'integer one' => [1];
        yield 'integer zero' => [0];
        yield 'string true' => ['true'];
        yield 'string one' => ['1'];
        yield 'null' => [null];
        yield 'array' => [[]];
    }
}
