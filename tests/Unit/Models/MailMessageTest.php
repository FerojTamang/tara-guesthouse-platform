<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\MailMessage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailMessageTest extends TestCase
{
    public function testApprovedUtf8MessageIsRetainedExactly(): void
    {
        $message = new MailMessage(
            'guest@example.test',
            'Asha Tamang',
            'Booking received — Tara Guest House',
            "Plain text body.\nSecond line.",
            '<p>Safe HTML body.</p>',
        );

        self::assertSame('guest@example.test', $message->recipientAddress());
        self::assertSame('Asha Tamang', $message->recipientName());
        self::assertSame('Booking received — Tara Guest House', $message->subject());
        self::assertSame("Plain text body.\nSecond line.", $message->textBody());
        self::assertSame('<p>Safe HTML body.</p>', $message->htmlBody());
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidHeadersAddressesAndBodiesAreRejected(
        string $address,
        string $name,
        string $subject,
        string $text,
        string $html,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        new MailMessage($address, $name, $subject, $text, $html);
    }

    /** @return iterable<string, array{string, string, string, string, string}> */
    public static function invalidProvider(): iterable
    {
        yield 'invalid address' => ['not-an-email', 'Guest', 'Subject', 'Text', '<p>HTML</p>'];
        yield 'address injection' => ["guest@example.test\r\nBcc:a@example.test", 'Guest', 'Subject', 'Text', '<p>HTML</p>'];
        yield 'name injection' => ['guest@example.test', "Guest\r\nBcc:a@example.test", 'Subject', 'Text', '<p>HTML</p>'];
        yield 'empty subject' => ['guest@example.test', 'Guest', '', 'Text', '<p>HTML</p>'];
        yield 'subject injection' => ['guest@example.test', 'Guest', "Subject\nBcc:a@example.test", 'Text', '<p>HTML</p>'];
        yield 'empty text' => ['guest@example.test', 'Guest', 'Subject', '', '<p>HTML</p>'];
        yield 'empty html' => ['guest@example.test', 'Guest', 'Subject', 'Text', ''];
        yield 'nul body' => ['guest@example.test', 'Guest', 'Subject', "Text\0", '<p>HTML</p>'];
    }
}
