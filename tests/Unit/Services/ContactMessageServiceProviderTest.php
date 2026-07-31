<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ContactException;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContactMessageRepository;
use App\Services\ContactMessageService;
use App\Services\ContactMessageServiceProvider;
use App\Services\ContactTransaction;
use PHPUnit\Framework\TestCase;

final class ContactMessageServiceProviderTest extends TestCase
{
    public function testItBuildsAndCachesTheService(): void
    {
        $calls = 0;
        $service = new ContactMessageService(
            $this->createStub(ContactMessageRepository::class),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(ContactTransaction::class),
        );
        $provider = new ContactMessageServiceProvider(
            static function () use (&$calls, $service): ContactMessageService {
                $calls++;
                return $service;
            },
        );

        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryFailsSafely(): void
    {
        $provider = new ContactMessageServiceProvider(static fn (): object => new \stdClass());

        $this->expectException(ContactException::class);
        $this->expectExceptionMessage('Contact workflow operation failed.');
        $provider->service();
    }
}
