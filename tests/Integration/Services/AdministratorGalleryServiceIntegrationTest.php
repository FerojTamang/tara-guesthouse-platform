<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\GalleryException;
use App\Models\AuditLogEntry;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Requests\GalleryCategoryRequest;
use App\Requests\GalleryImageUploadRequest;
use App\Requests\GalleryItemMetadataRequest;
use App\Requests\GalleryReorderRequest;
use App\Services\AdministratorGalleryService;
use App\Services\PdoGalleryTransaction;
use App\Storage\GalleryImageStorage;
use App\Storage\StoredGalleryImage;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class AdministratorGalleryServiceIntegrationTest extends DatabaseTestCase
{
    private GalleryRepository $repository;
    private int $administratorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR
                . 'database'
                . DIRECTORY_SEPARATOR
                . 'migrations',
        ))->migrate();
        $this->repository = new GalleryRepository($this->connection);
        $this->administratorId = $this->insertAdministrator();
    }

    public function testCategoryAndItemWorkflowsPersistWithAuditAndRetainedRemoval(): void
    {
        $storage = new AdministratorGalleryIntegrationStorage();
        $service = $this->service(new PdoAuditLogRepository($this->connection), $storage);
        $category = $service->createCategory(
            GalleryCategoryRequest::fromInput('Nature', 'nature', true),
            $this->administratorId,
            '127.0.0.1',
            'Gallery Integration Test',
        );
        $category = $service->updateCategory(
            $category->id(),
            GalleryCategoryRequest::fromInput('Local Nature', 'local-nature', false),
            $this->administratorId,
            null,
            null,
        );
        self::assertSame('local-nature', $category->slug());
        self::assertFalse($category->isActive());

        $created = $service->createItem(
            $this->metadata($category->id(), 'Riverside Sunrise', 10),
            $this->uploadRequest(),
            true,
            false,
            $this->administratorId,
            '127.0.0.1',
            'Gallery Integration Test',
        );
        self::assertSame($storage->relativePath, $created->filePath());
        self::assertTrue($created->isActive());
        self::assertFalse($created->isPublished());

        $updated = $service->updateItem(
            $created->id(),
            $this->metadata(null, 'Updated Riverside Sunrise', 20),
            $this->administratorId,
            null,
            null,
        );
        self::assertNull($updated->categoryId());
        self::assertSame($storage->relativePath, $updated->filePath());
        self::assertSame(20, $updated->sortOrder());

        $published = $service->setItemPublished(
            $created->id(),
            true,
            $this->administratorId,
            null,
            null,
        );
        self::assertTrue($published->isPublished());

        $inactive = $service->setItemActive(
            $created->id(),
            false,
            $this->administratorId,
            null,
            null,
        );
        self::assertFalse($inactive->isActive());
        self::assertTrue($inactive->isPublished());

        $reordered = $service->reorder(
            GalleryReorderRequest::fromInput([
                ['id' => $created->id(), 'sort_order' => 5],
            ]),
            $this->administratorId,
            null,
            null,
        );
        self::assertCount(1, $reordered);
        self::assertSame(5, $reordered[0]->sortOrder());

        $removed = $service->removeItem(
            $created->id(),
            $this->administratorId,
            null,
            null,
        );
        self::assertFalse($removed->isActive());
        self::assertFalse($removed->isPublished());
        self::assertNotNull($this->repository->findItemById($created->id()));
        self::assertSame([], $storage->removed);

        $events = $this->connection->query(
            "SELECT event_type FROM audit_logs WHERE entity_type IN ('gallery_category', 'gallery_item') ORDER BY id",
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame([
            'gallery_category_created',
            'gallery_category_updated',
            'gallery_item_created',
            'gallery_item_updated',
            'gallery_item_publication_changed',
            'gallery_item_active_changed',
            'gallery_items_reordered',
            'gallery_item_removed',
        ], $events);

        $audit = $this->connection->query(
            "SELECT user_id, entity_id, ip_address, user_agent, metadata_json FROM audit_logs "
            . "WHERE event_type = 'gallery_item_created' LIMIT 1",
        )->fetch();
        self::assertIsArray($audit);
        self::assertSame((string) $this->administratorId, (string) $audit['user_id']);
        self::assertSame((string) $created->id(), (string) $audit['entity_id']);
        self::assertSame('127.0.0.1', $audit['ip_address']);
        self::assertSame('Gallery Integration Test', $audit['user_agent']);
        self::assertStringContainsString('"file_path":"' . $storage->relativePath . '"', (string) $audit['metadata_json']);
    }

    public function testAuditFailureRollsBackItemAndCompensatesStoredImage(): void
    {
        $storage = new AdministratorGalleryIntegrationStorage();
        $service = $this->service(new AdministratorGalleryFailingAuditRepository(), $storage);

        try {
            $service->createItem(
                $this->metadata(null, 'Riverside Sunrise', 10),
                $this->uploadRequest(),
                true,
                true,
                $this->administratorId,
                null,
                null,
            );
            self::fail('Expected a safe audit failure.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Gallery catalogue operation failed.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM gallery')->fetchColumn());
        self::assertSame([$storage->relativePath], $storage->removed);
    }

    public function testDuplicateCategoryConflictRollsBackWithoutAudit(): void
    {
        $service = $this->service(
            new PdoAuditLogRepository($this->connection),
            new AdministratorGalleryIntegrationStorage(),
        );
        $service->createCategory(
            GalleryCategoryRequest::fromInput('Nature', 'nature', true),
            $this->administratorId,
            null,
            null,
        );

        try {
            $service->createCategory(
                GalleryCategoryRequest::fromInput('Other Nature', 'nature', true),
                $this->administratorId,
                null,
                null,
            );
            self::fail('Expected a duplicate category slug conflict.');
        } catch (GalleryException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertSame(1, (int) $this->connection->query(
            'SELECT COUNT(*) FROM gallery_categories',
        )->fetchColumn());
        self::assertSame(1, (int) $this->connection->query(
            "SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'gallery_category'",
        )->fetchColumn());
    }

    private function service(
        AuditLogWriteRepository $audit,
        GalleryImageStorage $storage,
    ): AdministratorGalleryService {
        return new AdministratorGalleryService(
            $this->repository,
            $storage,
            $audit,
            new PdoGalleryTransaction($this->connection),
            static fn (): DateTimeImmutable => new DateTimeImmutable(
                '2026-07-30 09:00:00 UTC',
            ),
        );
    }

    private function metadata(?int $categoryId, string $title, int $sortOrder): GalleryItemMetadataRequest
    {
        return GalleryItemMetadataRequest::fromInput(
            $categoryId,
            $title,
            'Morning light beside the river.',
            'Sunrise over the river beside Tara Guest House',
            $sortOrder,
        );
    }

    private function uploadRequest(): GalleryImageUploadRequest
    {
        /** @var GalleryImageUploadRequest $request */
        $request = (new ReflectionClass(GalleryImageUploadRequest::class))->newInstanceWithoutConstructor();

        return $request;
    }

    private function insertAdministrator(): int
    {
        $this->connection->exec(
            "INSERT INTO roles (role_name, description) VALUES ('Administrator', 'Test role')",
        );
        $roleId = (int) $this->connection->lastInsertId();
        $statement = $this->connection->prepare(
            'INSERT INTO users ('
            . 'role_id, full_name, username, email, password, account_status'
            . ') VALUES ('
            . ':role_id, :full_name, :username, :email, :password, :account_status'
            . ')',
        );
        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Administrator',
            'username' => 'gallery-admin',
            'email' => 'gallery-admin@example.test',
            'password' => password_hash('Test-only-password-123!', PASSWORD_DEFAULT),
            'account_status' => 'active',
        ]);

        return (int) $this->connection->lastInsertId();
    }
}

final class AdministratorGalleryIntegrationStorage implements GalleryImageStorage
{
    public string $relativePath = 'gallery/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png';

    /** @var list<string> */
    public array $removed = [];

    public function store(GalleryImageUploadRequest $request): StoredGalleryImage
    {
        return new StoredGalleryImage(
            $this->relativePath,
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png',
            'png',
            'image/png',
            68,
            1,
            1,
        );
    }

    public function remove(string $relativePath): void
    {
        $this->removed[] = $relativePath;
    }
}

final class AdministratorGalleryFailingAuditRepository implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new RuntimeException('sensitive audit storage failure');
    }
}
