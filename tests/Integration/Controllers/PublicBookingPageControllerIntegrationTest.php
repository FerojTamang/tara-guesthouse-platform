<?php

declare(strict_types=1);

namespace Tests\Integration\Controllers;

use App\Controllers\PublicBookingPageController;
use App\Core\CsrfTokenManager;
use App\Core\MigrationRunner;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Models\Booking;
use App\Models\Room;
use App\Presentation\RoomImageUrl;
use App\Repositories\PdoAvailabilityRepository;
use App\Repositories\PdoBookingWriteRepository;
use App\Repositories\RoomImageRepository;
use App\Repositories\RoomRepository;
use App\Services\BookingAccessToken;
use App\Services\BookingCreationService;
use App\Services\BookingDeadlinePolicy;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingService;
use App\Services\BookingSubmissionService;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSuccessFlash;
use App\Services\GuestPaymentContinuation;
use App\Services\NullTransactionalMailDispatcher;
use App\Services\PdoBookingTransaction;
use App\Services\PublicBookingRateLimiter;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublicBookingPageControllerIntegrationTest extends DatabaseTestCase
{
    private string $sessionPath;

    private string $rateLimitPath;

    private Session $session;

    private CsrfTokenManager $csrfTokens;

    private Router $router;

    private int $roomId;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();

        $suffix = bin2hex(random_bytes(8));
        $this->sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-booking-integration-session-' . $suffix;
        $this->rateLimitPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-booking-integration-limit-' . $suffix;
        self::assertTrue(mkdir($this->sessionPath, 0700));

        $this->roomId = $this->insertRoom($this->insertRoomType());
        $this->session = Session::start($this->sessionConfiguration());
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $paymentContinuation = new GuestPaymentContinuation($this->session);
        $rooms = new RoomRepository($this->connection);
        $images = new RoomImageRepository($this->connection);
        $acceptedAt = self::utc('2026-08-02 03:00:00');

        $workflowProvider = new BookingSubmissionWorkflowProvider(
            function () use ($rooms, $acceptedAt): BookingSubmissionWorkflow {
                return new BookingSubmissionWorkflow(
                    new BookingSubmissionService(
                        new BookingCreationService(
                            $rooms,
                            new BookingService(
                                $rooms,
                                new PdoAvailabilityRepository($this->connection),
                                static fn (): DateTimeImmutable => $acceptedAt,
                            ),
                            new PdoBookingWriteRepository($this->connection),
                            new PdoBookingTransaction($this->connection),
                        ),
                        new BookingReferenceGenerator(
                            static fn (int $length): string => str_repeat("\x2a", $length),
                        ),
                        new BookingDeadlinePolicy(360),
                        static fn (): DateTimeImmutable => $acceptedAt,
                    ),
                    new BookingAccessToken(str_repeat('integration-key-', 3)),
                    new NullTransactionalMailDispatcher(),
                );
            },
        );
        $roomCatalog = new PublicRoomCatalogServiceProvider(
            static fn (): PublicRoomCatalogService => new PublicRoomCatalogService(
                $rooms,
                $images,
                new RoomImageUrl(),
            ),
        );
        $controller = new PublicBookingPageController(
            new ViewRenderer(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
            $workflowProvider,
            new BookingSuccessFlash($this->session, new DateTimeZone('Asia/Kathmandu')),
            $paymentContinuation,
            $this->csrfTokens,
            $roomCatalog,
        );

        $this->router = new Router();
        $routes = require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $routes(
            $this->router,
            new ViewRenderer(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
            new CsrfMiddleware($this->csrfTokens),
            $controller,
            new PublicBookingRateLimitMiddleware(
                new PublicBookingRateLimiter($this->rateLimitPath, [
                    'max_attempts' => 5,
                    'attempt_window_seconds' => 900,
                    'block_duration_seconds' => 900,
                ]),
            ),
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->session) && session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }

        foreach ([$this->sessionPath ?? '', $this->rateLimitPath ?? ''] as $directory) {
            if ($directory === '') {
                continue;
            }

            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($directory);
        }

        parent::tearDown();
    }

    public function testBrowserEquivalentGuestBookingSubmissionCreatesPendingBookingAndRedirects(): void
    {
        $form = $this->router->dispatch(Request::create(
            'GET',
            '/booking?room_id=' . $this->roomId . '&check_in=2026-08-10&check_out=2026-08-12&adults=2&children=0',
        ));

        self::assertSame(200, $form->statusCode());
        self::assertStringContainsString('method="post" action="/booking"', $form->body());
        self::assertStringContainsString('name="terms_accepted"', $form->body());
        self::assertMatchesRegularExpression('/name="_csrf" value="[a-f0-9]{64}"/D', $form->body());

        $response = $this->router->dispatch(Request::create(
            'POST',
            '/booking',
            input: [
                '_csrf' => $this->csrfTokens->token(),
                'room_id' => (string) $this->roomId,
                'guest_full_name' => 'Browser Test Guest',
                'guest_email' => 'browser-booking@example.test',
                'guest_phone' => '+9779800000000',
                'check_in' => '2026-08-10',
                'check_out' => '2026-08-12',
                'adults' => '2',
                'children' => '0',
                'special_request' => 'Browser-equivalent isolated test.',
                'terms_accepted' => '1',
            ],
            remoteAddress: '203.0.113.80',
        ));

        self::assertNotSame(500, $response->statusCode());
        self::assertSame(303, $response->statusCode());
        self::assertSame('/booking/success', $response->headers()['Location']);

        $booking = $this->connection->query(
            'SELECT id, user_id, booking_status, check_in, check_out, adults, children, currency, total_amount '
            . 'FROM bookings',
        )->fetch();
        self::assertIsArray($booking);
        self::assertNull($booking['user_id']);
        self::assertSame(Booking::STATUS_PENDING, $booking['booking_status']);
        self::assertSame('2026-08-10', $booking['check_in']);
        self::assertSame('2026-08-12', $booking['check_out']);
        self::assertSame(2, (int) $booking['adults']);
        self::assertSame(0, (int) $booking['children']);
        self::assertSame(Booking::CURRENCY_NPR, $booking['currency']);
        self::assertSame('9000.00', $booking['total_amount']);

        $room = $this->connection->query(
            'SELECT room_id, price_per_night, nights, subtotal FROM booking_rooms',
        )->fetch();
        self::assertIsArray($room);
        self::assertSame($this->roomId, (int) $room['room_id']);
        self::assertSame('4500.00', $room['price_per_night']);
        self::assertSame(2, (int) $room['nights']);
        self::assertSame('9000.00', $room['subtotal']);
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM bookings')->fetchColumn());

        $success = $this->router->dispatch(Request::create('GET', '/booking/success'));
        self::assertSame(200, $success->statusCode());
        self::assertStringContainsString('NPR 9000.00', $success->body());
        self::assertStringContainsString('pending', $success->body());
        self::assertStringNotContainsString('browser-booking@example.test', $success->body());

        $refresh = $this->router->dispatch(Request::create('GET', '/booking/success'));
        self::assertSame(302, $refresh->statusCode());
        self::assertSame('/booking', $refresh->headers()['Location']);
    }

    private function insertRoomType(): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO room_types (name, slug, description, base_price, capacity_adults, capacity_children, is_active) '
            . 'VALUES (:name, :slug, NULL, :base_price, :capacity_adults, :capacity_children, 1)',
        );
        $statement->execute([
            'name' => 'Browser Booking Type',
            'slug' => 'browser-booking-type',
            'base_price' => '4500.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertRoom(int $roomTypeId): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO rooms (room_type_id, room_number, name, slug, description, price_per_night, '
            . 'capacity_adults, capacity_children, operational_status, is_published) VALUES '
            . '(:room_type_id, :room_number, :name, :slug, :description, :price_per_night, '
            . ':capacity_adults, :capacity_children, :operational_status, 1)',
        );
        $statement->execute([
            'room_type_id' => $roomTypeId,
            'room_number' => 'BB-101',
            'name' => 'Browser Booking Room',
            'slug' => 'browser-booking-room',
            'description' => 'Isolated browser booking fixture.',
            'price_per_night' => '4500.00',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'operational_status' => Room::STATUS_ACTIVE,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function sessionConfiguration(): array
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
            'save_path' => realpath($this->sessionPath),
            'gc_maxlifetime' => 43200,
        ];
    }

    private static function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}
