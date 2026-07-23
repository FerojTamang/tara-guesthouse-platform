<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorRoomImageController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Session;
use App\Core\UploadedFile;
use App\Core\ViewRenderer;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogTransaction;
use App\Services\RoomImageService;
use App\Storage\RoomImageStorage;
use App\Storage\StoredRoomImage;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorRoomImageManagementTest extends TestCase
{
    private string $savePath;
    private string $uploadPath;
    private array $sessionConfiguration;
    private Session $session;
    private AdministratorSession $administratorSession;
    private CsrfTokenManager $csrfTokens;
    private Application $application;
    private HttpRoomRepository $rooms;
    private HttpImageRepository $images;
    private HttpImageStorage $storage;
    private HttpImageTransaction $transaction;

    protected function setUp(): void
    {
        $this->savePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-admin-images-' . bin2hex(random_bytes(8));
        mkdir($this->savePath, 0700);
        $this->uploadPath = tempnam(sys_get_temp_dir(), 'tgh-http-image-');
        file_put_contents($this->uploadPath, self::png());
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $events = [];
        $this->rooms = new HttpRoomRepository($events, self::room());
        $this->images = new HttpImageRepository($events);
        $this->storage = new HttpImageStorage($events);
        $this->transaction = new HttpImageTransaction($events);
        $service = new RoomImageService(
            $this->rooms,
            $this->images,
            $this->storage,
            $this->transaction,
            10,
        );
        $flash = new RoomCatalogFlashMessages($this->session);
        $imageController = new AdministratorRoomImageController(
            static fn (): RoomImageService => $service,
            fn (): array => $this->uploadPolicy(),
            $flash,
        );
        $logger = new NullLogger();
        $renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new HttpImageAuthenticator(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $router = new \App\Core\Router();
        $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $registrar(
            $router,
            $renderer,
            $authenticationController,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($this->csrfTokens),
            null,
            null,
            null,
            null,
            $imageController,
        );
        $this->application = new Application($router, new ErrorHandler($logger, false), $this->session);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }
        @unlink($this->uploadPath);
        @rmdir($this->savePath);
    }

    public function testGuestAndGetRequestsCannotReachMutationActions(): void
    {
        foreach ([
            '/admin/rooms/7/images',
            '/admin/rooms/7/images/1',
            '/admin/rooms/7/images/1/primary',
            '/admin/rooms/7/images/1/delete',
        ] as $path) {
            $response = $this->application->handle(Request::create('POST', $path));
            self::assertSame(302, $response->statusCode());
            self::assertSame('/admin/login', $response->headers()['Location']);
            $this->reopenSession();
        }

        $this->authenticate();
        $get = $this->application->handle(Request::create('GET', '/admin/rooms/7/images/1/delete'));
        self::assertSame(405, $get->statusCode());
        self::assertSame('POST', $get->headers()['Allow']);
        self::assertSame([], $this->images->items);
    }

    public function testCsrfRejectionPreventsServiceCalls(): void
    {
        $this->authenticate();
        $response = $this->application->handle(Request::create('POST', '/admin/rooms/7/images'));
        self::assertSame(403, $response->statusCode());
        self::assertSame(0, $this->storage->stores);

        $this->reopenSession();
        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/7/images',
            input: ['_csrf' => str_repeat('0', 64)],
        ));
        self::assertSame(403, $response->statusCode());
        self::assertSame(0, $this->storage->stores);
    }

    public function testSuccessfulUploadMetadataPrimaryAndDeleteRedirectSafely(): void
    {
        $this->authenticate();
        $upload = $this->application->handle($this->uploadRequest());
        self::assertSame(303, $upload->statusCode());
        self::assertSame('/admin/rooms', $upload->headers()['Location']);
        self::assertTrue($this->images->items[1]->isPrimary());
        self::assertSame(1, $this->storage->stores);
        $this->assertFlashOnce('Room image uploaded successfully.');

        $metadata = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/7/images/1',
            input: ['_csrf' => $this->csrfTokens->token(), 'alt_text' => '  View  ', 'sort_order' => '5'],
        ));
        self::assertSame(303, $metadata->statusCode());
        self::assertSame('View', $this->images->items[1]->altText());
        self::assertSame(5, $this->images->items[1]->sortOrder());
        $this->assertFlashOnce('Room image metadata updated successfully.');

        $this->images->items[1] = self::image(1, false, 5, 'View');
        $primary = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/7/images/1/primary',
            input: ['_csrf' => $this->csrfTokens->token(), 'room_id' => '999', 'image_id' => '999'],
        ));
        self::assertSame(303, $primary->statusCode());
        self::assertTrue($this->images->items[1]->isPrimary());
        $this->assertFlashOnce('Primary room image updated successfully.');

        $delete = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/7/images/1/delete',
            input: ['_csrf' => $this->csrfTokens->token(), 'file_path' => 'sensitive'],
        ));
        self::assertSame(303, $delete->statusCode());
        self::assertSame([], $this->images->items);
        self::assertSame(1, $this->storage->removes);
        $this->assertFlashOnce('Room image removed successfully.');
    }

    public function testValidationIdentifiersLimitMissingAndCleanupFailuresAreSafe(): void
    {
        $this->authenticate();
        $missingUpload = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/7/images',
            input: ['_csrf' => $this->csrfTokens->token(), 'is_primary' => '0'],
        ));
        self::assertSame(422, $missingUpload->statusCode());
        self::assertSame('Room image is required.', $missingUpload->body());

        foreach (['0', '-1', '+1', ' 1', '1.0', '1e2', 'x1', str_repeat('9', 40)] as $invalid) {
            $this->reopenSession();
            $response = $this->application->handle(Request::create(
                'POST',
                '/admin/rooms/' . rawurlencode($invalid) . '/images/1/primary',
                input: ['_csrf' => $this->csrfTokens->token()],
            ));
            self::assertSame(404, $response->statusCode());
        }

        $this->images->count = 10;
        $this->reopenSession();
        $limit = $this->application->handle($this->uploadRequest());
        self::assertSame(409, $limit->statusCode());
        self::assertSame('This room already has the maximum of 10 images.', $limit->body());

        $this->images->count = 0;
        $this->images->items[1] = self::image(1, false);
        $this->storage->failRemove = true;
        $this->reopenSession();
        $cleanup = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/7/images/1/delete',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(500, $cleanup->statusCode());
        self::assertSame('Room image cleanup failed.', $cleanup->body());
        self::assertStringNotContainsString('uploads/rooms/', $cleanup->body());
    }

    public function testInvalidImageIdentifiersAndOwnershipFailuresReturnSafe404WithoutServiceCalls(): void
    {
        $this->authenticate();

        foreach (['0', '-1', '+1', ' 1', '1 ', "1\t", "\n1", '1.0', '1e2', '01x', str_repeat('9', 40)] as $invalid) {
            $before = $this->transaction->begins;
            $response = $this->application->handle(Request::create(
                'POST',
                '/admin/rooms/7/images/' . rawurlencode($invalid) . '/primary',
                input: ['_csrf' => $this->csrfTokens->token()],
            ));
            self::assertSame(404, $response->statusCode());
            self::assertSame($before, $this->transaction->begins);
            $this->reopenSession();
        }

        foreach ([' 7', '7 ', "7\t", "\n7"] as $invalid) {
            $before = $this->transaction->begins;
            $response = $this->application->handle(Request::create(
                'POST',
                '/admin/rooms/' . rawurlencode($invalid) . '/images/1/primary',
                input: ['_csrf' => $this->csrfTokens->token()],
            ));
            self::assertSame(404, $response->statusCode());
            self::assertSame('Not Found', $response->body());
            self::assertSame($before, $this->transaction->begins);
            self::assertStringNotContainsString($invalid, $response->body());
            $this->reopenSession();
        }

        $before = $this->transaction->begins;
        $missingRoom = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/999/images/1/primary',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(404, $missingRoom->statusCode());
        self::assertSame('Not Found', $missingRoom->body());
        self::assertSame($before + 1, $this->transaction->begins);

        $this->images->items[1] = self::image(1, false);
        $this->reopenSession();
        $wrongRoom = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/8/images/1/primary',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(404, $wrongRoom->statusCode());
        self::assertSame($missingRoom->body(), $wrongRoom->body());
        self::assertStringNotContainsString('uploads/rooms/', $wrongRoom->body());
        self::assertStringNotContainsString($this->uploadPath, $wrongRoom->body());
    }

    public function testInvalidUploadVariantsReturn422BeforeServiceExecution(): void
    {
        $this->authenticate();
        $small = tempnam(sys_get_temp_dir(), 'tgh-small-image-');
        $invalid = tempnam(sys_get_temp_dir(), 'tgh-invalid-image-');
        file_put_contents($small, self::png(32, 32));
        file_put_contents($invalid, "\x89PNG\r\n\x1a\nnot-decodable");

        try {
            $cases = [
                ['partial', 'room.png', $this->uploadPath, UPLOAD_ERR_PARTIAL, filesize($this->uploadPath), '0'],
                ['oversized', 'room.png', $this->uploadPath, UPLOAD_ERR_OK, 6 * 1024 * 1024, '0'],
                ['wrong mime', 'room.png', $invalid, UPLOAD_ERR_OK, filesize($invalid), '0'],
                ['wrong extension', 'room.gif', $this->uploadPath, UPLOAD_ERR_OK, filesize($this->uploadPath), '0'],
                ['invalid dimensions', 'room.png', $small, UPLOAD_ERR_OK, filesize($small), '0'],
                ['undecodable', 'room.png', $invalid, UPLOAD_ERR_OK, filesize($invalid), '0'],
                ['missing primary', 'room.png', $this->uploadPath, UPLOAD_ERR_OK, filesize($this->uploadPath), null],
            ];

            foreach ($cases as [$label, $name, $path, $error, $size, $primary]) {
                $before = $this->transaction->begins;
                $input = ['_csrf' => $this->csrfTokens->token(), 'alt_text' => 'HTTP alt', 'sort_order' => '4'];
                if ($primary !== null) {
                    $input['is_primary'] = $primary;
                }
                $response = $this->application->handle(Request::create(
                    'POST',
                    '/admin/rooms/7/images',
                    input: [...$input, 'room_id' => '999', 'image_id' => '999', 'file_path' => 'secret'],
                    files: ['image' => ['name' => $name, 'type' => 'attacker/value', 'tmp_name' => $path, 'error' => $error, 'size' => $size]],
                ));
                self::assertSame(422, $response->statusCode(), $label);
                self::assertSame($before, $this->transaction->begins, $label);
                self::assertStringNotContainsString($path, $response->body());
                self::assertStringNotContainsString('secret', $response->body());
                $this->reopenSession();
            }
        } finally {
            @unlink($small);
            @unlink($invalid);
        }
    }

    public function testInvalidMetadataFieldsReturn422BeforeServiceExecution(): void
    {
        $this->images->items[1] = self::image(1, false);
        $this->authenticate();

        foreach ([
            ['sort_order' => '-1', 'alt_text' => null],
            ['sort_order' => '0', 'alt_text' => str_repeat('a', 256)],
            ['sort_order' => '0', 'alt_text' => "\xC3\x28"],
        ] as $input) {
            $before = $this->transaction->begins;
            $response = $this->application->handle(Request::create(
                'POST',
                '/admin/rooms/7/images/1',
                input: [...$input, '_csrf' => $this->csrfTokens->token()],
            ));
            self::assertSame(422, $response->statusCode());
            self::assertSame($before, $this->transaction->begins);
            self::assertSame(0, $this->images->updates);
            $this->reopenSession();
        }
    }

    private function uploadRequest(): Request
    {
        return Request::create(
            'POST',
            '/admin/rooms/7/images',
            input: ['_csrf' => $this->csrfTokens->token(), 'alt_text' => '', 'sort_order' => '', 'is_primary' => '0'],
            files: ['image' => [
                'name' => 'room.png',
                'type' => 'image/png',
                'tmp_name' => $this->uploadPath,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($this->uploadPath),
            ]],
        );
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    private function reopenSession(): void
    {
        $this->session = Session::start($this->sessionConfiguration);
    }

    private function assertFlashOnce(string $expected): void
    {
        $this->reopenSession();
        $messages = new RoomCatalogFlashMessages($this->session);
        self::assertSame($expected, $messages->pullRoomSuccess());
        self::assertNull($messages->pullRoomSuccess());
    }

    private function uploadPolicy(): array
    {
        return (require dirname(__DIR__, 2) . '/config/upload.php')['room_images'];
    }

    private static function room(): Room
    {
        $time = new DateTimeImmutable('2026-01-01 UTC');
        $type = new RoomType(1, 'Type', 'type', null, '100.00', 2, 0, true, $time, $time);
        return new Room(7, $type, '7', 'Room', 'room', null, '100.00', 2, 0, Room::STATUS_ACTIVE, true, $time, $time, null);
    }

    private static function image(int $id, bool $primary, int $sortOrder = 0, ?string $alt = null): RoomImage
    {
        return new RoomImage($id, 7, 'uploads/rooms/' . str_repeat(dechex($id), 64) . '.png', $alt, $sortOrder, $primary, new DateTimeImmutable('2026-01-01 UTC'));
    }

    private static function png(int $width = 640, int $height = 480): string
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $row = "\0" . str_repeat("\0", $width * 3);
        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress(str_repeat($row, $height), 9)) . $chunk('IEND', '');
    }

    private static function loginPolicy(): array
    {
        return ['username_min_characters' => 3, 'username_max_characters' => 50, 'identifier_max_bytes' => 150, 'password_max_bytes' => 72, 'max_failed_attempts' => 5, 'attempt_window_seconds' => 900, 'block_duration_seconds' => 900];
    }

    private function configuration(): array
    {
        return ['name' => 'tgh_' . bin2hex(random_bytes(8)), 'inactivity_timeout' => 1800, 'absolute_lifetime' => 43200, 'cookie' => ['lifetime' => 0, 'secure' => false, 'http_only' => true, 'same_site' => 'Lax', 'path' => '/', 'domain' => null], 'strict_mode' => true, 'use_cookies' => true, 'use_only_cookies' => true, 'use_trans_sid' => false, 'save_handler' => 'files', 'save_path' => realpath($this->savePath), 'gc_maxlifetime' => 43200];
    }
}

final class HttpImageTransaction implements RoomCatalogTransaction
{
    private bool $active = false;
    public int $begins = 0;
    public function __construct(private array &$events) {}
    public function begin(): void { $this->events[] = 'begin'; $this->begins++; $this->active = true; }
    public function commit(): void { $this->events[] = 'commit'; $this->active = false; }
    public function rollBack(): void { $this->events[] = 'rollback'; $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}

final class HttpRoomRepository implements RoomCatalogRepository
{
    public function __construct(private array &$events, public ?Room $room) {}
    public function lockCurrentById(int $roomId): ?Room { $this->events[] = 'lock'; return in_array($roomId, [7, 8], true) ? $this->room : null; }
    public function findById(int $id): ?Room { return $this->room; }
    public function findAll(): array { return []; }
    public function findAmenityIds(int $roomId): array { return []; }
    public function create(int $roomTypeId, string $roomNumber, string $name, string $slug, ?string $description, string $pricePerNight, int $capacityAdults, int $capacityChildren, string $operationalStatus, bool $published): Room { throw new LogicException(); }
    public function update(int $id, int $roomTypeId, string $roomNumber, string $name, string $slug, ?string $description, string $pricePerNight, int $capacityAdults, int $capacityChildren, string $operationalStatus, bool $published): ?Room { throw new LogicException(); }
    public function replaceAmenities(int $roomId, array $amenityIds): void { throw new LogicException(); }
    public function softDelete(int $id): ?Room { throw new LogicException(); }
}

final class HttpImageRepository implements RoomImageCatalogRepository
{
    public array $items = [];
    public int $count = 0;
    public int $updates = 0;
    public function __construct(private array &$events) {}
    public function countByRoomId(int $roomId): int { $this->events[] = 'count'; return max($this->count, count($this->items)); }
    public function findAllByRoomId(int $roomId): array { $this->events[] = 'list'; return array_values($this->items); }
    public function findPrimaryByRoomId(int $roomId): ?RoomImage { foreach ($this->items as $item) { if ($item->isPrimary()) return $item; } return null; }
    public function findByIdAndRoomId(int $imageId, int $roomId): ?RoomImage { return $roomId === 7 ? ($this->items[$imageId] ?? null) : null; }
    public function create(int $roomId, string $filePath, ?string $altText, int $sortOrder, bool $primary): RoomImage { return $this->items[1] = new RoomImage(1, $roomId, $filePath, $altText, $sortOrder, $primary, new DateTimeImmutable('2026-01-01 UTC')); }
    public function clearPrimaryByRoomId(int $roomId): void { foreach ($this->items as $id => $item) { $this->items[$id] = new RoomImage($id, 7, $item->filePath(), $item->altText(), $item->sortOrder(), false, $item->createdAt()); } }
    public function updateMetadata(int $imageId, int $roomId, ?string $altText, int $sortOrder): ?RoomImage { $this->updates++; $item = $this->findByIdAndRoomId($imageId, $roomId); return $item === null ? null : ($this->items[$imageId] = new RoomImage($imageId, 7, $item->filePath(), $altText, $sortOrder, $item->isPrimary(), $item->createdAt())); }
    public function setPrimary(int $imageId, int $roomId): ?RoomImage { $item = $this->findByIdAndRoomId($imageId, $roomId); return $item === null ? null : ($this->items[$imageId] = new RoomImage($imageId, 7, $item->filePath(), $item->altText(), $item->sortOrder(), true, $item->createdAt())); }
    public function delete(int $imageId, int $roomId): ?RoomImage { $item = $this->findByIdAndRoomId($imageId, $roomId); if ($item !== null) unset($this->items[$imageId]); return $item; }
}

final class HttpImageStorage implements RoomImageStorage
{
    public int $stores = 0;
    public int $removes = 0;
    public bool $failRemove = false;
    public function __construct(private array &$events) {}
    public function store(UploadedFile $file, string $detectedMediaType): StoredRoomImage { $this->stores++; return new StoredRoomImage('uploads/rooms/' . str_repeat('a', 64) . '.png', str_repeat('a', 64) . '.png', 'png', $file->size()); }
    public function remove(string $relativePath): void { $this->removes++; if ($this->failRemove) throw new RuntimeException('sensitive path ' . $relativePath); }
}

final class HttpImageAuthenticator implements AdministratorAuthenticator
{
    public function authenticate(AdministratorLoginRequest $request, string $remoteAddress, string $context = 'http'): AdministratorIdentity { throw new LogicException(); }
}
