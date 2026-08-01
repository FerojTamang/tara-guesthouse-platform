<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\AuditLogEntry;
use App\Models\CmsPage;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PageRepository;
use App\Repositories\SettingRepository;
use App\Requests\CmsPageContentRequest;
use App\Requests\CmsSettingsUpdateRequest;
use App\Services\AdministratorCmsService;
use App\Services\CmsPageService;
use App\Services\CmsSettingsService;
use App\Services\CmsTransaction;
use App\Services\PlainTextCmsPageContentSanitizer;
use App\Services\SettingDefinitionRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorCmsServiceTest extends TestCase
{
    public function testSettingsAndPageMutationsAreTransactionalSanitizedAndAudited(): void
    {
        $settings = new CmsSettingRepositoryFake();
        $pages = new CmsPageRepositoryFake();
        $audit = new CmsAuditRepositoryFake();
        $transaction = new CmsTransactionFake();
        $service = $this->service($settings, $pages, $audit, $transaction);

        $updated = $service->updateSettings(
            CmsSettingsUpdateRequest::fromInput([
                'contact.email' => 'Stay@Example.Test',
                'homepage.rooms.limit' => '4',
            ]),
            9,
            '127.0.0.1',
            'CMS Test',
        );
        self::assertSame(['stay@example.test', '4'], array_map(static fn (SiteSetting $setting): string => $setting->value(), $updated));

        $page = $service->createPage(
            CmsPageContentRequest::fromInput('About Tara', 'about-tara', " Welcome  \r\nHome \r\n", '', null),
            9,
            null,
            null,
        );
        self::assertSame("Welcome\nHome", $page->content());
        self::assertSame(CmsPage::DRAFT, $page->status());

        $page = $service->updatePage(
            $page->id(),
            CmsPageContentRequest::fromInput('About Tara Guest House', 'about', 'Updated content', 'About', 'About Tara'),
            9,
            null,
            null,
        );
        self::assertSame('about', $page->slug());

        $page = $service->transitionPageStatus($page->id(), CmsPage::PUBLISHED, 9, null, null);
        self::assertSame(CmsPage::PUBLISHED, $page->status());
        self::assertNotNull($page->publishedAt());

        self::assertSame(4, $transaction->begins);
        self::assertSame(4, $transaction->commits);
        self::assertSame(0, $transaction->rollbacks);
        self::assertSame([
            'cms_setting_updated',
            'cms_setting_updated',
            'cms_page_created',
            'cms_page_updated',
            'cms_page_status_changed',
        ], array_map(static fn (AuditLogEntry $entry): string => $entry->eventType(), $audit->entries));
        self::assertStringNotContainsString('stay@example.test', implode('', array_map(
            static fn (AuditLogEntry $entry): string => $entry->metadataJson() ?? '',
            $audit->entries,
        )));
    }

    public function testAuditFailureRollsBackAndIsSanitized(): void
    {
        $transaction = new CmsTransactionFake();
        $service = $this->service(
            new CmsSettingRepositoryFake(),
            new CmsPageRepositoryFake(),
            new CmsFailingAuditRepositoryFake(),
            $transaction,
        );

        try {
            $service->createPage(
                CmsPageContentRequest::fromInput('About', 'about', 'Safe content', null, null),
                9,
                null,
                null,
            );
            self::fail('Expected a safe audit failure.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('CMS operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive', $exception->getMessage());
        }

        self::assertSame(1, $transaction->rollbacks);
    }

    public function testHtmlAndExistingTransactionsFailClosed(): void
    {
        $transaction = new CmsTransactionFake();
        $service = $this->service(new CmsSettingRepositoryFake(), new CmsPageRepositoryFake(), new CmsAuditRepositoryFake(), $transaction);

        try {
            $service->createPage(
                CmsPageContentRequest::fromInput('About', 'about', '<p>Unsafe</p>', null, null),
                9,
                null,
                null,
            );
            self::fail('Expected HTML to be rejected.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isValidationFailure());
        }

        $transaction->active = true;
        $this->expectException(CmsException::class);
        $service->updateSettings(CmsSettingsUpdateRequest::fromInput(['contact.email' => 'stay@example.test']), 9, null, null);
    }

    private function service(
        SettingRepository $settings,
        PageRepository $pages,
        AuditLogWriteRepository $audit,
        CmsTransaction $transaction,
    ): AdministratorCmsService {
        $registry = new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
            new SettingDefinition('homepage.rooms.limit', 'integer', false, 3),
        ]);

        return new AdministratorCmsService(
            new CmsSettingsService($settings, $registry),
            new CmsPageService($pages),
            new PlainTextCmsPageContentSanitizer(5000),
            $audit,
            $transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
        );
    }
}

final class CmsSettingRepositoryFake implements SettingRepository
{
    /** @var array<string, SiteSetting> */
    public array $settings = [];
    private int $nextId = 1;

    public function findByKey(string $key): ?SiteSetting { return $this->settings[$key] ?? null; }
    public function findAll(): array { $values = array_values($this->settings); usort($values, static fn ($a, $b) => $a->key() <=> $b->key()); return $values; }
    public function findAllPublic(): array { return array_values(array_filter($this->findAll(), static fn (SiteSetting $setting): bool => $setting->isPublic())); }

    public function upsert(string $key, string $value, string $valueType, bool $public, int $actorId): SiteSetting
    {
        $existing = $this->settings[$key] ?? null;
        $now = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        return $this->settings[$key] = new SiteSetting(
            $existing?->id() ?? $this->nextId++,
            $key,
            $value,
            $valueType,
            $public,
            $actorId,
            $existing?->createdAt() ?? $now,
            $now,
        );
    }
}

final class CmsPageRepositoryFake implements PageRepository
{
    /** @var array<int, CmsPage> */
    public array $pages = [];
    private int $nextId = 1;

    public function findById(int $id): ?CmsPage { return $this->pages[$id] ?? null; }
    public function findBySlug(string $slug): ?CmsPage { foreach ($this->pages as $page) if ($page->slug() === $slug) return $page; return null; }
    public function findPublishedBySlug(string $slug): ?CmsPage { $page = $this->findBySlug($slug); return $page?->isPublished() ? $page : null; }
    public function findAll(): array { return array_values($this->pages); }
    public function findAllPublished(): array { return array_values(array_filter($this->pages, static fn (CmsPage $page): bool => $page->isPublished())); }

    public function createDraft(string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): CmsPage
    {
        $id = $this->nextId++;
        $now = new DateTimeImmutable('2026-07-31 04:00:00 UTC');
        return $this->pages[$id] = new CmsPage($id, $title, $slug, $content, $metaTitle, $metaDescription, CmsPage::DRAFT, null, $actorId, $actorId, $now, $now);
    }

    public function updateContent(int $id, string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): ?CmsPage
    {
        $current = $this->pages[$id] ?? null;
        if (!$current instanceof CmsPage) return null;
        return $this->pages[$id] = new CmsPage($id, $title, $slug, $content, $metaTitle, $metaDescription, $current->status(), $current->publishedAt(), $current->createdBy(), $actorId, $current->createdAt(), new DateTimeImmutable('2026-07-31 04:00:00 UTC'));
    }

    public function transitionStatus(int $id, string $expectedStatus, string $newStatus, ?DateTimeImmutable $publishedAt, int $actorId): ?CmsPage
    {
        $current = $this->pages[$id] ?? null;
        if (!$current instanceof CmsPage || $current->status() !== $expectedStatus) return null;
        return $this->pages[$id] = new CmsPage($id, $current->title(), $current->slug(), $current->content(), $current->metaTitle(), $current->metaDescription(), $newStatus, $publishedAt, $current->createdBy(), $actorId, $current->createdAt(), new DateTimeImmutable('2026-07-31 04:00:00 UTC'));
    }
}

final class CmsAuditRepositoryFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public function append(AuditLogEntry $entry): void { $this->entries[] = $entry; }
}

final class CmsFailingAuditRepositoryFake implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void { throw new RuntimeException('sensitive audit failure'); }
}

final class CmsTransactionFake implements CmsTransaction
{
    public bool $active = false;
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public function begin(): void { if ($this->active) throw new RuntimeException('active'); $this->active = true; $this->begins++; }
    public function commit(): void { if (!$this->active) throw new RuntimeException('inactive'); $this->active = false; $this->commits++; }
    public function rollBack(): void { if (!$this->active) throw new RuntimeException('inactive'); $this->active = false; $this->rollbacks++; }
    public function inProgress(): bool { return $this->active; }
}
