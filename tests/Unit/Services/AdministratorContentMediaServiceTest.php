<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\UploadedFile;
use App\Exceptions\ContentMediaException;
use App\Models\AuditLogEntry;
use App\Models\ContentMedia;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\ContentMediaRepository;
use App\Requests\ContentMediaMetadataRequest;
use App\Requests\ContentMediaUploadRequest;
use App\Services\AdministratorContentMediaService;
use App\Services\CmsTransaction;
use App\Storage\ContentMediaStorage;
use App\Storage\StoredContentMedia;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorContentMediaServiceTest extends TestCase
{
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAUAAAAC0CAIAAABqhmJGAAABkUlEQVR42u3TsQkAAAgEMXX/Yb+xdw4hGeHgOpsCfhoJwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGDAwGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBAQODgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwICB4bcD4XcEPQdXsdAAAAAASUVORK5CYII=';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-content-media-service-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function testUploadPersistsAuditsAndCommits(): void
    {
        $repository = new ContentMediaRepositoryFake();
        $storage = new ContentMediaStorageFake();
        $audit = new ContentMediaAuditRepositoryFake();
        $transaction = new ContentMediaTransactionFake();
        $request = $this->uploadRequest();
        $storage->next = new StoredContentMedia(
            'uploads/content/' . str_repeat('a', 64) . '.png',
            str_repeat('a', 64) . '.png',
            'png',
            'image/png',
            $request->file()->size(),
            320,
            180,
            $request->sha256(),
        );

        $service = new AdministratorContentMediaService(
            $repository,
            $storage,
            $audit,
            $transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-04 16:40:00 UTC'),
        );
        $media = $service->upload($request, 9, '127.0.0.1', 'Media Test');

        self::assertSame(1, $media->id());
        self::assertSame('/uploads/content/' . str_repeat('a', 64) . '.png', $media->publicUrl());
        self::assertSame(1, $storage->stores);
        self::assertSame(1, $transaction->begins);
        self::assertSame(1, $transaction->commits);
        self::assertSame(0, $transaction->rollbacks);
        self::assertCount(1, $audit->entries);
        self::assertSame('content_media_uploaded', $audit->entries[0]->eventType());
        self::assertStringNotContainsString('Garden terrace with mountain view', $audit->entries[0]->metadataJson() ?? '');
    }

    public function testDuplicateChecksumIsRejectedBeforeStorage(): void
    {
        $request = $this->uploadRequest();
        $repository = new ContentMediaRepositoryFake();
        $repository->media[1] = ContentMediaRepositoryFake::media(1, $request->sha256());
        $storage = new ContentMediaStorageFake();
        $service = new AdministratorContentMediaService(
            $repository,
            $storage,
            new ContentMediaAuditRepositoryFake(),
            new ContentMediaTransactionFake(),
        );

        try {
            $service->upload($request, 9, null, null);
            self::fail('Expected a duplicate-media conflict.');
        } catch (ContentMediaException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('This image already exists in the media library.', $exception->getMessage());
        }

        self::assertSame(0, $storage->stores);
    }

    public function testUsedMediaCannotBeDeleted(): void
    {
        $repository = new ContentMediaRepositoryFake();
        $repository->media[4] = ContentMediaRepositoryFake::media(4, str_repeat('c', 64), 2);
        $transaction = new ContentMediaTransactionFake();
        $storage = new ContentMediaStorageFake();
        $service = new AdministratorContentMediaService(
            $repository,
            $storage,
            new ContentMediaAuditRepositoryFake(),
            $transaction,
        );

        try {
            $service->delete(4, 9, null, null);
            self::fail('Expected in-use media deletion to be blocked.');
        } catch (ContentMediaException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertStringContainsString('cannot be deleted', $exception->getMessage());
        }

        self::assertSame(1, $transaction->rollbacks);
        self::assertSame([], $storage->removed);
        self::assertArrayHasKey(4, $repository->media);
    }

    public function testUnusedMediaDeletionSoftDeletesThenRemovesFile(): void
    {
        $repository = new ContentMediaRepositoryFake();
        $repository->media[4] = ContentMediaRepositoryFake::media(4, str_repeat('c', 64));
        $transaction = new ContentMediaTransactionFake();
        $storage = new ContentMediaStorageFake();
        $audit = new ContentMediaAuditRepositoryFake();
        $service = new AdministratorContentMediaService($repository, $storage, $audit, $transaction);

        $service->delete(4, 9, '127.0.0.1', 'Media Test');

        self::assertArrayNotHasKey(4, $repository->media);
        self::assertSame(['uploads/content/' . str_repeat('d', 64) . '.png'], $storage->removed);
        self::assertSame('content_media_deleted', $audit->entries[0]->eventType());
        self::assertSame(1, $transaction->commits);
    }

    private function uploadRequest(): ContentMediaUploadRequest
    {
        $bytes = base64_decode(self::PNG_BASE64, true);
        self::assertIsString($bytes);
        $path = $this->directory . DIRECTORY_SEPARATOR . 'source-' . bin2hex(random_bytes(4)) . '.tmp';
        self::assertSame(strlen($bytes), file_put_contents($path, $bytes));

        return ContentMediaUploadRequest::fromInput(
            new UploadedFile('garden.png', 'image/png', $path, UPLOAD_ERR_OK, strlen($bytes)),
            'Garden',
            'Garden terrace with mountain view',
            null,
            [
                'allowed_mime_extensions' => [
                    'image/jpeg' => ['jpg', 'jpeg'],
                    'image/png' => ['png'],
                    'image/webp' => ['webp'],
                ],
                'maximum_file_size_bytes' => 5 * 1024 * 1024,
                'minimum_width_pixels' => 320,
                'minimum_height_pixels' => 180,
                'maximum_width_pixels' => 6000,
                'maximum_height_pixels' => 6000,
                'require_decodability' => true,
                'filesystem_root' => 'public/uploads/content',
                'database_path_prefix' => 'uploads/content/',
            ],
        );
    }
}

final class ContentMediaRepositoryFake implements ContentMediaRepository
{
    /** @var array<int, ContentMedia> */
    public array $media = [];
    private int $nextId = 1;

    public function findAllActive(): array { return array_values($this->media); }
    public function findActiveById(int $id): ?ContentMedia { return $this->media[$id] ?? null; }
    public function findActiveByIdForShare(int $id): ?ContentMedia { return $this->findActiveById($id); }
    public function findActiveByIdForUpdate(int $id): ?ContentMedia { return $this->findActiveById($id); }
    public function findActiveByChecksum(string $sha256): ?ContentMedia
    {
        foreach ($this->media as $media) {
            if (hash_equals($media->sha256(), $sha256)) return $media;
        }
        return null;
    }

    public function create(
        string $storagePath,
        string $originalName,
        string $mimeType,
        string $extension,
        int $byteSize,
        int $width,
        int $height,
        string $sha256,
        ?string $title,
        string $altText,
        ?string $caption,
        int $uploadedBy,
    ): ContentMedia {
        $id = $this->nextId++;
        $time = new DateTimeImmutable('2026-08-04 16:40:00 UTC');
        return $this->media[$id] = new ContentMedia(
            $id,
            $storagePath,
            $originalName,
            $mimeType,
            $extension,
            $byteSize,
            $width,
            $height,
            $sha256,
            $title,
            $altText,
            $caption,
            $uploadedBy,
            $time,
            $time,
            null,
            0,
        );
    }

    public function updateMetadata(int $id, ?string $title, string $altText, ?string $caption): ?ContentMedia
    {
        $current = $this->media[$id] ?? null;
        if (!$current instanceof ContentMedia) return null;
        return $this->media[$id] = new ContentMedia(
            $current->id(),
            $current->storagePath(),
            $current->originalName(),
            $current->mimeType(),
            $current->extension(),
            $current->byteSize(),
            $current->width(),
            $current->height(),
            $current->sha256(),
            $title,
            $altText,
            $caption,
            $current->uploadedBy(),
            $current->createdAt(),
            new DateTimeImmutable('2026-08-04 16:50:00 UTC'),
            null,
            $current->usageCount(),
        );
    }

    public function softDelete(int $id): bool
    {
        if (!isset($this->media[$id])) return false;
        unset($this->media[$id]);
        return true;
    }

    public static function media(int $id, string $sha256, int $usageCount = 0): ContentMedia
    {
        $time = new DateTimeImmutable('2026-08-04 16:40:00 UTC');
        return new ContentMedia(
            $id,
            'uploads/content/' . str_repeat('d', 64) . '.png',
            'garden.png',
            'image/png',
            'png',
            458,
            320,
            180,
            $sha256,
            'Garden',
            'Garden terrace with mountain view',
            null,
            9,
            $time,
            $time,
            null,
            $usageCount,
        );
    }
}

final class ContentMediaStorageFake implements ContentMediaStorage
{
    public ?StoredContentMedia $next = null;
    public int $stores = 0;
    /** @var list<string> */
    public array $removed = [];

    public function store(ContentMediaUploadRequest $request): StoredContentMedia
    {
        $this->stores++;
        if (!$this->next instanceof StoredContentMedia) throw new RuntimeException('No stored media configured.');
        return $this->next;
    }

    public function remove(string $relativePath): void { $this->removed[] = $relativePath; }
}

final class ContentMediaAuditRepositoryFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public function append(AuditLogEntry $entry): void { $this->entries[] = $entry; }
}

final class ContentMediaTransactionFake implements CmsTransaction
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
