<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\ContactException;
use App\Requests\ContactMessageRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactMessageRequestTest extends TestCase
{
    public function testItNormalizesApprovedContactFields(): void
    {
        $request = ContactMessageRequest::fromInput(
            ' Asha Sharma ',
            ' ASHA@EXAMPLE.TEST ',
            ' +977 980-000-0000 ',
            ' Room availability ',
            " Hello.\r\nPlease share availability for next week. ",
        );

        self::assertSame('Asha Sharma', $request->name());
        self::assertSame('asha@example.test', $request->email());
        self::assertSame('+977 980-000-0000', $request->phone());
        self::assertSame('Room availability', $request->subject());
        self::assertSame(
            "Hello.\nPlease share availability for next week.",
            $request->message(),
        );
    }

    public function testPhoneIsOptional(): void
    {
        $request = ContactMessageRequest::fromInput(
            'Asha Sharma',
            'asha@example.test',
            '',
            'General inquiry',
            'Please send more information.',
        );

        self::assertNull($request->phone());
    }

    #[DataProvider('invalidInputProvider')]
    public function testItRejectsInvalidInput(
        mixed $name,
        mixed $email,
        mixed $phone,
        mixed $subject,
        mixed $message,
    ): void {
        try {
            ContactMessageRequest::fromInput(
                $name,
                $email,
                $phone,
                $subject,
                $message,
            );
            self::fail('Expected contact validation to fail.');
        } catch (ContactException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed, mixed, mixed, mixed, mixed}> */
    public static function invalidInputProvider(): iterable
    {
        $valid = [
            'Asha Sharma',
            'asha@example.test',
            '+9779800000000',
            'General inquiry',
            'Please send more information.',
        ];

        yield 'short name' => ['A', ...array_slice($valid, 1)];
        yield 'multiline name' => ["Asha\nSharma", ...array_slice($valid, 1)];
        yield 'invalid email' => [$valid[0], 'not-an-email', ...array_slice($valid, 2)];
        yield 'email too long' => [$valid[0], str_repeat('a', 145) . '@x.test', ...array_slice($valid, 2)];
        yield 'invalid phone' => [$valid[0], $valid[1], 'phone?', $valid[3], $valid[4]];
        yield 'phone too long' => [$valid[0], $valid[1], '+977' . str_repeat('1', 20), $valid[3], $valid[4]];
        yield 'short subject' => [$valid[0], $valid[1], $valid[2], 'Hi', $valid[4]];
        yield 'multiline subject' => [$valid[0], $valid[1], $valid[2], "General\ninquiry", $valid[4]];
        yield 'short message' => [$valid[0], $valid[1], $valid[2], $valid[3], 'Too short'];
        yield 'message control' => [$valid[0], $valid[1], $valid[2], $valid[3], "Unsafe\0message text"];
        yield 'array name' => [[], $valid[1], $valid[2], $valid[3], $valid[4]];
    }
}
