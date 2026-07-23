<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Core\UploadedFile;
use App\Exceptions\RoomImageException;
use App\Models\Room;
use App\Models\RoomImage;
use App\Repositories\RoomImageCatalogRepository;
use App\Repositories\RoomImageRepository;
use App\Repositories\RoomRepository;
use App\Requests\RoomImageUploadRequest;
use App\Services\PdoRoomCatalogTransaction;
use App\Services\RoomImageService;
use App\Storage\LocalRoomImageStorage;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class RoomImageServiceTest extends DatabaseTestCase
{
    private string $storageRoot;
    private string $uploadPath;
    private int $roomId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner($this->connection, dirname(__DIR__, 3) . '/database/migrations'))->migrate();
        $this->storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-image-service-' . bin2hex(random_bytes(8));
        mkdir($this->storageRoot, 0700);
        $this->uploadPath = tempnam(sys_get_temp_dir(), 'tgh-image-upload-');
        file_put_contents($this->uploadPath, self::png());
        $typeId = $this->insertRoomType();
        $this->roomId = $this->insertRoom($typeId);
    }

    protected function tearDown(): void
    {
        @unlink($this->uploadPath);

        if (isset($this->storageRoot) && is_dir($this->storageRoot)) {
            foreach (scandir($this->storageRoot) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    @unlink($this->storageRoot . DIRECTORY_SEPARATOR . $entry);
                }
            }
            @rmdir($this->storageRoot);
        }

        parent::tearDown();
    }

    public function testUploadsPersistFilesPrimaryRulesAndDeterministicOrder(): void
    {
        $service = $this->service(new RoomImageRepository($this->connection));
        $first = $service->upload($this->request(false));
        $second = $service->upload($this->request(false));
        $replacement = $service->upload($this->request(true));
        $repository = new RoomImageRepository($this->connection);

        self::assertTrue($first->isPrimary());
        self::assertFalse($second->isPrimary());
        self::assertTrue($replacement->isPrimary());
        self::assertSame([0, 1, 2], array_map(
            static fn (RoomImage $image): int => $image->sortOrder(),
            $repository->findAllByRoomId($this->roomId),
        ));
        self::assertSame($replacement->id(), $repository->findPrimaryByRoomId($this->roomId)?->id());
        self::assertCount(3, $this->storedFiles());
        self::assertSame($replacement->filePath(), $repository->findById($replacement->id())?->filePath());
    }

    public function testNinthCountPermitsTenthAndTenthRejectsEleventhBeforeStorage(): void
    {
        for ($index = 0; $index < 9; $index++) {
            $this->insertImage($index, $index === 0);
        }

        $service = $this->service(new RoomImageRepository($this->connection));
        self::assertInstanceOf(RoomImage::class, $service->upload($this->request(false)));
        self::assertCount(1, $this->storedFiles());

        try {
            $service->upload($this->request(false));
            self::fail('Expected maximum image failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isLimit());
            self::assertSame('This room already has the maximum of 10 images.', $exception->getMessage());
            self::assertCount(1, $this->storedFiles());
            self::assertSame(10, (new RoomImageRepository($this->connection))->countByRoomId($this->roomId));
        }
    }

    public function testRepositoryFailureRollsBackPrimaryAndRemovesStoredFile(): void
    {
        $this->insertImage(0, true);
        $repository = new RoomImageRepository($this->connection);
        $service = $this->service(new FailingCreateImageRepository($repository));

        try {
            $service->upload($this->request(true));
            self::fail('Expected repository failure.');
        } catch (RoomImageException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Room image operation failed.', $exception->getMessage());
            self::assertCount(0, $this->storedFiles());
            self::assertSame(1, $repository->countByRoomId($this->roomId));
            self::assertTrue($repository->findPrimaryByRoomId($this->roomId)?->isPrimary());
            self::assertFalse($this->connection->inTransaction());
        }
    }

    private function service(RoomImageCatalogRepository $images): RoomImageService
    {
        return new RoomImageService(
            new RoomRepository($this->connection),
            $images,
            new LocalRoomImageStorage($this->storageRoot),
            new PdoRoomCatalogTransaction($this->connection),
            10,
        );
    }

    private function request(bool $primary): RoomImageUploadRequest
    {
        $file = new UploadedFile('room.png', 'image/png', $this->uploadPath, UPLOAD_ERR_OK, filesize($this->uploadPath));
        $configuration = require dirname(__DIR__, 3) . '/config/upload.php';
        return RoomImageUploadRequest::fromInput($this->roomId, $file, null, null, $primary, $configuration['room_images']);
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return array_values(array_filter(scandir($this->storageRoot) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
    }

    private function insertRoomType(): int
    {
        $statement = $this->connection->prepare('INSERT INTO room_types (name, slug, base_price, capacity_adults, capacity_children, is_active) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute(['Image Type', 'image-type', '100.00', 2, 0, 1]);
        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(int $typeId): int
    {
        $statement = $this->connection->prepare('INSERT INTO rooms (room_type_id, room_number, name, slug, price_per_night, capacity_adults, capacity_children, operational_status, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$typeId, '301', 'Image Room', 'image-room-service', '100.00', 2, 0, Room::STATUS_ACTIVE, 1]);
        return (int) $this->connection->lastInsertId();
    }

    private function insertImage(int $sortOrder, bool $primary): void
    {
        $statement = $this->connection->prepare('INSERT INTO room_images (room_id, file_path, alt_text, sort_order, is_primary) VALUES (?, ?, NULL, ?, ?)');
        $statement->execute([$this->roomId, 'uploads/rooms/' . str_pad(dechex($sortOrder + 1), 64, '0', STR_PAD_LEFT) . '.png', $sortOrder, $primary ? 1 : 0]);
    }

    private static function png(): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $row = "\0" . str_repeat("\0", 640 * 3);
        return "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', 640, 480, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress(str_repeat($row, 480), 9))
            . $chunk('IEND', '');
    }
}

final readonly class FailingCreateImageRepository implements RoomImageCatalogRepository
{
    public function __construct(private RoomImageCatalogRepository $inner) {}
    public function countByRoomId(int $roomId): int { return $this->inner->countByRoomId($roomId); }
    public function findAllByRoomId(int $roomId): array { return $this->inner->findAllByRoomId($roomId); }
    public function findPrimaryByRoomId(int $roomId): ?RoomImage { return $this->inner->findPrimaryByRoomId($roomId); }
    public function create(int $roomId, string $filePath, ?string $altText, int $sortOrder, bool $primary): RoomImage { throw new RuntimeException('sensitive database failure'); }
    public function clearPrimaryByRoomId(int $roomId): void { $this->inner->clearPrimaryByRoomId($roomId); }
}
