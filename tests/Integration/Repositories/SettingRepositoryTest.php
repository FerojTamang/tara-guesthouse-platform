<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Repositories\PdoSettingRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CmsFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class SettingRepositoryTest extends DatabaseTestCase
{
    private PdoSettingRepository $repository;
    private int $actorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->actorId = CmsFixture::insertAdministrator($this->connection, 'settings-repo');
        $this->repository = new PdoSettingRepository($this->connection);
    }

    public function testItUpsertsListsAndFiltersPublicSettingsInKeyOrder(): void
    {
        $this->repository->upsert('seo.default.title', 'Tara Guest House', 'string', true, $this->actorId);
        $this->repository->upsert('internal.preview.limit', '6', 'integer', false, $this->actorId);
        $updated = $this->repository->upsert('seo.default.title', 'Tara Guest House Nepal', 'string', true, $this->actorId);

        self::assertSame('Tara Guest House Nepal', $updated->value());
        self::assertSame($this->actorId, $updated->updatedBy());
        self::assertSame(
            ['internal.preview.limit', 'seo.default.title'],
            array_map(static fn ($setting): string => $setting->key(), $this->repository->findAll()),
        );
        self::assertSame(
            ['seo.default.title'],
            array_map(static fn ($setting): string => $setting->key(), $this->repository->findAllPublic()),
        );
        self::assertSame(2, (int) $this->connection->query('SELECT COUNT(*) FROM settings')->fetchColumn());
    }

    public function testMissingAndInvalidKeysReturnNullWithoutQueryLeakage(): void
    {
        self::assertNull($this->repository->findByKey(''));
        self::assertNull($this->repository->findByKey('missing.key'));
    }

    public function testRepositoryFailuresAreSanitized(): void
    {
        $this->connection->exec('DROP TABLE settings');

        try {
            $this->repository->findAll();
            self::fail('Expected a setting repository failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The setting listing failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }
}
