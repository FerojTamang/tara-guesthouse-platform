<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Controllers\AdministratorRoomController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Presentation\RoomImageUrl;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\AmenityCatalogRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Repositories\RoomTypeCatalogRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\AmenityCatalogService;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogService;
use App\Services\RoomCatalogServiceProvider;
use App\Services\RoomCatalogTransaction;
use App\Services\RoomTypeCatalogService;
use App\Services\RoomImageService;
use App\Storage\RoomImageStorage;
use App\Storage\StoredRoomImage;
use App\Core\UploadedFile;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorRoomManagementTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;

    private AdministratorSession $administratorSession;

    private CsrfTokenManager $csrfTokens;

    private Application $application;

    private RoomManagementRoomRepositoryFake $rooms;

    private RoomManagementRoomTypeRepositoryFake $roomTypes;

    private RoomManagementAmenityRepositoryFake $amenities;

    private RoomManagementImageRepositoryFake $images;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-room-management-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->roomTypes = new RoomManagementRoomTypeRepositoryFake();
        $this->amenities = new RoomManagementAmenityRepositoryFake();
        $this->rooms = new RoomManagementRoomRepositoryFake($this->roomTypes);
        $this->images = new RoomManagementImageRepositoryFake();
        $transaction = new RoomManagementTransactionFake();
        $logger = new NullLogger();
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new RoomManagementAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $services = new RoomCatalogServiceProvider(
            fn (): RoomTypeCatalogService => new RoomTypeCatalogService($this->roomTypes),
            fn (): AmenityCatalogService => new AmenityCatalogService($this->amenities),
            fn (): RoomCatalogService => new RoomCatalogService(
                $this->rooms,
                $this->roomTypes,
                $this->amenities,
                $transaction,
            ),
        );
        $flashMessages = new RoomCatalogFlashMessages($this->session);
        $catalogController = new AdministratorRoomCatalogController(
            $renderer,
            $services,
            $this->csrfTokens,
            $flashMessages,
            fn (): RoomImageService => new RoomImageService(
                $this->rooms,
                $this->images,
                new RoomManagementImageStorageFake(),
                $transaction,
                10,
            ),
            new RoomImageUrl(),
        );
        $roomController = new AdministratorRoomController(
            $renderer,
            $services,
            $this->csrfTokens,
            $flashMessages,
            fn (): RoomImageService => new RoomImageService(
                $this->rooms,
                $this->images,
                new RoomManagementImageStorageFake(),
                $transaction,
                10,
            ),
            new RoomImageUrl(),
        );
        $router = new Router();
        $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $registrar(
            $router,
            $renderer,
            $authenticationController,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($this->csrfTokens),
            $catalogController,
            null,
            null,
            $roomController,
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

        foreach (glob($this->savePath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->savePath)) {
            rmdir($this->savePath);
        }

        parent::tearDown();
    }

    public function testGuestCannotOpenOrSubmitTheCreateWorkflow(): void
    {
        $token = $this->csrfTokens->token();

        $getResponse = $this->application->handle(Request::create('GET', '/admin/rooms/create'));

        self::assertSame(302, $getResponse->statusCode());
        self::assertSame('/admin/login', $getResponse->headers()['Location']);

        $this->reopenSession();
        $postResponse = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms',
            input: [...$this->validInput(), '_csrf' => $token],
        ));

        self::assertSame(302, $postResponse->statusCode());
        self::assertSame('/admin/login', $postResponse->headers()['Location']);
        self::assertSame(0, $this->rooms->createCalls);
    }

    public function testCreateFormRendersEscapedCatalogueOptionsAndSecureDefaults(): void
    {
        $this->authenticate();
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('GET', '/admin/rooms/create'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Create room | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('method="post" action="/admin/rooms"', $response->body());
        self::assertStringContainsString('name="_csrf" value="' . $token . '"', $response->body());
        self::assertStringContainsString('&lt;b&gt;Deluxe&lt;/b&gt;', $response->body());
        self::assertStringNotContainsString('<b>Deluxe</b>', $response->body());
        self::assertStringContainsString('&lt;script&gt;Wi-Fi&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Wi-Fi</script>', $response->body());
        self::assertStringContainsString('name="operational_status"', $response->body());
        self::assertStringContainsString('value="active" selected', $response->body());
        self::assertStringContainsString('name="published" value="0"', $response->body());
        self::assertStringContainsString('name="amenity_ids[]"', $response->body());
        self::assertStringNotContainsString('id="room-images"', $response->body());
    }

    public function testInvalidCreateReturns422AndPreservesEscapedInputWithoutWriting(): void
    {
        $this->authenticate();
        $input = $this->validInput();
        $input['name'] = '<script>Unsafe</script>';
        $input['slug'] = 'Invalid Slug';
        $input['_csrf'] = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('POST', '/admin/rooms', input: $input));

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Slug is invalid.', $response->body());
        self::assertStringContainsString('&lt;script&gt;Unsafe&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Unsafe</script>', $response->body());
        self::assertSame(0, $this->rooms->createCalls);
    }

    public function testDuplicateCreateReturns409WithSafeConflictAndNormalizedValues(): void
    {
        $this->authenticate();
        $this->rooms->createFailure = RoomCatalogException::duplicateRoomNumber();

        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms',
            input: [...$this->validInput(), '_csrf' => $this->csrfTokens->token()],
        ));

        self::assertSame(409, $response->statusCode());
        self::assertStringContainsString('A room with this room number already exists.', $response->body());
        self::assertStringContainsString('value="4500.00"', $response->body());
        self::assertSame(1, $this->rooms->createCalls);
    }

    public function testSuccessfulCreateRedirectsAndShowsOneTimeNotice(): void
    {
        $this->authenticate();

        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms',
            input: [...$this->validInput(), '_csrf' => $this->csrfTokens->token()],
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/rooms', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(1, $this->rooms->createCalls);
        self::assertSame([1, 2], $this->rooms->amenityAssignments[1]);

        $this->reopenSession();
        $firstIndex = $this->application->handle(Request::create('GET', '/admin/rooms'));
        self::assertStringContainsString('Room created successfully.', $firstIndex->body());
        self::assertStringContainsString('Himalayan room', $firstIndex->body());

        $this->reopenSession();
        $secondIndex = $this->application->handle(Request::create('GET', '/admin/rooms'));
        self::assertStringNotContainsString('Room created successfully.', $secondIndex->body());
    }

    public function testEditFormLoadsAssignmentsAndUpdatePersistsValidatedInput(): void
    {
        $this->rooms->items[7] = self::room(
            7,
            $this->roomTypes->items[1],
            'A-102',
            '<b>Existing room</b>',
            'existing-room',
            "Line one\n<script>Line two</script>",
            '3200.00',
            2,
            0,
            Room::STATUS_ACTIVE,
            false,
        );
        $this->rooms->amenityAssignments[7] = [2];
        $this->images->items = [
            new RoomImage(2, 7, 'uploads/rooms/' . str_repeat('b', 64) . '.webp', '<script>Lake</script>', 1, true, new DateTimeImmutable('2026-01-01 UTC')),
            new RoomImage(1, 7, 'uploads/rooms/%2e%2e/' . str_repeat('a', 64) . '.png', null, 4, false, new DateTimeImmutable('2026-01-01 UTC')),
        ];
        $this->authenticate();

        $formResponse = $this->application->handle(Request::create('GET', '/admin/rooms/7/edit'));

        self::assertSame(200, $formResponse->statusCode());
        self::assertStringContainsString('action="/admin/rooms/7"', $formResponse->body());
        self::assertStringContainsString('value="&lt;b&gt;Existing room&lt;/b&gt;"', $formResponse->body());
        self::assertStringContainsString('&lt;script&gt;Line two&lt;/script&gt;', $formResponse->body());
        self::assertStringNotContainsString('<script>Line two</script>', $formResponse->body());
        self::assertMatchesRegularExpression('/value="2"\s+checked/', $formResponse->body());
        self::assertStringContainsString('id="room-images"', $formResponse->body());
        self::assertStringContainsString('enctype="multipart/form-data"', $formResponse->body());
        self::assertStringContainsString('name="image"', $formResponse->body());
        self::assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $formResponse->body());
        self::assertStringNotContainsString('/admin/rooms/7/images/2/primary', $formResponse->body());
        self::assertStringContainsString('/admin/rooms/7/images/1/primary', $formResponse->body());
        self::assertStringContainsString('Primary image', $formResponse->body());
        self::assertStringContainsString('&lt;script&gt;Lake&lt;/script&gt;', $formResponse->body());
        self::assertStringNotContainsString('<script>Lake</script>', $formResponse->body());
        self::assertStringContainsString('/assets/images/room-placeholder.svg', $formResponse->body());
        self::assertStringNotContainsString('%2e%2e', $formResponse->body());
        self::assertStringNotContainsString('C:\\', $formResponse->body());

        $this->reopenSession();
        $input = $this->validInput();
        $input['room_type_id'] = '2';
        $input['name'] = 'Updated room';
        $input['operational_status'] = Room::STATUS_MAINTENANCE;
        $input['published'] = '1';
        $input['amenity_ids'] = ['1'];
        $input['_csrf'] = $this->csrfTokens->token();
        $updateResponse = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/7',
            input: $input,
        ));

        self::assertSame(303, $updateResponse->statusCode());
        self::assertSame('/admin/rooms', $updateResponse->headers()['Location']);
        self::assertSame(1, $this->rooms->updateCalls);
        self::assertSame('Updated room', $this->rooms->items[7]->name());
        self::assertSame(2, $this->rooms->items[7]->roomType()->id());
        self::assertSame(Room::STATUS_MAINTENANCE, $this->rooms->items[7]->operationalStatus());
        self::assertTrue($this->rooms->items[7]->isPublished());
        self::assertSame([1], $this->rooms->amenityAssignments[7]);
    }

    public function testMissingOrMalformedEditTargetsReturn404WithoutDisclosure(): void
    {
        $this->authenticate();

        $missing = $this->application->handle(Request::create('GET', '/admin/rooms/999/edit'));
        self::assertSame(404, $missing->statusCode());
        self::assertSame('Not Found', $missing->body());
        self::assertSame('no-store', $missing->headers()['Cache-Control']);

        $this->reopenSession();
        $malformed = $this->application->handle(Request::create('GET', '/admin/rooms/not-a-number/edit'));
        self::assertSame(404, $malformed->statusCode());
        self::assertSame('Not Found', $malformed->body());
    }

    public function testSoftDeleteIsPostOnlyCsrfProtectedAndRemovesRoomFromCurrentListing(): void
    {
        $this->rooms->items[4] = self::room(
            4,
            $this->roomTypes->items[1],
            'B-201',
            'Standard room',
            'standard-room',
            null,
            '2500.00',
            1,
            0,
            Room::STATUS_INACTIVE,
            false,
        );
        $this->authenticate();

        $getResponse = $this->application->handle(Request::create('GET', '/admin/rooms/4/delete'));
        self::assertSame(405, $getResponse->statusCode());
        self::assertSame('POST', $getResponse->headers()['Allow']);

        $this->reopenSession();
        $forbidden = $this->application->handle(Request::create('POST', '/admin/rooms/4/delete'));
        self::assertSame(403, $forbidden->statusCode());
        self::assertSame(0, $this->rooms->softDeleteCalls);

        $this->reopenSession();
        $deleted = $this->application->handle(Request::create(
            'POST',
            '/admin/rooms/4/delete',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(303, $deleted->statusCode());
        self::assertSame('/admin/rooms', $deleted->headers()['Location']);
        self::assertSame(1, $this->rooms->softDeleteCalls);
        self::assertTrue($this->rooms->items[4]->isDeleted());
        self::assertSame([], $this->rooms->findAll());

        $this->reopenSession();
        $index = $this->application->handle(Request::create('GET', '/admin/rooms'));
        self::assertStringContainsString('Room removed successfully.', $index->body());
        self::assertStringNotContainsString('Standard room', $index->body());
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    private function reopenSession(): void
    {
        $this->session = Session::start($this->sessionConfiguration);
    }

    /** @return array<string, mixed> */
    private function validInput(): array
    {
        return [
            'room_type_id' => '1',
            'room_number' => 'A-101',
            'name' => 'Himalayan room',
            'slug' => 'himalayan-room',
            'description' => 'A comfortable physical room.',
            'price_per_night' => '4500',
            'capacity_adults' => '2',
            'capacity_children' => '1',
            'operational_status' => Room::STATUS_ACTIVE,
            'published' => '0',
            'amenity_ids' => ['2', '1'],
        ];
    }

    public static function room(
        int $id,
        RoomType $roomType,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $price,
        int $adults,
        int $children,
        string $status,
        bool $published,
        ?DateTimeImmutable $deletedAt = null,
    ): Room {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new Room(
            $id,
            $roomType,
            $roomNumber,
            $name,
            $slug,
            $description,
            $price,
            $adults,
            $children,
            $status,
            $published,
            $timestamp,
            $timestamp,
            $deletedAt,
        );
    }

    /** @return array<string, int> */
    private static function loginPolicy(): array
    {
        return [
            'username_min_characters' => 3,
            'username_max_characters' => 50,
            'identifier_max_bytes' => 150,
            'password_max_bytes' => 72,
            'max_failed_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ];
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'name' => 'tgh_' . bin2hex(random_bytes(8)),
            'inactivity_timeout' => 1800,
            'absolute_lifetime' => 43200,
            'cookie' => [
                'lifetime' => 0,
                'secure' => false,
                'http_only' => true,
                'same_site' => 'Lax',
                'path' => '/',
                'domain' => null,
            ],
            'strict_mode' => true,
            'use_cookies' => true,
            'use_only_cookies' => true,
            'use_trans_sid' => false,
            'save_handler' => 'files',
            'save_path' => realpath($this->savePath),
            'gc_maxlifetime' => 43200,
        ];
    }
}

final class RoomManagementRoomTypeRepositoryFake implements RoomTypeCatalogRepository
{
    /** @var array<int, RoomType> */
    public array $items;

    public function __construct()
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');
        $this->items = [
            1 => new RoomType(1, '<b>Deluxe</b>', 'deluxe', null, '4000.00', 2, 1, true, $timestamp, $timestamp),
            2 => new RoomType(2, 'Retired type', 'retired-type', null, '3000.00', 1, 0, false, $timestamp, $timestamp),
        ];
    }

    public function findById(int $id): ?RoomType
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }


    public function findAllActive(): array
    {
        return array_values(array_filter($this->items, static fn (RoomType $item): bool => $item->isActive()));
    }

    public function create(
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): RoomType {
        throw new LogicException('Not used by this test.');
    }

    public function update(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): ?RoomType {
        throw new LogicException('Not used by this test.');
    }

    public function setActive(int $id, bool $active): ?RoomType
    {
        throw new LogicException('Not used by this test.');
    }
}

final class RoomManagementAmenityRepositoryFake implements AmenityCatalogRepository
{
    /** @var array<int, Amenity> */
    public array $items;

    public function __construct()
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');
        $this->items = [
            1 => new Amenity(1, '<script>Wi-Fi</script>', 'wifi', true, $timestamp, $timestamp),
            2 => new Amenity(2, 'Old heater', 'heater', false, $timestamp, $timestamp),
        ];
    }

    public function findById(int $id): ?Amenity
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findAllActive(): array
    {
        return array_values(array_filter($this->items, static fn (Amenity $item): bool => $item->isActive()));
    }

    public function create(string $name, ?string $icon, bool $active): Amenity
    {
        throw new LogicException('Not used by this test.');
    }

    public function update(int $id, string $name, ?string $icon, bool $active): ?Amenity
    {
        throw new LogicException('Not used by this test.');
    }

    public function setActive(int $id, bool $active): ?Amenity
    {
        throw new LogicException('Not used by this test.');
    }
}

final class RoomManagementRoomRepositoryFake implements RoomCatalogRepository
{
    /** @var array<int, Room> */
    public array $items = [];

    /** @var array<int, list<int>> */
    public array $amenityAssignments = [];

    public int $createCalls = 0;

    public int $updateCalls = 0;

    public int $softDeleteCalls = 0;

    public ?RoomCatalogException $createFailure = null;

    public function __construct(private readonly RoomManagementRoomTypeRepositoryFake $roomTypes)
    {
    }

    public function findById(int $id): ?Room
    {
        $room = $this->items[$id] ?? null;

        return $room instanceof Room && !$room->isDeleted() ? $room : null;
    }

    public function lockCurrentById(int $roomId): ?Room
    {
        return $this->findById($roomId);
    }

    public function findAll(): array
    {
        $items = array_filter($this->items, static fn (Room $room): bool => !$room->isDeleted());
        ksort($items);

        return array_values($items);
    }

    public function findAllPublic(): array { return []; }
    public function findPublicBySlug(string $slug): ?Room { return null; }
    public function findActiveAmenitiesByRoomId(int $roomId): array { return []; }

    public function findAmenityIds(int $roomId): array
    {
        return $this->amenityAssignments[$roomId] ?? [];
    }

    public function create(
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $pricePerNight,
        int $capacityAdults,
        int $capacityChildren,
        string $operationalStatus,
        bool $published,
    ): Room {
        $this->createCalls++;

        if ($this->createFailure !== null) {
            throw $this->createFailure;
        }

        $roomType = $this->roomTypes->findById($roomTypeId);

        if (!$roomType instanceof RoomType) {
            throw RoomCatalogException::roomTypeNotFound();
        }

        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;
        $room = AdministratorRoomManagementTest::room(
            $id,
            $roomType,
            $roomNumber,
            $name,
            $slug,
            $description,
            $pricePerNight,
            $capacityAdults,
            $capacityChildren,
            $operationalStatus,
            $published,
        );
        $this->items[$id] = $room;

        return $room;
    }

    public function update(
        int $id,
        int $roomTypeId,
        string $roomNumber,
        string $name,
        string $slug,
        ?string $description,
        string $pricePerNight,
        int $capacityAdults,
        int $capacityChildren,
        string $operationalStatus,
        bool $published,
    ): ?Room {
        $this->updateCalls++;
        $existing = $this->findById($id);
        $roomType = $this->roomTypes->findById($roomTypeId);

        if (!$existing instanceof Room || !$roomType instanceof RoomType) {
            return null;
        }

        $updated = AdministratorRoomManagementTest::room(
            $id,
            $roomType,
            $roomNumber,
            $name,
            $slug,
            $description,
            $pricePerNight,
            $capacityAdults,
            $capacityChildren,
            $operationalStatus,
            $published,
        );
        $this->items[$id] = $updated;

        return $updated;
    }

    public function replaceAmenities(int $roomId, array $amenityIds): void
    {
        $this->amenityAssignments[$roomId] = $amenityIds;
    }

    public function softDelete(int $id): ?Room
    {
        $this->softDeleteCalls++;
        $existing = $this->findById($id);

        if (!$existing instanceof Room) {
            return null;
        }

        $deleted = AdministratorRoomManagementTest::room(
            $id,
            $existing->roomType(),
            $existing->roomNumber(),
            $existing->name(),
            $existing->slug(),
            $existing->description(),
            $existing->pricePerNight(),
            $existing->capacityAdults(),
            $existing->capacityChildren(),
            $existing->operationalStatus(),
            $existing->isPublished(),
            new DateTimeImmutable('2026-07-22 11:00:00 UTC'),
        );
        $this->items[$id] = $deleted;

        return $deleted;
    }
}

final class RoomManagementImageRepositoryFake implements RoomImageCatalogRepository
{
    /** @var list<RoomImage> */
    public array $items = [];

    public function countByRoomId(int $roomId): int { return count($this->items); }
    public function findAllByRoomId(int $roomId): array { return $this->items; }
    public function findPrimaryByRoomId(int $roomId): ?RoomImage { return null; }
    public function findByIdAndRoomId(int $imageId, int $roomId): ?RoomImage { return null; }
    public function create(int $roomId, string $filePath, ?string $altText, int $sortOrder, bool $primary): RoomImage { throw new LogicException(); }
    public function clearPrimaryByRoomId(int $roomId): void { throw new LogicException(); }
    public function updateMetadata(int $imageId, int $roomId, ?string $altText, int $sortOrder): ?RoomImage { throw new LogicException(); }
    public function setPrimary(int $imageId, int $roomId): ?RoomImage { throw new LogicException(); }
    public function delete(int $imageId, int $roomId): ?RoomImage { throw new LogicException(); }
}

final class RoomManagementImageStorageFake implements RoomImageStorage
{
    public function store(UploadedFile $file, string $detectedMediaType): StoredRoomImage { throw new LogicException(); }
    public function remove(string $relativePath): void { throw new LogicException(); }
}

final class RoomManagementTransactionFake implements RoomCatalogTransaction
{
    private bool $active = false;

    public function begin(): void
    {
        $this->active = true;
    }

    public function commit(): void
    {
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}

final class RoomManagementAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
