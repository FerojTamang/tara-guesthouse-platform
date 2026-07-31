<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Repositories\SettingRepository;
use App\Services\CmsSettingsService;
use App\Services\SettingDefinitionRegistry;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CmsSettingsServiceTest extends TestCase
{
    public function testItAllowsOnlyConfiguredKeysAndDerivesTypeAndVisibilityServerSide(): void
    {
        $repository = new InMemorySettingRepository();
        $service = new CmsSettingsService($repository, new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
            new SettingDefinition('internal.preview.limit', 'integer', false, 3),
        ]));

        $public = $service->upsert('contact.email', 'HELLO@example.test', 7);
        $private = $service->upsert('internal.preview.limit', '12', 7);

        self::assertSame('hello@example.test', $public->value());
        self::assertSame('email', $public->valueType());
        self::assertTrue($public->isPublic());
        self::assertFalse($private->isPublic());
        self::assertSame(['contact.email'], array_map(
            static fn (SiteSetting $setting): string => $setting->key(),
            $service->listPublic(),
        ));
        self::assertSame(7, $service->get('contact.email')->updatedBy());
    }

    public function testUnknownKeysInvalidValuesAndActorsAreRejectedBeforePersistence(): void
    {
        $repository = new InMemorySettingRepository();
        $service = new CmsSettingsService($repository, new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
        ]));

        foreach ([
            fn () => $service->upsert('unknown.key', 'value', 1),
            fn () => $service->upsert('contact.email', 'invalid', 1),
            fn () => $service->upsert('contact.email', 'hello@example.test', 0),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected CMS setting validation to fail.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }

        self::assertSame([], $repository->findAll());
    }
}

final class InMemorySettingRepository implements SettingRepository
{
    /** @var array<string, SiteSetting> */
    private array $settings = [];
    private int $nextId = 1;

    public function findByKey(string $key): ?SiteSetting { return $this->settings[$key] ?? null; }
    public function findAll(): array { $values = array_values($this->settings); usort($values, static fn ($a, $b) => $a->key() <=> $b->key()); return $values; }
    public function findAllPublic(): array { return array_values(array_filter($this->findAll(), static fn (SiteSetting $setting): bool => $setting->isPublic())); }

    public function upsert(string $key, string $value, string $valueType, bool $public, int $actorId): SiteSetting
    {
        $time = new DateTimeImmutable('2026-07-31 00:00:00', new DateTimeZone('UTC'));
        $existing = $this->settings[$key] ?? null;
        $setting = new SiteSetting(
            $existing?->id() ?? $this->nextId++,
            $key,
            $value,
            $valueType,
            $public,
            $actorId,
            $existing?->createdAt() ?? $time,
            $time,
        );
        return $this->settings[$key] = $setting;
    }
}
