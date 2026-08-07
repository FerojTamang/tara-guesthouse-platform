<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\AuditLogEntry;
use App\Models\CmsPage;
use App\Models\ContentMedia;
use App\Models\PageContentProfile;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContentMediaRepository;
use App\Repositories\PageContentProfileRepository;
use App\Repositories\PageRepository;
use App\Repositories\SettingRepository;
use App\Requests\CmsPageContentRequest;
use App\Services\AdministratorCmsService;
use App\Services\CmsPageService;
use App\Services\CmsSettingsService;
use App\Services\CmsTransaction;
use App\Services\PlainTextCmsPageContentSanitizer;
use App\Services\SettingDefinitionRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AdministratorCmsStructuredContentTest extends TestCase
{
    public function testManagedImageBlocksCreateProfileAndUsageReferences(): void
    {
        $media = StructuredContentMediaRepositoryFake::media();
        $mediaRepository = new StructuredContentMediaRepositoryFake($media);
        $profiles = new StructuredPageProfileRepositoryFake();
        $pages = new StructuredPageRepositoryFake();
        $service = $this->service($pages, $profiles, $mediaRepository);
        $content = json_encode([
            'format' => 'tara-blocks-v1',
            'version' => 1,
            'type' => 'news',
            'summary' => 'A professional update from Tara Guest House.',
            'blocks' => [[
                'type' => 'image',
                'media_id' => $media->id(),
                'media_title' => $media->title(),
                'url' => $media->publicUrl(),
                'width' => $media->width(),
                'height' => $media->height(),
                'alt' => $media->altText(),
                'caption' => $media->caption(),
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $page = $service->createPage(
            CmsPageContentRequest::fromInput('Garden update', 'garden-update', $content, null, null),
            9,
            null,
            null,
        );

        self::assertSame('news', $profiles->profiles[$page->id()]['content_type']);
        self::assertSame('A professional update from Tara Guest House.', $profiles->profiles[$page->id()]['summary']);
        self::assertSame([[
            'media_id' => $media->id(),
            'block_key' => 'block-1',
            'sort_order' => 0,
        ]], $profiles->usages[$page->id()]);
    }

    public function testManagedImageIdentityCannotBeForgedByTheBrowser(): void
    {
        $media = StructuredContentMediaRepositoryFake::media();
        $service = $this->service(
            new StructuredPageRepositoryFake(),
            new StructuredPageProfileRepositoryFake(),
            new StructuredContentMediaRepositoryFake($media),
        );
        $content = json_encode([
            'format' => 'tara-blocks-v1',
            'version' => 1,
            'type' => 'page',
            'summary' => '',
            'blocks' => [[
                'type' => 'image',
                'media_id' => $media->id(),
                'url' => 'https://untrusted.example.test/forged.jpg',
                'width' => $media->width(),
                'height' => $media->height(),
                'alt' => 'Forged image',
                'caption' => '',
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        try {
            $service->createPage(
                CmsPageContentRequest::fromInput('Unsafe image', 'unsafe-image', $content, null, null),
                9,
                null,
                null,
            );
            self::fail('Expected managed media identity validation to fail.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('A selected content image is unavailable.', $exception->getMessage());
        }
    }

    public function testManagedImageRequiresAccessibleAlternativeText(): void
    {
        $media = StructuredContentMediaRepositoryFake::media();
        $service = $this->service(
            new StructuredPageRepositoryFake(),
            new StructuredPageProfileRepositoryFake(),
            new StructuredContentMediaRepositoryFake($media),
        );
        $content = json_encode([
            'format' => 'tara-blocks-v1',
            'version' => 1,
            'type' => 'page',
            'summary' => '',
            'blocks' => [[
                'type' => 'image',
                'media_id' => $media->id(),
                'url' => $media->publicUrl(),
                'width' => $media->width(),
                'height' => $media->height(),
                'alt' => '   ',
                'caption' => '',
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        try {
            $service->createPage(
                CmsPageContentRequest::fromInput('Inaccessible image', 'inaccessible-image', $content, null, null),
                9,
                null,
                null,
            );
            self::fail('Expected alternative-text validation to fail.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame(
                'Selected content images require valid alternative text.',
                $exception->getMessage(),
            );
        }
    }

    private function service(
        PageRepository $pages,
        PageContentProfileRepository $profiles,
        ContentMediaRepository $media,
    ): AdministratorCmsService {
        return new AdministratorCmsService(
            new CmsSettingsService(
                new StructuredSettingRepositoryFake(),
                new SettingDefinitionRegistry([
                    new SettingDefinition('contact.email', 'email', true, 254),
                ]),
            ),
            new CmsPageService($pages),
            new PlainTextCmsPageContentSanitizer(10000),
            new StructuredAuditRepositoryFake(),
            new StructuredTransactionFake(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-04 16:50:00 UTC'),
            $profiles,
            $media,
        );
    }
}

final class StructuredContentMediaRepositoryFake implements ContentMediaRepository
{
    public function __construct(private readonly ContentMedia $media) {}
    public function findAllActive(): array { return [$this->media]; }
    public function findActiveById(int $id): ?ContentMedia { return $id === $this->media->id() ? $this->media : null; }
    public function findActiveByIdForShare(int $id): ?ContentMedia { return $this->findActiveById($id); }
    public function findActiveByIdForUpdate(int $id): ?ContentMedia { return $this->findActiveById($id); }
    public function findActiveByChecksum(string $sha256): ?ContentMedia { return hash_equals($this->media->sha256(), $sha256) ? $this->media : null; }
    public function create(string $storagePath, string $originalName, string $mimeType, string $extension, int $byteSize, int $width, int $height, string $sha256, ?string $title, string $altText, ?string $caption, int $uploadedBy): ContentMedia { return $this->media; }
    public function updateMetadata(int $id, ?string $title, string $altText, ?string $caption): ?ContentMedia { return $this->findActiveById($id); }
    public function softDelete(int $id): bool { return false; }

    public static function media(): ContentMedia
    {
        $time = new DateTimeImmutable('2026-08-04 16:50:00 UTC');
        return new ContentMedia(
            6,
            'uploads/content/' . str_repeat('a', 64) . '.webp',
            'garden.webp',
            'image/webp',
            'webp',
            145000,
            1600,
            900,
            str_repeat('b', 64),
            'Garden view',
            'Garden terrace overlooking the hills',
            'Morning at Tara Guest House.',
            9,
            $time,
            $time,
            null,
            0,
        );
    }
}

final class StructuredPageProfileRepositoryFake implements PageContentProfileRepository
{
    /** @var array<int, array{content_type: string, summary: ?string, updated_by: int}> */
    public array $profiles = [];
    /** @var array<int, list<array{media_id: int, block_key: string, sort_order: int}>> */
    public array $usages = [];

    public function findByPageId(int $pageId): ?PageContentProfile { return null; }
    public function findByPageIds(array $pageIds): array { return []; }
    public function upsertCore(int $pageId, string $contentType, ?string $summary, int $updatedBy): PageContentProfile
    {
        $this->profiles[$pageId] = ['content_type' => $contentType, 'summary' => $summary, 'updated_by' => $updatedBy];
        $time = new DateTimeImmutable('2026-08-04 16:50:00 UTC');
        return new PageContentProfile($pageId, $contentType, $summary, null, null, null, false, false, null, null, null, null, false, $updatedBy, $time, $time);
    }
    public function updatePublishing(int $pageId, ?string $noticeStyle, ?DateTimeImmutable $publishAt, ?DateTimeImmutable $expiresAt, bool $featured, bool $pinned, ?int $featuredMediaId, ?string $socialTitle, ?string $socialDescription, ?int $socialMediaId, bool $seoNoindex, int $updatedBy): PageContentProfile
    {
        $current = $this->profiles[$pageId] ?? ['content_type' => 'page', 'summary' => null, 'updated_by' => $updatedBy];
        $time = new DateTimeImmutable('2026-08-04 16:50:00 UTC');
        return new PageContentProfile($pageId, $current['content_type'], $current['summary'], $noticeStyle, $publishAt, $expiresAt, $featured, $pinned, $featuredMediaId, $socialTitle, $socialDescription, $socialMediaId, $seoNoindex, $updatedBy, $time, $time);
    }
    public function updatePublication(int $pageId, DateTimeImmutable $publishAt, int $updatedBy): void {}
    public function replaceBlockMedia(int $pageId, array $usages): void { $this->usages[$pageId] = $usages; }
}

final class StructuredPageRepositoryFake implements PageRepository
{
    /** @var array<int, CmsPage> */
    private array $pages = [];
    public function findById(int $id): ?CmsPage { return $this->pages[$id] ?? null; }
    public function findBySlug(string $slug): ?CmsPage { foreach ($this->pages as $page) if ($page->slug() === $slug) return $page; return null; }
    public function findPublishedBySlug(string $slug): ?CmsPage { $page = $this->findBySlug($slug); return $page?->isPublished() ? $page : null; }
    public function findAll(): array { return array_values($this->pages); }
    public function findAllPublished(): array { return array_values(array_filter($this->pages, static fn (CmsPage $page): bool => $page->isPublished())); }
    public function createDraft(string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): CmsPage
    {
        $time = new DateTimeImmutable('2026-08-04 16:50:00 UTC');
        return $this->pages[1] = new CmsPage(1, $title, $slug, $content, $metaTitle, $metaDescription, CmsPage::DRAFT, null, $actorId, $actorId, $time, $time);
    }
    public function updateContent(int $id, string $title, string $slug, string $content, ?string $metaTitle, ?string $metaDescription, int $actorId): ?CmsPage { return null; }
    public function transitionStatus(int $id, string $expectedStatus, string $newStatus, ?DateTimeImmutable $publishedAt, int $actorId): ?CmsPage { return null; }
}

final class StructuredSettingRepositoryFake implements SettingRepository
{
    public function findByKey(string $key): ?SiteSetting { return null; }
    public function findAll(): array { return []; }
    public function findAllPublic(): array { return []; }
    public function upsert(string $key, string $value, string $valueType, bool $public, int $actorId): SiteSetting { throw new \RuntimeException('not used'); }
}

final class StructuredAuditRepositoryFake implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void {}
}

final class StructuredTransactionFake implements CmsTransaction
{
    private bool $active = false;
    public function begin(): void { $this->active = true; }
    public function commit(): void { $this->active = false; }
    public function rollBack(): void { $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}
