<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\ViewRenderer;
use App\Exceptions\MailException;
use App\Models\MailMessage;
use App\Services\MailTemplateRenderer;
use App\Services\MailTransport;
use App\Services\TransactionalMailService;
use App\Services\TransactionalMailServiceProvider;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransactionalMailServiceProviderTest extends TestCase
{
    public function testValidServiceIsCached(): void
    {
        $service = new TransactionalMailService(
            new ProviderNoOpMailTransport(),
            new MailTemplateRenderer(new ViewRenderer(
                dirname(__DIR__, 3) . '/resources/views',
            )),
            'Tara Guest House',
            'https://stay.example.test',
            new DateTimeZone('Asia/Kathmandu'),
        );
        $calls = 0;
        $provider = new TransactionalMailServiceProvider(
            static function () use (&$calls, $service): TransactionalMailService {
                $calls++;
                return $service;
            },
        );

        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryResultIsSanitizedAndDetached(): void
    {
        $provider = new TransactionalMailServiceProvider(
            static fn (): object => new \stdClass(),
        );

        try {
            $provider->service();
            self::fail('Expected invalid provider result failure.');
        } catch (MailException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Transactional email operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testUnexpectedFactoryFailureIsSanitizedAndDetached(): void
    {
        $provider = new TransactionalMailServiceProvider(
            static fn (): never => throw new RuntimeException('private SMTP password details'),
        );

        try {
            $provider->service();
            self::fail('Expected provider failure.');
        } catch (MailException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Transactional email operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('private', $exception->getMessage());
        }
    }
}

final class ProviderNoOpMailTransport implements MailTransport
{
    public function send(MailMessage $message): void
    {
    }
}
