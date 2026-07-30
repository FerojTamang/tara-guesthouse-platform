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
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingStatusSnapshot;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\BookingStatusWriteRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorBookingService;
use App\Services\AdministratorBookingServiceProvider;
use App\Services\AdministratorBookingStatusService;
use App\Services\AdministratorBookingStatusServiceProvider;
use App\Services\AdministratorLogoutService;
use App\Services\BookingTransaction;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorBookingStatusApiTest extends TestCase
{
    private string $savePath;

    private Session $session;

    private CsrfTokenManager $csrfTokens;

    private AdministratorSession $administratorSession;

    private Router $router;

    private BookingStatusApiRepositoryFake $bookings;

    private BookingStatusApiAuditFake $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-booking-status-api-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession(
            $this->session,
            $this->csrfTokens,
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
            new BookingStatusApiAuthenticatorFake(),
            new AdministratorLogoutService(
                $this->administratorSession,
                $logger,
            ),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $this->bookings = new BookingStatusApiRepositoryFake();
        $this->audit = new BookingStatusApiAuditFake();
        $transaction = new BookingStatusApiTransactionFake();
        $readRepository = $this->createStub(
            BookingCatalogRepository::class,
        );
        $bookingController = new AdministratorBookingController(
            $renderer,
            new AdministratorBookingServiceProvider(
                static fn (): AdministratorBookingService => new AdministratorBookingService(
                    $readRepository,
                ),
            ),
            new AdministratorBookingStatusServiceProvider(
                fn (): AdministratorBookingStatusService => new AdministratorBookingStatusService(
                    $this->bookings,
                    $this->audit,
                    $transaction,
                    static fn (): DateTimeImmutable => new DateTimeImmutable(
                        '2026-08-01 12:00:00 UTC',
                    ),
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
            . 'api.php';
        $registrar(
            $this->router,
            $renderer,
            $authenticationController,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware(
                $this->administratorSession,
            ),
            new CsrfMiddleware($this->csrfTokens),
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

    public function testGuestIsRejectedBeforeCsrfAndStatusService(): void
    {
        $response = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/bookings/31/status',
            headers: ['Content-Type' => 'application/json'],
            body: self::cancelJson(),
        ));

        self::assertSame(401, $response->statusCode());
        self::assertSame(
            '{"message":"Authentication required."}',
            $response->body(),
        );
        self::assertNull($this->bookings->lockedBookingId);
    }

    public function testAuthenticatedRequestRequiresCsrfBeforeStatusService(): void
    {
        $this->authenticate();
        $response = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/bookings/31/status',
            headers: ['Content-Type' => 'application/json'],
            body: self::cancelJson(),
        ));

        self::assertSame(403, $response->statusCode());
        self::assertSame('{"message":"Forbidden."}', $response->body());
        self::assertNull($this->bookings->lockedBookingId);
    }

    public function testRouteIsPatchOnly(): void
    {
        $response = $this->router->dispatch(
            Request::create('GET', '/api/v1/admin/bookings/31/status'),
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('PATCH', $response->headers()['Allow']);
    }

    public function testSuccessfulTransitionReturnsNarrowNoStoreContract(): void
    {
        $this->authenticate();
        $this->bookings->current = new BookingStatusSnapshot(
            31,
            'TGH-2026-STATUSAPI000000001',
            Booking::STATUS_PENDING,
        );
        $response = $this->router->dispatch($this->request(
            self::cancelJson(),
            headers: ['User-Agent' => "  Tara\tStatus Agent  "],
            remoteAddress: '203.0.113.50',
        ));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'message' => 'Booking status updated.',
            'data' => [
                'booking_id' => 31,
                'booking_reference' => 'TGH-2026-STATUSAPI000000001',
                'previous_status' => Booking::STATUS_PENDING,
                'booking_status' => Booking::STATUS_CANCELLED,
                'changed_at' => '2026-08-01T17:45:00+05:45',
            ],
        ], json_decode(
            $response->body(),
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame(Booking::STATUS_CANCELLED, $this->bookings->updatedStatus);
        self::assertSame('Guest requested cancellation.', $this->bookings->updatedReason);
        self::assertNotNull($this->audit->entry);
        self::assertSame(91, $this->audit->entry->userId());
        self::assertSame('203.0.113.50', $this->audit->entry->ipAddress());
        self::assertSame('Tara Status Agent', $this->audit->entry->userAgent());
        self::assertStringNotContainsString('guest_email', $response->body());
        self::assertStringNotContainsString('guest_phone', $response->body());
    }

    public function testContentTypeAndMalformedBodiesFailWithoutStatusWrite(): void
    {
        $this->authenticate();
        $unsupported = $this->router->dispatch($this->request(
            self::cancelJson(),
            contentType: 'text/plain',
        ));
        self::assertSame(415, $unsupported->statusCode());

        $malformed = $this->router->dispatch($this->request('{'));
        self::assertSame(400, $malformed->statusCode());

        $list = $this->router->dispatch($this->request('[]'));
        self::assertSame(400, $list->statusCode());
        self::assertNull($this->bookings->lockedBookingId);
    }

    public function testInvalidPayloadsReturn422WithoutStatusWrite(): void
    {
        $this->authenticate();
        $payloads = [
            '{}',
            '{"status":"confirmed"}',
            '{"status":"cancelled"}',
            '{"status":"checked_in","reason":"not accepted"}',
            '{"status":"cancelled","reason":"valid","user_id":91}',
        ];

        foreach ($payloads as $payload) {
            $response = $this->router->dispatch($this->request($payload));
            self::assertSame(422, $response->statusCode());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
        }

        self::assertNull($this->bookings->lockedBookingId);
    }

    public function testMissingAndForbiddenCurrentStateReturn404And409(): void
    {
        $this->authenticate();
        $missing = $this->router->dispatch($this->request(self::cancelJson()));
        self::assertSame(404, $missing->statusCode());
        self::assertSame(
            '{"success":false,"message":"The booking was not found."}',
            $missing->body(),
        );

        $this->bookings->current = new BookingStatusSnapshot(
            31,
            'TGH-2026-STATUSAPI000000001',
            Booking::STATUS_CHECKED_OUT,
        );
        $conflict = $this->router->dispatch($this->request(self::cancelJson()));
        self::assertSame(409, $conflict->statusCode());
        self::assertStringContainsString(
            'transition is not allowed',
            $conflict->body(),
        );
    }

    public function testInfrastructureFailureReturnsSanitized500(): void
    {
        $this->authenticate();
        $this->bookings->current = new BookingStatusSnapshot(
            31,
            'TGH-2026-STATUSAPI000000001',
            Booking::STATUS_PENDING,
        );
        $this->audit->failure = new RuntimeException(
            'SQLSTATE private audit failure',
        );
        $response = $this->router->dispatch($this->request(self::cancelJson()));

        self::assertSame(500, $response->statusCode());
        self::assertSame(
            '{"success":false,"message":"Internal Server Error."}',
            $response->body(),
        );
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('private', $response->body());
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(
            new AdministratorIdentity(91),
        );
    }

    /** @param array<string, string> $headers */
    private function request(
        string $body,
        array $headers = [],
        ?string $remoteAddress = null,
        string $contentType = 'application/json',
    ): Request {
        return Request::create(
            'PATCH',
            '/api/v1/admin/bookings/31/status',
            headers: [
                'Content-Type' => $contentType,
                'X-CSRF-Token' => $this->csrfTokens->token(),
                ...$headers,
            ],
            body: $body,
            remoteAddress: $remoteAddress,
        );
    }

    private static function cancelJson(): string
    {
        return json_encode([
            'status' => Booking::STATUS_CANCELLED,
            'reason' => 'Guest requested cancellation.',
        ], JSON_THROW_ON_ERROR);
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

final class BookingStatusApiAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}

final class BookingStatusApiRepositoryFake implements BookingStatusWriteRepository
{
    public ?BookingStatusSnapshot $current = null;

    public ?int $lockedBookingId = null;

    public ?string $updatedStatus = null;

    public ?string $updatedReason = null;

    public function lockCurrent(int $bookingId): ?BookingStatusSnapshot
    {
        $this->lockedBookingId = $bookingId;

        return $this->current;
    }

    public function updateStatus(
        BookingStatusSnapshot $current,
        string $targetStatus,
        ?string $reason,
        DateTimeImmutable $changedAt,
    ): void {
        $this->updatedStatus = $targetStatus;
        $this->updatedReason = $reason;
    }

    public function appendEvent(
        int $bookingId,
        string $eventType,
        string $fromStatus,
        string $toStatus,
        int $performedBy,
        ?string $reason,
        DateTimeImmutable $createdAt,
        ?string $metadataJson = null,
    ): void {
    }
}

final class BookingStatusApiAuditFake implements AuditLogWriteRepository
{
    public ?AuditLogEntry $entry = null;

    public ?RuntimeException $failure = null;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->entry = $entry;
    }
}

final class BookingStatusApiTransactionFake implements BookingTransaction
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
