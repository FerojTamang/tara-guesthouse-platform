<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\NotificationException;
use App\Repositories\NotificationRepository;
use App\Services\AdministratorNotificationService;
use App\Services\AdministratorNotificationServiceProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorNotificationServiceProviderTest extends TestCase
{
    public function testProviderCachesOneValidService(): void
    {
        $calls = 0;
        $service = new AdministratorNotificationService(new ProviderNotificationRepository());
        $provider = new AdministratorNotificationServiceProvider(
            static function () use (&$calls, $service): AdministratorNotificationService {
                $calls++;
                return $service;
            },
        );

        self::assertSame($service, $provider->service());
        self::assertSame($service, $provider->service());
        self::assertSame(1, $calls);
    }

    public function testInvalidFactoryResultsAndFailuresAreSanitized(): void
    {
        foreach ([
            static fn (): object => new \stdClass(),
            static function (): never { throw new RuntimeException('sensitive notification provider failure'); },
        ] as $factory) {
            try {
                (new AdministratorNotificationServiceProvider($factory))->service();
                self::fail('Invalid notification provider factory was accepted.');
            } catch (NotificationException $exception) {
                self::assertTrue($exception->isSystemFailure());
                self::assertSame('Notification workflow operation failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('sensitive', $exception->getMessage());
            }
        }
    }
}

final class ProviderNotificationRepository implements NotificationRepository
{
    public function create(int $userId, string $type, string $title, string $message, ?string $relatedType, ?int $relatedId, \DateTimeImmutable $createdAt): \App\Models\Notification { throw new RuntimeException('Not used.'); }
    public function findPageForUser(int $userId, int $limit, int $offset): array { return []; }
    public function countForUser(int $userId): int { return 0; }
    public function countUnreadForUser(int $userId): int { return 0; }
    public function findByIdForUser(int $id, int $userId): ?\App\Models\Notification { return null; }
    public function markReadForUser(int $id, int $userId, \DateTimeImmutable $readAt): ?\App\Models\Notification { return null; }
    public function markAllReadForUser(int $userId, \DateTimeImmutable $readAt): int { return 0; }
}
