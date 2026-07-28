<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorBookingController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingRoom;
use App\Repositories\BookingCatalogRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorBookingService;
use App\Services\AdministratorBookingServiceProvider;
use App\Services\AdministratorBookingStatusServiceProvider;
use App\Services\AdministratorLogoutService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorBookingReadPagesTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private AdministratorSession $administratorSession;

    private CsrfTokenManager $csrfTokens;

    private Router $router;

    private BookingCatalogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-booking-pages-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession(
            $this->session,
            $this->csrfTokens,
        );
        $this->repository = $this->createStub(
            BookingCatalogRepository::class,
        );
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR
                . 'resources'
                . DIRECTORY_SEPARATOR
                . 'views',
        );
        $logger = new NullLogger();
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new BookingPageAuthenticatorFake(),
            new AdministratorLogoutService(
                $this->administratorSession,
                $logger,
            ),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $bookingController = new AdministratorBookingController(
            $renderer,
            new AdministratorBookingServiceProvider(
                fn (): AdministratorBookingService => new AdministratorBookingService(
                    $this->repository,
                ),
            ),
            new AdministratorBookingStatusServiceProvider(
                static fn (): mixed => throw new \LogicException(
                    'Status service must remain lazy during booking reads.',
                ),
            ),
            $this->administratorSession,
            $this->csrfTokens,
            new DateTimeZone('Asia/Kathmandu'),
        );

        $this->router = new Router();
        $registrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'admin.php';
        $registrar(
            $this->router,
            $renderer,
            $authenticationController,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware(
                $this->administratorSession,
            ),
            new CsrfMiddleware($this->csrfTokens),
            null,
            null,
            null,
            null,
            null,
            $bookingController,
        );
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
                @unlink($file);
            }
        }

        @rmdir($this->savePath);

        parent::tearDown();
    }

    #[DataProvider('guestPaths')]
    public function testGuestCannotOpenBookingPages(string $path): void
    {
        $response = $this->router->dispatch(
            Request::create('GET', $path),
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame(
            '/admin/login',
            $response->headers()['Location'],
        );
        self::assertSame(
            'no-store',
            $response->headers()['Cache-Control'],
        );
    }

    /** @return iterable<string, array{string}> */
    public static function guestPaths(): iterable
    {
        yield 'list' => ['/admin/bookings'];
        yield 'detail' => ['/admin/bookings/31'];
    }

    public function testListRendersEscapedBookingDataAndCanonicalFilter(): void
    {
        $this->repository->method('findAll')->willReturn([
            self::booking(),
        ]);
        $this->administratorSession->establish(
            new AdministratorIdentity(91),
        );

        $response = $this->router->dispatch(
            Request::create(
                'GET',
                '/admin/bookings?status=pending',
            ),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            'no-store',
            $response->headers()['Cache-Control'],
        );
        self::assertStringContainsString(
            '<title>Bookings | Tara Guest House</title>',
            $response->body(),
        );
        self::assertStringContainsString(
            'href="/admin/bookings"',
            $response->body(),
        );
        self::assertSame(
            1,
            substr_count($response->body(), 'aria-current="page"'),
        );
        self::assertStringContainsString(
            '&lt;script&gt;Guest Example&lt;/script&gt;',
            $response->body(),
        );
        self::assertStringNotContainsString(
            '<script>Guest Example</script>',
            $response->body(),
        );
        self::assertStringContainsString(
            'TGH-2026-ADMINREAD00000001',
            $response->body(),
        );
        self::assertStringContainsString(
            'NPR 18000.00',
            $response->body(),
        );
        self::assertStringContainsString(
            'value="pending"',
            $response->body(),
        );
        self::assertSame(
            1,
            substr_count($response->body(), 'selected'),
        );
        self::assertStringContainsString(
            'value="checked_in"',
            $response->body(),
        );
        self::assertStringContainsString(
            'href="/admin/bookings/31"',
            $response->body(),
        );
    }

    public function testInvalidStatusFilterReturnsEscaped422Page(): void
    {
        $this->administratorSession->establish(
            new AdministratorIdentity(91),
        );

        $response = $this->router->dispatch(
            Request::create(
                'GET',
                '/admin/bookings?status=completed',
            ),
        );

        self::assertSame(422, $response->statusCode());
        self::assertSame(
            'no-store',
            $response->headers()['Cache-Control'],
        );
        self::assertStringContainsString(
            'Booking status filter is invalid.',
            $response->body(),
        );
        self::assertStringNotContainsString(
            'value="completed"',
            $response->body(),
        );
    }

    public function testDetailRendersBookingRoomPolicyAndEventSnapshots(): void
    {
        $this->repository->method('findById')->willReturn(
            self::booking(),
        );
        $this->repository->method('findRoomByBookingId')->willReturn(
            self::room(),
        );
        $this->repository->method('findEventsByBookingId')->willReturn([
            self::event(),
        ]);
        $this->administratorSession->establish(
            new AdministratorIdentity(91),
        );

        $response = $this->router->dispatch(
            Request::create('GET', '/admin/bookings/31'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            'no-store',
            $response->headers()['Cache-Control'],
        );
        self::assertStringContainsString(
            'TGH-2026-ADMINREAD00000001',
            $response->body(),
        );
        self::assertStringContainsString(
            '&lt;script&gt;Guest Example&lt;/script&gt;',
            $response->body(),
        );
        self::assertStringContainsString(
            'guest@example.test',
            $response->body(),
        );
        self::assertStringContainsString(
            '+9779812345678',
            $response->body(),
        );
        self::assertStringContainsString(
            '#12',
            $response->body(),
        );
        self::assertStringContainsString(
            'NPR 6000.00',
            $response->body(),
        );
        self::assertStringContainsString(
            'NPR 18000.00',
            $response->body(),
        );
        self::assertStringContainsString(
            '2026-08-01 21:45',
            $response->body(),
        );
        self::assertStringContainsString(
            'Booking Created',
            $response->body(),
        );
        self::assertStringContainsString(
            'New',
            $response->body(),
        );
        self::assertStringContainsString(
            'Pending',
            $response->body(),
        );
        self::assertStringContainsString(
            'action="/api/v1/admin/bookings/31/status"',
            $response->body(),
        );
        self::assertStringContainsString(
            'data-target-status="rejected"',
            $response->body(),
        );
        self::assertStringContainsString(
            'data-target-status="cancelled"',
            $response->body(),
        );
        self::assertStringNotContainsString(
            'data-target-status="confirmed"',
            $response->body(),
        );
        self::assertStringNotContainsString(
            'data-target-status="checked_in"',
            $response->body(),
        );
    }

    #[DataProvider('statusActionPages')]
    public function testDetailShowsOnlyPermittedStatusActions(
        string $currentStatus,
        array $expectedTargets,
    ): void {
        $this->repository->method('findById')->willReturn(
            self::booking($currentStatus),
        );
        $this->repository->method('findRoomByBookingId')->willReturn(
            self::room(),
        );
        $this->repository->method('findEventsByBookingId')->willReturn([]);
        $this->administratorSession->establish(
            new AdministratorIdentity(91),
        );

        $response = $this->router->dispatch(
            Request::create('GET', '/admin/bookings/31'),
        );

        self::assertSame(200, $response->statusCode());

        foreach ([
            Booking::STATUS_REJECTED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_CHECKED_IN,
            Booking::STATUS_CHECKED_OUT,
        ] as $target) {
            $needle = 'data-target-status="' . $target . '"';

            if (in_array($target, $expectedTargets, true)) {
                self::assertStringContainsString($needle, $response->body());
            } else {
                self::assertStringNotContainsString($needle, $response->body());
            }
        }

        self::assertStringNotContainsString(
            'data-target-status="confirmed"',
            $response->body(),
        );
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function statusActionPages(): iterable
    {
        yield 'pending' => [
            Booking::STATUS_PENDING,
            [Booking::STATUS_REJECTED, Booking::STATUS_CANCELLED],
        ];
        yield 'confirmed' => [
            Booking::STATUS_CONFIRMED,
            [Booking::STATUS_CHECKED_IN, Booking::STATUS_CANCELLED],
        ];
        yield 'checked in' => [
            Booking::STATUS_CHECKED_IN,
            [Booking::STATUS_CHECKED_OUT],
        ];
        yield 'checked out terminal' => [Booking::STATUS_CHECKED_OUT, []];
        yield 'cancelled terminal' => [Booking::STATUS_CANCELLED, []];
        yield 'rejected terminal' => [Booking::STATUS_REJECTED, []];
    }

    #[DataProvider('missingDetailPaths')]
    public function testMalformedOrMissingDetailReturns404(
        string $path,
    ): void {
        $this->administratorSession->establish(
            new AdministratorIdentity(91),
        );

        $response = $this->router->dispatch(
            Request::create('GET', $path),
        );

        self::assertSame(404, $response->statusCode());
        self::assertSame('Not Found', $response->body());
        self::assertSame(
            'no-store',
            $response->headers()['Cache-Control'],
        );
    }

    /** @return iterable<string, array{string}> */
    public static function missingDetailPaths(): iterable
    {
        yield 'zero' => ['/admin/bookings/0'];
        yield 'non numeric' => ['/admin/bookings/not-a-number'];
        yield 'missing' => ['/admin/bookings/999'];
    }

    private static function booking(
        string $status = Booking::STATUS_PENDING,
    ): Booking
    {
        $createdAt = new DateTimeImmutable(
            '2026-08-01 10:00:00 UTC',
        );

        return new Booking(
            31,
            'TGH-2026-ADMINREAD00000001',
            null,
            '<script>Guest Example</script>',
            'guest@example.test',
            '+9779812345678',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            2,
            1,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            $status,
            'Late arrival.',
            $createdAt,
            'v1',
            new DateTimeImmutable('2026-08-01 16:00:00 UTC'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
            null,
        );
    }

    private static function room(): BookingRoom
    {
        return new BookingRoom(
            41,
            31,
            12,
            '6000.00',
            3,
            '18000.00',
            new DateTimeImmutable('2026-08-01 10:00:00 UTC'),
        );
    }

    private static function event(): BookingEvent
    {
        return new BookingEvent(
            51,
            31,
            'booking_created',
            null,
            Booking::STATUS_PENDING,
            null,
            'Guest requested a quiet room.',
            '{"source":"guest"}',
            new DateTimeImmutable('2026-08-01 10:05:00 UTC'),
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

final class BookingPageAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
