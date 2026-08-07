<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicBookingPageController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Exceptions\BookingException;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Presentation\RoomImageUrl;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Requests\GuestBookingRequest;
use App\Services\BookingAccessToken;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSubmitter;
use App\Services\BookingSuccessFlash;
use App\Services\GuestPaymentContinuation;
use App\Services\PublicBookingRateLimiter;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublicBookingPageTest extends TestCase
{
    private string $sessionPath;

    private string $rateLimitPath;

    private Session $session;

    private CsrfTokenManager $tokens;

    private Router $router;

    private GuestPaymentContinuation $paymentContinuation;

    private PublicBookingPageSubmitterFake $submitter;

    private PublicBookingPageLoggerFake $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->sessionPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-booking-page-session-'
            . $suffix;
        $this->rateLimitPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-booking-page-limit-'
            . $suffix;
        self::assertTrue(mkdir($this->sessionPath, 0700));

        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->paymentContinuation = new GuestPaymentContinuation($this->session);
        $this->submitter = new PublicBookingPageSubmitterFake();
        $this->logger = new PublicBookingPageLoggerFake();
        $timezone = new DateTimeZone('Asia/Kathmandu');
        $bookingRoom = self::room();
        $rooms = $this->createStub(RoomCatalogRepository::class);
        $rooms->method('findPublicById')->willReturnCallback(
            static fn (int $roomId): ?Room => $roomId === $bookingRoom->id() ? $bookingRoom : null,
        );
        $rooms->method('findActiveAmenitiesByRoomId')->willReturn([]);
        $images = $this->createStub(RoomImageCatalogRepository::class);
        $images->method('findPrimaryByRoomId')->willReturn(null);
        $images->method('findAllByRoomId')->willReturn([]);
        $controller = new PublicBookingPageController(
            new ViewRenderer(
                dirname(__DIR__, 2)
                    . DIRECTORY_SEPARATOR
                    . 'resources'
                    . DIRECTORY_SEPARATOR
                    . 'views',
            ),
            new BookingSubmissionWorkflowProvider(
                fn (): BookingSubmissionWorkflow => new BookingSubmissionWorkflow(
                    $this->submitter,
                    new BookingAccessToken(str_repeat('k', 32)),
                ),
            ),
            new BookingSuccessFlash($this->session, $timezone),
            $this->paymentContinuation,
            $this->tokens,
            new PublicRoomCatalogServiceProvider(
                static fn (): PublicRoomCatalogService => new PublicRoomCatalogService(
                    $rooms,
                    $images,
                    new RoomImageUrl(),
                ),
            ),
            $this->logger,
        );

        $this->router = new Router();
        $registrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'web.php';
        $registrar(
            $this->router,
            new ViewRenderer(
                dirname(__DIR__, 2)
                    . DIRECTORY_SEPARATOR
                    . 'resources'
                    . DIRECTORY_SEPARATOR
                    . 'views',
            ),
            new CsrfMiddleware($this->tokens),
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->session->destroy();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_id('');
        }

        foreach ([$this->sessionPath, $this->rateLimitPath] as $directory) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($directory);
        }

        parent::tearDown();
    }

    public function testFormIsRenderedWithSafePrefillAndCsrfToken(): void
    {
        $response = $this->router->dispatch(
            Request::create(
                'GET',
                '/booking?room_id=12&check_in=2026-08-10&check_out=2026-08-12',
            ),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Tell us about your stay', $response->body());
        self::assertStringContainsString('Deluxe Garden Room', $response->body());
        self::assertStringContainsString('NPR 4500.00', $response->body());
        self::assertStringContainsString('type="hidden" name="room_id" value="12"', $response->body());
        self::assertStringNotContainsString('type="number" name="room_id"', $response->body());
        self::assertStringContainsString('value="2026-08-10"', $response->body());
        self::assertStringContainsString(
            'value="' . $this->tokens->token() . '"',
            $response->body(),
        );
    }

    public function testMissingOrIneligibleRoomSelectionShowsChooseRoomState(): void
    {
        $missing = $this->router->dispatch(Request::create('GET', '/booking'));
        $invalid = $this->router->dispatch(Request::create('GET', '/booking?room_id=999'));

        foreach ([$missing, $invalid] as $response) {
            self::assertSame(200, $response->statusCode());
            self::assertStringContainsString('Choose a room first', $response->body());
            self::assertStringNotContainsString('name="room_id"', $response->body());
            self::assertStringNotContainsString('Room ID', $response->body());
        }
    }

    public function testPostRequiresCsrfBeforeConsumingRateLimit(): void
    {
        $response = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking',
                input: self::input(),
                remoteAddress: '203.0.113.10',
            ),
        );

        self::assertSame(403, $response->statusCode());
        self::assertSame('Forbidden', $response->body());
        self::assertSame(0, $this->submitter->calls);
        self::assertSame(
            [],
            glob($this->rateLimitPath . DIRECTORY_SEPARATOR . '*.json') ?: [],
        );
    }

    public function testSuccessfulPostRedirectsAndSuccessDataIsOneTime(): void
    {
        $input = self::input();
        $input['_csrf'] = $this->tokens->token();

        $submitted = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking',
                input: $input,
                remoteAddress: '203.0.113.11',
            ),
        );

        self::assertSame(303, $submitted->statusCode());
        self::assertSame('/booking/success', $submitted->headers()['Location']);
        self::assertSame('4', $submitted->headers()['X-RateLimit-Remaining']);
        self::assertSame(1, $this->submitter->calls);

        $success = $this->router->dispatch(
            Request::create('GET', '/booking/success'),
        );

        self::assertSame(200, $success->statusCode());
        self::assertStringContainsString(
            'TGH-2026-REFERENCE00000000001',
            $success->body(),
        );
        self::assertStringContainsString('pending', $success->body());
        self::assertStringContainsString(
            '2026-08-01T23:45:00+05:45',
            $success->body(),
        );
        self::assertStringContainsString('NPR 9000.00', $success->body());
        self::assertStringContainsString(
            'No payment has been recorded.',
            $success->body(),
        );
        self::assertStringNotContainsString('asha@example.test', $success->body());
        self::assertStringNotContainsString('+9779800000000', $success->body());
        self::assertStringNotContainsString('v1.', $success->body());
        self::assertStringContainsString('/booking/payment', $success->body());
        $continuation = $this->paymentContinuation->current();
        self::assertNotNull($continuation);
        self::assertSame(
            'TGH-2026-REFERENCE00000000001',
            $continuation['booking_reference'],
        );
        self::assertMatchesRegularExpression(
            '/^v1\.[A-Za-z0-9_-]{43}$/D',
            $continuation['booking_access_token'],
        );

        $refresh = $this->router->dispatch(
            Request::create('GET', '/booking/success'),
        );

        self::assertSame(302, $refresh->statusCode());
        self::assertSame('/booking', $refresh->headers()['Location']);
    }

    public function testValidationAndConflictRenderTheFormWithoutRedirecting(): void
    {
        $invalid = self::input();
        $invalid['_csrf'] = $this->tokens->token();
        $invalid['guest_email'] = 'not-an-email';

        $validation = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking',
                input: $invalid,
                remoteAddress: '203.0.113.12',
            ),
        );

        self::assertSame(422, $validation->statusCode());
        self::assertStringContainsString(
            'not-an-email',
            $validation->body(),
        );
        self::assertStringContainsString('href="#guest_email"', $validation->body());
        self::assertStringContainsString('id="booking-form-error"', $validation->body());
        self::assertStringContainsString('aria-describedby="booking-form-error"', $validation->body());
        self::assertMatchesRegularExpression(
            '/id="guest_email"[^>]*aria-invalid="true"[^>]*aria-describedby="guest_email_error"/',
            $validation->body(),
        );
        self::assertStringContainsString('id="guest_email_error"', $validation->body());
        self::assertDoesNotMatchRegularExpression(
            '/id="guest_phone"[^>]*aria-invalid="true"/',
            $validation->body(),
        );
        self::assertStringContainsString('Deluxe Garden Room', $validation->body());

        $this->submitter->failure = BookingException::roomUnavailable();
        $conflictInput = self::input();
        $conflictInput['_csrf'] = $this->tokens->token();
        $conflict = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking',
                input: $conflictInput,
                remoteAddress: '203.0.113.13',
            ),
        );

        self::assertSame(409, $conflict->statusCode());
        self::assertStringContainsString(
            'not available',
            $conflict->body(),
        );
    }

    public function testUnexpectedFailureIsLoggedAndReturnsOnlySafePublicError(): void
    {
        $this->submitter->failure = BookingException::failure(
            new RuntimeException('SQLSTATE private C:\\storage\\secret'),
        );
        $input = self::input();
        $input['_csrf'] = $this->tokens->token();

        $response = $this->router->dispatch(Request::create(
            'POST',
            '/booking',
            input: $input,
            remoteAddress: '203.0.113.14',
        ));

        self::assertSame(500, $response->statusCode());
        self::assertSame('Internal Server Error', $response->body());
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('storage', $response->body());
        self::assertStringNotContainsString('secret', $response->body());
        self::assertCount(1, $this->logger->records);
        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertSame('Public booking submission failed.', $this->logger->records[0]['message']);
        self::assertInstanceOf(BookingException::class, $this->logger->records[0]['context']['exception']);
    }

    /** @return array<string, mixed> */
    private static function input(): array
    {
        return [
            'room_id' => '12',
            'guest_full_name' => 'Asha Sharma',
            'guest_email' => 'asha@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => '2',
            'children' => '0',
            'special_request' => 'Late arrival.',
            'terms_accepted' => '1',
        ];
    }

    private static function room(): Room
    {
        $timestamp = new DateTimeImmutable('2026-07-01 10:00:00 UTC');
        $type = new RoomType(1, 'Deluxe', 'deluxe', null, '4500.00', 2, 1, true, $timestamp, $timestamp);

        return new Room(
            12,
            $type,
            'D-12',
            'Deluxe Garden Room',
            'deluxe-garden-room',
            'A private garden-facing room.',
            '4500.00',
            2,
            1,
            Room::STATUS_ACTIVE,
            true,
            $timestamp,
            $timestamp,
            null,
        );
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
}

final class PublicBookingPageLoggerFake extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class PublicBookingPageSubmitterFake implements BookingSubmitter
{
    public int $calls = 0;

    public ?BookingException $failure = null;

    public function submit(GuestBookingRequest $request): Booking
    {
        $this->calls++;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        $createdAt = new DateTimeImmutable('2026-08-01 12:00:00 UTC');

        return new Booking(
            99,
            'TGH-2026-REFERENCE00000000001',
            null,
            $request->guestFullName(),
            $request->guestEmail(),
            $request->guestPhone(),
            $request->stayPeriod()->checkIn(),
            $request->stayPeriod()->checkOut(),
            $request->adults(),
            $request->children(),
            Booking::CURRENCY_NPR,
            '9000.00',
            '9000.00',
            Booking::STATUS_PENDING,
            $request->specialRequest(),
            $createdAt,
            null,
            new DateTimeImmutable('2026-08-01 18:00:00 UTC'),
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
}
