<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use App\Models\AuditLogEntry;
use App\Models\CmsPage;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Repositories\AuditLogWriteRepository;
use App\Requests\CmsPageContentRequest;
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
    ): CmsPage {
        $this->assertAdministrator($administratorId);
        $content = $this->contentSanitizer->sanitize($request->content());

        return $this->transact(function () use ($request, $content, $administratorId, $ipAddress, $userAgent): CmsPage {
            $page = $this->pages->createDraft(
                $request->title(),
                $request->slug(),
                $content,
                $request->metaTitle(),
                $request->metaDescription(),
                $administratorId,
            );

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
    ): CmsPage {
        $this->assertAdministrator($administratorId);
        $content = $this->contentSanitizer->sanitize($request->content());

        return $this->transact(function () use ($id, $request, $content, $administratorId, $ipAddress, $userAgent): CmsPage {
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
    ): CmsPage {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use ($id, $status, $administratorId, $ipAddress, $userAgent): CmsPage {
            $previous = $this->pages->get($id);
            $page = $this->pages->transitionStatus($id, $status, $administratorId);

            $this->appendAudit(
                $administratorId,
                'cms_page_status_changed',
                'page',
                $page->id(),
                'Administrator changed CMS page publication status.',
                [
                    'slug' => $page->slug(),
                    'from' => $previous->status(),
                    'to' => $page->status(),
                ],
                $ipAddress,
                $userAgent,
            );

            return $page;
        });
    }

    public function contentFormat(): string
    {
        return $this->contentSanitizer->format();
    }

    public function contentMaximumLength(): int
    {
        return $this->contentSanitizer->maximumLength();
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
