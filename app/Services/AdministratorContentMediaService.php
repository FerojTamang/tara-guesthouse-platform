<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ContentMediaException;
use App\Models\AuditLogEntry;
use App\Models\ContentMedia;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContentMediaRepository;
use App\Requests\ContentMediaMetadataRequest;
use App\Requests\ContentMediaUploadRequest;
use App\Storage\ContentMediaStorage;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/** Owns authenticated media-library writes, usage safeguards, and audit evidence. */
final readonly class AdministratorContentMediaService
{
    private Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private ContentMediaRepository $media,
        private ContentMediaStorage $storage,
        private AuditLogWriteRepository $auditLogs,
        private CmsTransaction $transaction,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    /** @return list<ContentMedia> */
    public function listMedia(): array
    {
        try {
            return $this->media->findAllActive();
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function getMedia(int $id): ContentMedia
    {
        try {
            $media = $this->media->findActiveById($id);
            if (!$media instanceof ContentMedia) {
                throw ContentMediaException::notFound();
            }
            return $media;
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function upload(
        ContentMediaUploadRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ContentMedia {
        $this->assertAdministrator($administratorId);

        if ($this->media->findActiveByChecksum($request->sha256()) instanceof ContentMedia) {
            throw ContentMediaException::conflict('This image already exists in the media library.');
        }

        $stored = $this->storage->store($request);

        try {
            return $this->transact(function () use ($request, $stored, $administratorId, $ipAddress, $userAgent): ContentMedia {
                if ($this->media->findActiveByChecksum($request->sha256()) instanceof ContentMedia) {
                    throw ContentMediaException::conflict('This image already exists in the media library.');
                }

                $metadata = $request->metadata();
                $media = $this->media->create(
                    $stored->relativePath(),
                    $request->originalName(),
                    $stored->mediaType(),
                    $stored->extension(),
                    $stored->byteSize(),
                    $stored->width(),
                    $stored->height(),
                    $stored->sha256(),
                    $metadata->title(),
                    $metadata->altText(),
                    $metadata->caption(),
                    $administratorId,
                );

                $this->appendAudit(
                    $administratorId,
                    'content_media_uploaded',
                    $media->id(),
                    'Administrator uploaded a reusable content image.',
                    [
                        'mime_type' => $media->mimeType(),
                        'width' => $media->width(),
                        'height' => $media->height(),
                        'file_size_bytes' => $media->byteSize(),
                    ],
                    $ipAddress,
                    $userAgent,
                );

                return $media;
            });
        } catch (Throwable $failure) {
            try {
                $this->storage->remove($stored->relativePath());
            } catch (Throwable) {
                // Cleanup must not replace the safe persistence failure.
            }

            throw $failure instanceof ContentMediaException
                ? $failure
                : ContentMediaException::failure($failure);
        }
    }

    public function updateMetadata(
        int $id,
        ContentMediaMetadataRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): ContentMedia {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use ($id, $request, $administratorId, $ipAddress, $userAgent): ContentMedia {
            $existing = $this->media->findActiveByIdForUpdate($id);
            if (!$existing instanceof ContentMedia) {
                throw ContentMediaException::notFound();
            }

            $media = $this->media->updateMetadata(
                $id,
                $request->title(),
                $request->altText(),
                $request->caption(),
            );

            if (!$media instanceof ContentMedia) {
                throw ContentMediaException::notFound();
            }

            $this->appendAudit(
                $administratorId,
                'content_media_metadata_updated',
                $media->id(),
                'Administrator updated content image metadata.',
                ['usage_count' => $media->usageCount()],
                $ipAddress,
                $userAgent,
            );

            return $media;
        });
    }

    public function delete(
        int $id,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->assertAdministrator($administratorId);

        $media = $this->transact(function () use ($id, $administratorId, $ipAddress, $userAgent): ContentMedia {
            $media = $this->media->findActiveByIdForUpdate($id);
            if (!$media instanceof ContentMedia) {
                throw ContentMediaException::notFound();
            }

            if ($media->isInUse()) {
                throw ContentMediaException::conflict(
                    'This image is used by published or draft content and cannot be deleted.',
                );
            }

            if (!$this->media->softDelete($media->id())) {
                throw ContentMediaException::notFound();
            }

            $this->appendAudit(
                $administratorId,
                'content_media_deleted',
                $media->id(),
                'Administrator deleted an unused content image.',
                ['storage_path' => $media->storagePath()],
                $ipAddress,
                $userAgent,
            );

            return $media;
        });

        try {
            $this->storage->remove($media->storagePath());
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    /** @param array<string, bool|int|string|null> $metadata */
    private function appendAudit(
        int $administratorId,
        string $eventType,
        int $mediaId,
        string $description,
        array $metadata,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        try {
            $json = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $failure) {
            throw new RuntimeException('Content media audit metadata could not be encoded.', previous: $failure);
        }

        $this->auditLogs->append(new AuditLogEntry(
            $administratorId,
            $eventType,
            'content_media',
            $mediaId,
            $description,
            $json,
            $ipAddress,
            $userAgent,
            $this->now(),
        ));
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();
        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw ContentMediaException::failure();
        }
        return $value;
    }

    private function assertAdministrator(int $administratorId): void
    {
        if ($administratorId <= 0) {
            throw ContentMediaException::invalidInput('Administrator media request is invalid.');
        }
    }

    /** @template T @param callable(): T $operation @return T */
    private function transact(callable $operation): mixed
    {
        try {
            if ($this->transaction->inProgress()) {
                throw ContentMediaException::failure();
            }
            $this->transaction->begin();
            $result = $operation();
            $this->transaction->commit();
            return $result;
        } catch (Throwable $failure) {
            try {
                if ($this->transaction->inProgress()) {
                    $this->transaction->rollBack();
                }
            } catch (Throwable $rollbackFailure) {
                throw ContentMediaException::failure($rollbackFailure);
            }

            throw $failure instanceof ContentMediaException
                ? $failure
                : ContentMediaException::failure($failure);
        }
    }
}
