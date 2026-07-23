<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\AmenityCatalogRepository;
use App\Repositories\RoomCatalogRepository;
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
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorRoomCatalogReadPagesTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private AdministratorSession $administratorSession;

    private CsrfTokenManager $csrfTokens;

    private Application $application;

    private RoomTypeCatalogRepository $roomTypeRepository;

    private AmenityCatalogRepository $amenityRepository;

    private RoomCatalogRepository $roomRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-room-catalog-feature-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->roomTypeRepository = $this->createStub(RoomTypeCatalogRepository::class);
        $this->amenityRepository = $this->createStub(AmenityCatalogRepository::class);
        $this->roomRepository = $this->createStub(RoomCatalogRepository::class);
        $roomTransaction = $this->createStub(RoomCatalogTransaction::class);
        $logger = new NullLogger();
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new CatalogPageAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $catalogController = new AdministratorRoomCatalogController(
            $renderer,
            new RoomCatalogServiceProvider(
                fn (): RoomTypeCatalogService => new RoomTypeCatalogService($this->roomTypeRepository),
                fn (): AmenityCatalogService => new AmenityCatalogService($this->amenityRepository),
                fn (): RoomCatalogService => new RoomCatalogService(
                    $this->roomRepository,
                    $this->roomTypeRepository,
                    $this->amenityRepository,
                    $roomTransaction,
                ),
            ),
            $this->csrfTokens,
            new RoomCatalogFlashMessages($this->session),
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

    #[DataProvider('guestCataloguePaths')]
    public function testGuestCannotOpenRoomCataloguePages(string $path): void
    {
        $response = $this->application->handle(Request::create('GET', $path));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
    }

    /** @return iterable<string, array{string}> */
    public static function guestCataloguePaths(): iterable
    {
        yield 'rooms' => ['/admin/rooms'];
        yield 'room types' => ['/admin/room-types'];
        yield 'amenities' => ['/admin/amenities'];
    }

    public function testRoomsPageRendersEscapedOperationalAndPublicationData(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');
        $deluxe = new RoomType(
            1,
            '<b>Deluxe</b>',
            'deluxe',
            null,
            '6500.00',
            2,
            1,
            true,
            $timestamp,
            $timestamp,
        );
        $standard = new RoomType(
            2,
            'Standard',
            'standard',
            null,
            '3500.00',
            1,
            0,
            true,
            $timestamp,
            $timestamp,
        );
        $this->roomRepository->method('findAll')->willReturn([
            new Room(
                11,
                $deluxe,
                'A-101',
                '<script>Himalayan Suite</script>',
                'himalayan-suite',
                null,
                '7000.50',
                2,
                1,
                Room::STATUS_MAINTENANCE,
                true,
                $timestamp,
                $timestamp,
                null,
            ),
            new Room(
                12,
                $standard,
                'B-201',
                'Standard Room',
                'standard-room',
                null,
                '3800.00',
                1,
                0,
                Room::STATUS_ACTIVE,
                false,
                $timestamp,
                $timestamp,
                null,
            ),
        ]);
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('GET', '/admin/rooms'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Rooms | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('href="/admin/rooms"', $response->body());
        self::assertStringContainsString('aria-current="page"', $response->body());
        self::assertSame(1, substr_count($response->body(), 'aria-current="page"'));
        self::assertStringContainsString('&lt;script&gt;Himalayan Suite&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Himalayan Suite</script>', $response->body());
        self::assertStringContainsString('&lt;b&gt;Deluxe&lt;/b&gt;', $response->body());
        self::assertStringNotContainsString('<b>Deluxe</b>', $response->body());
        self::assertStringContainsString('NPR 7000.50', $response->body());
        self::assertStringContainsString('2 adults,', $response->body());
        self::assertStringContainsString('1 child', $response->body());
        self::assertStringContainsString('Maintenance', $response->body());
        self::assertStringContainsString('Published', $response->body());
        self::assertStringContainsString('Unpublished', $response->body());
        self::assertStringContainsString('href="/admin/rooms/create"', $response->body());
        self::assertStringContainsString('href="/admin/rooms/11/edit"', $response->body());
        self::assertStringContainsString('action="/admin/rooms/11/delete"', $response->body());
        self::assertStringContainsString('name="_csrf" value="' . $token . '"', $response->body());
    }

    public function testRoomTypesPageRendersEscapedDeterministicCatalogueData(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');
        $this->roomTypeRepository->method('findAll')->willReturn([
            new RoomType(1, '<script>Deluxe</script>', 'deluxe-suite', null, '6500.50', 2, 1, true, $timestamp, $timestamp),
            new RoomType(2, 'Standard', 'standard', null, '3500.00', 1, 0, false, $timestamp, $timestamp),
        ]);
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('GET', '/admin/room-types'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Room types | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('href="/admin/room-types"', $response->body());
        self::assertStringContainsString('aria-label="Management modules"', $response->body());
        self::assertStringNotContainsString('aria-label="Upcoming management modules"', $response->body());
        self::assertStringContainsString('aria-current="page"', $response->body());
        self::assertStringContainsString('&lt;script&gt;Deluxe&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Deluxe</script>', $response->body());
        self::assertStringContainsString('NPR 6500.50', $response->body());
        self::assertStringContainsString('2 adults,', $response->body());
        self::assertStringContainsString('1 child', $response->body());
        self::assertStringContainsString('Inactive', $response->body());
        self::assertStringContainsString('value="' . $token . '"', $response->body());
        self::assertSame(1, substr_count($response->body(), 'aria-current="page"'));
    }

    public function testAmenitiesPageRendersEscapedOptionalIconAndEmptyState(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');
        $this->amenityRepository->method('findAll')->willReturn([
            new Amenity(1, '<b>Airport transfer</b>', 'car-front', true, $timestamp, $timestamp),
            new Amenity(2, 'Breakfast', null, false, $timestamp, $timestamp),
        ]);
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('GET', '/admin/amenities'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Amenities | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('href="/admin/amenities"', $response->body());
        self::assertStringContainsString('href="/admin/amenities/create"', $response->body());
        self::assertStringContainsString('href="/admin/amenities/1/edit"', $response->body());
        self::assertStringContainsString('action="/admin/amenities/1/deactivate"', $response->body());
        self::assertStringContainsString('value="' . $token . '"', $response->body());
        self::assertStringContainsString('&lt;b&gt;Airport transfer&lt;/b&gt;', $response->body());
        self::assertStringNotContainsString('<b>Airport transfer</b>', $response->body());
        self::assertStringContainsString('<code>car-front</code>', $response->body());
        self::assertStringContainsString('Not set', $response->body());
        self::assertStringContainsString('Inactive', $response->body());
        self::assertSame(1, substr_count($response->body(), 'aria-current="page"'));
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

final class CatalogPageAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
