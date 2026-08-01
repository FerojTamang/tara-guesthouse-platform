<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\ViewRenderer;
use App\Exceptions\MailException;
use App\Models\MailMessage;
use App\Services\LoggingTransactionalMailDispatcher;
use App\Services\MailTemplateRenderer;
use App\Services\MailTransport;
use App\Services\TransactionalMailService;
use App\Services\TransactionalMailServiceProvider;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Tests\Support\MailFixture;

final class LoggingTransactionalMailDispatcherTest extends TestCase
{
    public function testFailureLoggingContainsOnlySafeEventAndEntityMetadata(): void
    {
        $logger = new RecordingMailLogger();
        $dispatcher = new LoggingTransactionalMailDispatcher(
            new TransactionalMailServiceProvider(
                static fn (): TransactionalMailService => new TransactionalMailService(
                    new AlwaysFailingMailTransport(),
                    new MailTemplateRenderer(new ViewRenderer(
                        dirname(__DIR__, 3) . '/resources/views',
                    )),
                    'Tara Guest House',
                    'https://stay.example.test',
                    new DateTimeZone('Asia/Kathmandu'),
                ),
            ),
            $logger,
        );
        $token = str_repeat('ef', 32);
        $booking = MailFixture::booking('checked_out', 'Private Guest', 'private@example.test');
        $invitation = MailFixture::invitation($token);

        $dispatcher->reviewInvitation($booking, $invitation);

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('transactional_mail_delivery_failed', $logger->records[0]['message']);
        self::assertSame([
            'mail_event' => 'review_invitation',
            'booking_id' => 31,
            'review_invitation_id' => 77,
        ], $logger->records[0]['context']);
        $encoded = json_encode($logger->records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($token, $encoded);
        self::assertStringNotContainsString('private@example.test', $encoded);
        self::assertStringNotContainsString('Private Guest', $encoded);
    }

    public function testInvalidStatusFailureDoesNotPlaceCallerTextInLogs(): void
    {
        $logger = new RecordingMailLogger();
        $dispatcher = new LoggingTransactionalMailDispatcher(
            new TransactionalMailServiceProvider(
                static fn (): TransactionalMailService => new TransactionalMailService(
                    new AlwaysFailingMailTransport(),
                    new MailTemplateRenderer(new ViewRenderer(
                        dirname(__DIR__, 3) . '/resources/views',
                    )),
                    'Tara Guest House',
                    'https://stay.example.test',
                    new DateTimeZone('Asia/Kathmandu'),
                ),
            ),
            $logger,
        );
        $privateStatus = "cancelled\nprivate-token";

        $dispatcher->bookingStatusChanged(MailFixture::booking(), $privateStatus);

        self::assertCount(1, $logger->records);
        self::assertSame([
            'mail_event' => 'booking_status_invalid',
            'booking_id' => 31,
        ], $logger->records[0]['context']);
        self::assertStringNotContainsString(
            $privateStatus,
            json_encode($logger->records, JSON_THROW_ON_ERROR),
        );
    }

}

final class AlwaysFailingMailTransport implements MailTransport
{
    public function send(MailMessage $message): void
    {
        throw MailException::transport();
    }
}

final class RecordingMailLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
