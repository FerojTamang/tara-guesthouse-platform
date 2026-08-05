<?php

declare(strict_types=1);

namespace App\Services;

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
use App\Requests\CmsPageContentRequest;
use App\Requests\CmsPagePublishingRequest;
use App\Requests\CmsSettingsUpdateRequest;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/** Owns authenticated Administrator settings/page mutations and audit evidence. */
final readonly class AdministratorCmsService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private CmsSettingsService|CmsSettingsServiceProvider $settings,
        private CmsPageService $pages,
        private CmsPageContentSanitizer $contentSanitizer,
        private AuditLogWriteRepository $auditLogs,
        private CmsTransaction $transaction,
        ?callable $clock = null,
        private ?PageContentProfileRepository $profiles = null,
        private ?ContentMediaRepository $contentMedia = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    /** @return list<SettingDefinition> */
    public function settingDefinitions(): array
    {
        return $this->executeRead(fn (): array => $this->settingsService()->definitions());
    }

    /** @return list<SiteSetting> */
    public function listSettings(): array
    {
        return $this->executeRead(fn (): array => $this->settingsService()->listAll());
    }

    /** @return list<CmsPage> */
    public function listPages(): array
    {
        return $this->executeRead(fn (): array => $this->pages->listAll());
    }

    public function getPage(int $id): CmsPage
    {
        return $this->executeRead(fn (): CmsPage => $this->pages->get($id));
    }

    public function pageProfile(int $pageId): ?PageContentProfile
    {
        if ($pageId <= 0 || !$this->profiles instanceof PageContentProfileRepository) {
            return null;
        }

        return $this->executeRead(fn (): ?PageContentProfile => $this->profiles?->findByPageId($pageId));
    }

    /** @return list<SiteSetting> */
    public function updateSettings(
        CmsSettingsUpdateRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use ($request, $administratorId, $ipAddress, $userAgent): array {
            $updated = [];

            foreach ($request->values() as $key => $value) {
                $setting = $this->settingsService()->upsert($key, $value, $administratorId);
                $this->appendAudit(
                    $administratorId,
                    'cms_setting_updated',
                    'setting',
                    $setting->id(),
                    'Administrator updated an approved site setting.',
                    [
                        'setting_key' => $setting->key(),
                        'value_type' => $setting->valueType(),
                        'is_public' => $setting->isPublic(),
                    ],
                    $ipAddress,
                    $userAgent,
                );
                $updated[] = $setting;
            }

            return $updated;
        });
    }

    public function createPage(
        CmsPageContentRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        ?CmsPagePublishingRequest $publishing = null,
    ): CmsPage {
        $this->assertAdministrator($administratorId);
        $this->assertPublicSlugAvailable($request->slug());
        $content = $this->contentSanitizer->sanitize($request->content());

        return $this->transact(function () use ($request, $content, $administratorId, $ipAddress, $userAgent, $publishing): CmsPage {
            $page = $this->pages->createDraft(
                $request->title(),
                $request->slug(),
                $content,
                $request->metaTitle(),
                $request->metaDescription(),
                $administratorId,
            );
            $this->syncStructuredContent($page->id(), $content, $administratorId);
            $this->syncPublishingProfile($page->id(), $publishing, $administratorId);

            $this->appendAudit(
                $administratorId,
                'cms_page_created',
                'page',
                $page->id(),
                'Administrator created a CMS page draft.',
                [
                    'slug' => $page->slug(),
                    'status' => $page->status(),
                    'content_format' => $this->contentSanitizer->format(),
                ],
                $ipAddress,
                $userAgent,
            );

            return $page;
        });
    }

    public function updatePage(
        int $id,
        CmsPageContentRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        ?CmsPagePublishingRequest $publishing = null,
    ): CmsPage {
        $this->assertAdministrator($administratorId);
        $this->assertPublicSlugAvailable($request->slug());
        $content = $this->contentSanitizer->sanitize($request->content());

        return $this->transact(function () use ($id, $request, $content, $administratorId, $ipAddress, $userAgent, $publishing): CmsPage {
            $previous = $this->pages->get($id);
            $page = $this->pages->updateContent(
                $id,
                $request->title(),
                $request->slug(),
                $content,
                $request->metaTitle(),
                $request->metaDescription(),
                $administratorId,
            );
            $this->syncStructuredContent($page->id(), $content, $administratorId);
            $this->syncPublishingProfile($page->id(), $publishing, $administratorId);

            $this->appendAudit(
                $administratorId,
                'cms_page_updated',
                'page',
                $page->id(),
                'Administrator updated CMS page content.',
                [
                    'previous_slug' => $previous->slug(),
                    'slug' => $page->slug(),
                    'status' => $page->status(),
                    'content_format' => $this->contentSanitizer->format(),
                ],
                $ipAddress,
                $userAgent,
            );

            return $page;
        });
    }

    public function transitionPageStatus(
        int $id,
        string $status,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        ?DateTimeImmutable $publishAt = null,
    ): CmsPage {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use ($id, $status, $administratorId, $ipAddress, $userAgent, $publishAt): CmsPage {
            $previous = $this->pages->get($id);
            $transitionedAt = $this->now();
            $profile = $this->profiles?->findByPageId($id);
            if ($status === CmsPage::SCHEDULED && !$publishAt instanceof DateTimeImmutable) {
                $publishAt = $profile?->publishAt();
            }
            $targetPublication = $status === CmsPage::PUBLISHED ? $transitionedAt : $publishAt;
            if (
                $profile instanceof PageContentProfile
                && $profile->expiresAt() instanceof DateTimeImmutable
                && $targetPublication instanceof DateTimeImmutable
                && $profile->expiresAt() <= $targetPublication
            ) {
                throw CmsException::invalidInput('The publication date must be earlier than the notice expiry date.');
            }
            $page = $this->pages->transitionStatus($id, $status, $administratorId, $publishAt);
            if (
                $this->profiles instanceof PageContentProfileRepository
                && $page->publishedAt() instanceof DateTimeImmutable
            ) {
                $this->profiles->updatePublication($page->id(), $page->publishedAt(), $administratorId);
            }

            $this->appendAudit(
                $administratorId,
                'cms_page_status_changed',
                'page',
                $page->id(),
                'Administrator changed CMS page publication status.',
                [
                    'slug' => $page->slug(),
                    'from' => $previous->publicationState($transitionedAt),
                    'to' => $page->publicationState($transitionedAt),
                ],
                $ipAddress,
                $userAgent,
            );

            return $page;
        });
    }

    private function assertPublicSlugAvailable(string $slug): void
    {
        if ((new NavigationRouteCatalog())->isReservedPageSlug($slug)) {
            throw CmsException::invalidInput('The page address is reserved by a public website route.');
        }
    }

    public function contentFormat(): string
    {
        return $this->contentSanitizer->format();
    }

    public function contentMaximumLength(): int
    {
        return $this->contentSanitizer->maximumLength();
    }

    private function syncStructuredContent(int $pageId, string $content, int $administratorId): void
    {
        if (!$this->profiles instanceof PageContentProfileRepository) {
            return;
        }

        $contentType = 'page';
        $summary = null;
        $usages = [];

        try {
            $document = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $document = null;
        }

        if (is_array($document) && ($document['format'] ?? null) === 'tara-blocks-v1') {
            $type = $document['type'] ?? null;
            $rawSummary = $document['summary'] ?? null;
            $blocks = $document['blocks'] ?? null;

            if (
                ($document['version'] ?? null) !== 1
                || !is_string($type)
                || !in_array($type, PageContentProfile::TYPES, true)
                || !is_string($rawSummary)
                || !is_array($blocks)
                || !array_is_list($blocks)
                || count($blocks) > 100
            ) {
                throw CmsException::invalidInput('The structured page content is invalid.');
            }

            $contentType = $type;
            $summary = trim($rawSummary);
            if ($summary === '') {
                $summary = null;
            } elseif (!mb_check_encoding($summary, 'UTF-8') || str_contains($summary, "\0") || mb_strlen($summary, 'UTF-8') > 500) {
                throw CmsException::invalidInput('The page summary is invalid.');
            }

            foreach ($blocks as $index => $block) {
                if (!is_array($block) || !is_string($block['type'] ?? null)) {
                    throw CmsException::invalidInput('The structured page blocks are invalid.');
                }

                if (!in_array($block['type'], ['image', 'image_text'], true) || !array_key_exists('media_id', $block)) {
                    continue;
                }

                $mediaId = $block['media_id'];
                $url = $block['url'] ?? null;
                $width = $block['width'] ?? null;
                $height = $block['height'] ?? null;

                if (
                    !is_int($mediaId)
                    || $mediaId <= 0
                    || !is_string($url)
                    || !is_int($width)
                    || !is_int($height)
                ) {
                    throw CmsException::invalidInput('A selected content image is invalid.');
                }

                if (!$this->contentMedia instanceof ContentMediaRepository) {
                    throw CmsException::failure();
                }

                $media = $this->contentMedia->findActiveByIdForShare($mediaId);
                if (
                    !$media instanceof ContentMedia
                    || $url !== $media->publicUrl()
                    || $width !== $media->width()
                    || $height !== $media->height()
                ) {
                    throw CmsException::invalidInput('A selected content image is unavailable.');
                }

                $altText = $block['alt'] ?? null;
                if (
                    !is_string($altText)
                    || !mb_check_encoding($altText, 'UTF-8')
                    || str_contains($altText, "\0")
                    || trim($altText) === ''
                    || mb_strlen(trim($altText), 'UTF-8') > 255
                ) {
                    throw CmsException::invalidInput('Selected content images require valid alternative text.');
                }

                if (array_key_exists('caption', $block)) {
                    $caption = $block['caption'];
                    if (
                        !is_string($caption)
                        || !mb_check_encoding($caption, 'UTF-8')
                        || str_contains($caption, "\0")
                        || mb_strlen(trim($caption), 'UTF-8') > 500
                    ) {
                        throw CmsException::invalidInput('The selected content image caption is invalid.');
                    }
                }

                $usages[] = [
                    'media_id' => $media->id(),
                    'block_key' => 'block-' . ($index + 1),
                    'sort_order' => $index,
                ];
            }
        }

        $this->profiles->upsertCore($pageId, $contentType, $summary, $administratorId);
        $this->profiles->replaceBlockMedia($pageId, $usages);
    }

    private function syncPublishingProfile(
        int $pageId,
        ?CmsPagePublishingRequest $publishing,
        int $administratorId,
    ): void {
        if (!$publishing instanceof CmsPagePublishingRequest || !$this->profiles instanceof PageContentProfileRepository) {
            return;
        }

        $profile = $this->profiles->findByPageId($pageId);
        if (!$profile instanceof PageContentProfile) {
            throw CmsException::failure();
        }

        if (
            $profile->contentType() !== 'notice'
            && ($publishing->noticeStyle() !== null || $publishing->expiresAt() !== null)
        ) {
            throw CmsException::invalidInput('Notice style and expiry are available only for notices.');
        }

        foreach ([$publishing->featuredMediaId(), $publishing->socialMediaId()] as $mediaId) {
            if ($mediaId === null) {
                continue;
            }
            if (!$this->contentMedia instanceof ContentMediaRepository) {
                throw CmsException::failure();
            }
            if (!$this->contentMedia->findActiveByIdForShare($mediaId) instanceof ContentMedia) {
                throw CmsException::invalidInput('A selected publishing image is unavailable.');
            }
        }

        $this->profiles->updatePublishing(
            $pageId,
            $publishing->noticeStyle(),
            $publishing->publishAt(),
            $publishing->expiresAt(),
            $publishing->isFeatured(),
            $publishing->isPinned(),
            $publishing->featuredMediaId(),
            $publishing->socialTitle(),
            $publishing->socialDescription(),
            $publishing->socialMediaId(),
            $publishing->seoNoindex(),
            $administratorId,
        );
    }

    /** @param array<string, bool|int|string|null> $metadata */
    private function appendAudit(
        int $administratorId,
        string $eventType,
        string $entityType,
        ?int $entityId,
        string $description,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        try {
            $metadataJson = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $failure) {
            throw new RuntimeException('CMS audit metadata could not be encoded.', previous: $failure);
        }

        $this->auditLogs->append(new AuditLogEntry(
            $administratorId,
            $eventType,
            $entityType,
            $entityId,
            $description,
            $metadataJson,
            $ipAddress,
            $userAgent,
            $this->now(),
        ));
    }

    private function settingsService(): CmsSettingsService
    {
        return $this->settings instanceof CmsSettingsService
            ? $this->settings
            : $this->settings->service();
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();

        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw CmsException::failure();
        }

        return $value;
    }

    private function assertAdministrator(int $administratorId): void
    {
        if ($administratorId <= 0) {
            throw CmsException::invalidInput('Administrator CMS request is invalid.');
        }
    }

    /** @template T @param callable(): T $operation @return T */
    private function executeRead(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }

    /** @template T @param callable(): T $operation @return T */
    private function transact(callable $operation): mixed
    {
        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $result = $operation();
            $commitAttempted = true;
            $this->transaction->commit();

            return $result;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof CmsException ? $failure : CmsException::failure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw CmsException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw CmsException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw CmsException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw CmsException::failure();
            }
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
