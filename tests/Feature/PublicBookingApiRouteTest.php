<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicBookingController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Models\Booking;
use App\Requests\GuestBookingRequest;
use App\Services\BookingAccessToken;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSubmitter;
use App\Services\PublicBookingRateLimiter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublicBookingApiRouteTest extends TestCase
{
    private string $savePath;

    private string $rateLimitPath;

    private Session $session;

    private CsrfTokenManager $tokens;

    private Router $router;

    private PublicBookingRouteSubmitterFake $submitter;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-booking-route-session-'
            . $suffix;
        $this->rateLimitPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-booking-route-limit-'
            . $suffix;
        self::assertTrue(mkdir($this->savePath, 0700));

        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->submitter = new PublicBookingRouteSubmitterFake();

        $controller = new PublicBookingController(
            new BookingSubmissionWorkflowProvider(
                fn (): BookingSubmissionWorkflow => new BookingSubmissionWorkflow(
                    $this->submitter,
                    new BookingAccessToken(str_repeat('k', 32)),
                ),
            ),
            new DateTimeZone('Asia/Kathmandu'),
        );
        $rateLimit = new PublicBookingRateLimitMiddleware(
            new PublicBookingRateLimiter($this->rateLimitPath, [
                'max_attempts' => 5,
                'attempt_window_seconds' => 900,
                'block_duration_seconds' => 900,
            ]),
        );

        $this->router = new Router();
        $registrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'api.php';
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
            $rateLimit,
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

        foreach ([$this->savePath, $this->rateLimitPath] as $directory) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($directory);
        }

        parent::tearDown();
    }

    public function testRouteIsPostOnlyAndCsrfRunsBeforeRateLimiting(): void
    {
        $wrongMethod = $this->router->dispatch(
            Request::create('GET', '/api/v1/bookings'),
        );
        self::assertSame(405, $wrongMethod->statusCode());
        self::assertSame('POST', $wrongMethod->headers()['Allow']);

        $missingToken = $this->router->dispatch(
            Request::create(
                'POST',
                '/api/v1/bookings',
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(self::payload(), JSON_THROW_ON_ERROR),
                remoteAddress: '203.0.113.10',
            ),
        );

        self::assertSame(403, $missingToken->statusCode());
        self::assertSame('{"message":"Forbidden."}', $missingToken->body());
        self::assertSame(0, $this->submitter->calls);
        self::assertSame(
            [],
            glob($this->rateLimitPath . DIRECTORY_SEPARATOR . '*.json') ?: [],
        );
    }

    public function testValidSubmissionIncludesRateLimitHeaders(): void
    {
        $response = $this->router->dispatch(
            $this->request('203.0.113.11'),
        );

        self::assertSame(201, $response->statusCode());
        self::assertSame('4', $response->headers()['X-RateLimit-Remaining']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(1, $this->submitter->calls);
    }

    public function testSixthCsrfValidAttemptIsBlockedBeforeSubmission(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            self::assertSame(
                201,
                $this->router
                    ->dispatch($this->request('203.0.113.12'))
                    ->statusCode(),
            );
        }

        $blocked = $this->router->dispatch(
            $this->request('203.0.113.12'),
        );

        self::assertSame(429, $blocked->statusCode());
        self::assertSame('0', $blocked->headers()['X-RateLimit-Remaining']);
        self::assertGreaterThan(0, (int) $blocked->headers()['Retry-After']);
        self::assertSame(5, $this->submitter->calls);
    }

    private function request(string $remoteAddress): Request
    {
        return Request::create(
            'POST',
            '/api/v1/bookings',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->tokens->token(),
            ],
            body: json_encode(self::payload(), JSON_THROW_ON_ERROR),
            remoteAddress: $remoteAddress,
        );
    }

    /** @return array<string, mixed> */
    private static function payload(): array
    {
        return [
            'room_id' => 12,
            'guest_full_name' => 'Asha Sharma',
            'guest_email' => 'asha@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-08-10',
            'check_out' => '2026-08-12',
            'adults' => 2,
            'children' => 0,
            'terms_accepted' => true,
        ];
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
            'save_path' => realpath($this->savePath),
            'gc_maxlifetime' => 43200,
        ];
    }
}

final class PublicBookingRouteSubmitterFake implements BookingSubmitter
{
    public int $calls = 0;

    public function submit(GuestBookingRequest $request): Booking
    {
        $this->calls++;
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
            null,
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
