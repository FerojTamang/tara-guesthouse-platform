<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorReviewController;
use App\Core\CsrfTokenManager;
use App\Core\Environment;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Repositories\AdministratorReviewRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\AdministratorReviewService;
use App\Services\AdministratorReviewServiceProvider;
use App\Services\ReviewModerationPolicyProvider;
use App\Services\ReviewTransaction;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorReviewModerationTest extends TestCase
{
    private string $sessionPath;
    private Session $session;
    private CsrfTokenManager $tokens;
    private AdministratorSession $administratorSession;
    private Router $router;
    private ViewRenderer $renderer;
    private AdministratorReviewServiceProvider $services;
    private FeatureAdministratorReviewRepository $reviews;
    private FeatureAdministratorReviewAuditRepository $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-review-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->sessionPath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->tokens);
        $this->renderer = new ViewRenderer(
            dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'resources'
            . DIRECTORY_SEPARATOR
            . 'views',
        );
        $logger = new NullLogger();
        $authenticationController = new AdministratorAuthenticationController(
            $this->renderer,
            new FeatureAdministratorReviewAuthenticator(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->tokens,
            $logger,
            self::loginPolicy(),
        );
        $this->reviews = new FeatureAdministratorReviewRepository();
        $this->reviews->items[11] = self::review();
        $this->audit = new FeatureAdministratorReviewAuditRepository();
        $service = new AdministratorReviewService(
            $this->reviews,
            new FeatureAdministratorReviewBookingRepository(self::booking()),
            new FeatureAdministratorReviewInvitationRepository(self::invitation()),
            $this->audit,
            new FeatureAdministratorReviewTransaction(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-02 02:00:00 UTC'),
        );
        $this->services = new AdministratorReviewServiceProvider(
            static fn (): AdministratorReviewService => $service,
        );
        $controller = new AdministratorReviewController(
            $this->renderer,
            $this->services,
            new ReviewModerationPolicyProvider(
                dirname(__DIR__, 2),
                Environment::fromArray([
                    'REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '1000',
                ]),
            ),
            $this->administratorSession,
            $this->tokens,
            new DateTimeZone('Asia/Kathmandu'),
        );
        $guestOnly = new GuestOnlyMiddleware($this->administratorSession);
        $requireAdministrator = new RequireAdministratorMiddleware($this->administratorSession);
        $csrf = new CsrfMiddleware($this->tokens);
        $this->router = new Router();

        $admin = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'admin.php';
        $admin(
            $this->router,
            $this->renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            reviewController: $controller,
        );

        $api = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'api.php';
        $api(
            $this->router,
            $this->renderer,
            $authenticationController,
            $guestOnly,
            $requireAdministrator,
            $csrf,
            $controller,
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
        foreach (glob($this->sessionPath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->sessionPath);
        parent::tearDown();
    }

    public function testGuestCannotReadOrModerateReviews(): void
    {
        $page = $this->router->dispatch(Request::create('GET', '/admin/reviews'));
        self::assertSame(302, $page->statusCode());
        self::assertSame('/admin/login', $page->headers()['Location']);

        $api = $this->router->dispatch(Request::create('GET', '/api/v1/admin/reviews'));
        self::assertSame(401, $api->statusCode());

        $mutation = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/reviews/11/status',
            headers: ['Content-Type' => 'application/json'],
            body: '{"review_status":"approved"}',
        ));
        self::assertSame(401, $mutation->statusCode());
        self::assertSame(Review::STATUS_PENDING, $this->reviews->items[11]->status());
    }

    public function testAuthenticatedListAndDetailEscapeGuestContentAndShowPendingOnlyControls(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $list = $this->router->dispatch(Request::create('GET', '/admin/reviews?status=pending'));
        self::assertSame(200, $list->statusCode());
        self::assertSame(Review::STATUS_PENDING, $this->reviews->lastStatus);
        self::assertStringContainsString('&lt;script&gt;Verified Guest&lt;/script&gt;', $list->body());
        self::assertStringNotContainsString('<script>Verified Guest</script>', $list->body());
        self::assertStringContainsString('href="/admin/reviews/11"', $list->body());
        self::assertStringContainsString('href="/admin/reviews"', $list->body());

        $detail = $this->router->dispatch(Request::create('GET', '/admin/reviews/11'));
        self::assertSame(200, $detail->statusCode());
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $detail->body());
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $detail->body());
        self::assertStringContainsString('data-review-status="approved"', $detail->body());
        self::assertStringContainsString('data-review-status="rejected"', $detail->body());
        self::assertStringContainsString('maxlength="1000"', $detail->body());
        self::assertStringContainsString('TGH-REVIEW-000023', $detail->body());
        self::assertStringNotContainsString(self::invitation()->tokenHash(), $detail->body());
        self::assertStringNotContainsString('data-review-delete', $detail->body());
        self::assertStringNotContainsString('data-review-reply', $detail->body());
        self::assertStringNotContainsString('/api/v1/admin/reviews/11/delete', $detail->body());

        $unsupportedDelete = $this->router->dispatch(
            Request::create('DELETE', '/api/v1/admin/reviews/11'),
        );
        self::assertSame(405, $unsupportedDelete->statusCode());
    }

    public function testMissingModerationPolicyKeepsDetailReadableAndDisablesControls(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $controller = new AdministratorReviewController(
            $this->renderer,
            $this->services,
            new ReviewModerationPolicyProvider(
                dirname(__DIR__, 2),
                Environment::fromArray([
                    'REVIEW_MODERATION_NOTE_MAXIMUM_CHARACTERS' => '',
                ]),
            ),
            $this->administratorSession,
            $this->tokens,
            new DateTimeZone('Asia/Kathmandu'),
        );

        $response = $controller->show(
            Request::create('GET', '/admin/reviews/11'),
            ['id' => '11'],
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString(
            'Moderation is unavailable until the approved note-length policy is configured.',
            $response->body(),
        );
        self::assertStringNotContainsString('data-review-moderation-form', $response->body());
        self::assertStringNotContainsString('/assets/js/admin-reviews.js', $response->body());
    }

    public function testAdministratorApisExposeExactContextWithoutTokenHash(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $list = $this->router->dispatch(Request::create('GET', '/api/v1/admin/reviews?status=pending'));
        self::assertSame(200, $list->statusCode());
        $decodedList = json_decode($list->body(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame([
            'review_id', 'booking_id', 'review_invitation_id', 'guest_name', 'rating',
            'title', 'comment', 'review_status', 'moderated_by', 'moderated_at',
            'moderation_note', 'created_at', 'updated_at',
        ], array_keys($decodedList['data'][0]));

        $detail = $this->router->dispatch(Request::create('GET', '/api/v1/admin/reviews/11'));
        self::assertSame(200, $detail->statusCode());
        $decoded = json_decode($detail->body(), true, 32, JSON_THROW_ON_ERROR);
        self::assertTrue($decoded['data']['verified_stay']);
        self::assertSame('checked_out', $decoded['data']['booking']['booking_status']);
        self::assertNotNull($decoded['data']['invitation']['used_at']);
        self::assertStringNotContainsString('token_hash', $detail->body());
        self::assertStringNotContainsString(self::invitation()->tokenHash(), $detail->body());
    }

    public function testApprovalRequiresCsrfUsesExactPayloadAndBecomesImmutable(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
        $payload = json_encode([
            'review_status' => Review::STATUS_APPROVED,
            'moderation_note' => 'Verified against the checked-out stay.',
        ], JSON_THROW_ON_ERROR);

        $withoutCsrf = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/reviews/11/status',
            headers: ['Content-Type' => 'application/json'],
            body: $payload,
        ));
        self::assertSame(403, $withoutCsrf->statusCode());
        self::assertSame(Review::STATUS_PENDING, $this->reviews->items[11]->status());

        $approved = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/reviews/11/status',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->tokens->token(),
                'User-Agent' => " Review\tModerator ",
            ],
            body: $payload,
            remoteAddress: '203.0.113.90',
        ));
        self::assertSame(200, $approved->statusCode());
        $decoded = json_decode($approved->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame(Review::STATUS_APPROVED, $decoded['data']['review_status']);
        self::assertSame(91, $decoded['data']['moderated_by']);
        self::assertSame(Review::STATUS_APPROVED, $this->reviews->items[11]->status());
        self::assertCount(1, $this->audit->entries);
        self::assertStringNotContainsString(
            'Verified against the checked-out stay.',
            (string) $this->audit->entries[0]->metadataJson(),
        );

        $again = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/reviews/11/status',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->tokens->token(),
            ],
            body: $payload,
        ));
        self::assertSame(409, $again->statusCode());

        $detail = $this->router->dispatch(Request::create('GET', '/admin/reviews/11'));
        self::assertSame(200, $detail->statusCode());
        self::assertStringNotContainsString('data-review-moderation-form', $detail->body());
    }

    public function testRejectionNoteAndPayloadValidationFailWithoutMutation(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));

        foreach ([
            ['review_status' => Review::STATUS_REJECTED],
            ['review_status' => Review::STATUS_REJECTED, 'moderation_note' => '   '],
            ['review_status' => Review::STATUS_PENDING],
            ['review_status' => Review::STATUS_APPROVED, 'unexpected' => true],
        ] as $payload) {
            $response = $this->router->dispatch(Request::create(
                'PATCH',
                '/api/v1/admin/reviews/11/status',
                headers: [
                    'Content-Type' => 'application/json',
                    'X-CSRF-Token' => $this->tokens->token(),
                ],
                body: json_encode($payload, JSON_THROW_ON_ERROR),
            ));
            self::assertSame(422, $response->statusCode());
            self::assertSame(Review::STATUS_PENDING, $this->reviews->items[11]->status());
        }

        self::assertSame(405, $this->router->dispatch(
            Request::create('DELETE', '/api/v1/admin/reviews/11'),
        )->statusCode());
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

    private static function review(): Review
    {
        $created = new DateTimeImmutable('2026-08-02 00:30:00 UTC');

        return new Review(
            11,
            23,
            7,
            '<script>Verified Guest</script>',
            5,
            '<b>Warm stay</b>',
            '<img src=x onerror=alert(1)>' . "\n" . 'Helpful team.',
            Review::STATUS_PENDING,
            null,
            null,
            null,
            $created,
            $created,
        );
    }

    private static function booking(): Booking
    {
        return new Booking(
            23,
            'TGH-REVIEW-000023',
            null,
            'Verified Guest',
            'verified@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-07-20 00:00:00 UTC'),
            new DateTimeImmutable('2026-07-22 00:00:00 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            '5000.00',
            '10000.00',
            Booking::STATUS_CHECKED_OUT,
            null,
            new DateTimeImmutable('2026-07-01 00:00:00 UTC'),
            'v1',
            new DateTimeImmutable('2026-07-02 00:00:00 UTC'),
            new DateTimeImmutable('2026-07-01 01:00:00 UTC'),
            new DateTimeImmutable('2026-07-20 06:00:00 UTC'),
            new DateTimeImmutable('2026-07-22 06:00:00 UTC'),
            null,
            null,
            null,
            null,
            new DateTimeImmutable('2026-07-01 00:00:00 UTC'),
            new DateTimeImmutable('2026-07-22 06:00:00 UTC'),
            null,
        );
    }

    private static function invitation(): ReviewInvitation
    {
        return new ReviewInvitation(
            7,
            23,
            hash('sha256', 'feature-review-token'),
            new DateTimeImmutable('2026-08-08 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-02 00:30:00 UTC'),
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        );
    }
}

final class FeatureAdministratorReviewRepository implements AdministratorReviewRepository
{
    /** @var array<int, Review> */
    public array $items = [];
    public ?string $lastStatus = null;

    public function findAll(?string $status = null): array
    {
        $this->lastStatus = $status;

        return array_values(array_filter(
            $this->items,
            static fn (Review $review): bool => $status === null || $review->status() === $status,
        ));
    }

    public function findById(int $id): ?Review
    {
        return $this->items[$id] ?? null;
    }

    public function lockById(int $id): ?Review
    {
        return $this->findById($id);
    }

    public function transitionPending(
        int $reviewId,
        string $targetStatus,
        int $administratorId,
        ?string $moderationNote,
        DateTimeImmutable $moderatedAt,
    ): ?Review {
        $review = $this->items[$reviewId] ?? null;

        if (!$review instanceof Review || $review->status() !== Review::STATUS_PENDING) {
            return null;
        }

        return $this->items[$reviewId] = new Review(
            $review->id(),
            $review->bookingId(),
            $review->reviewInvitationId(),
            $review->guestNameSnapshot(),
            $review->rating(),
            $review->title(),
            $review->comment(),
            $targetStatus,
            $administratorId,
            $moderatedAt,
            $moderationNote,
            $review->createdAt(),
            $moderatedAt,
        );
    }
}

final class FeatureAdministratorReviewBookingRepository implements BookingCatalogRepository
{
    public function __construct(private Booking $booking)
    {
    }

    public function findAll(?string $status = null): array { return [$this->booking]; }
    public function findById(int $id): ?Booking { return $id === $this->booking->id() ? $this->booking : null; }
    public function findByReference(string $bookingReference): ?Booking { return null; }
    public function findRoomByBookingId(int $bookingId): ?BookingRoom { return null; }
    public function findEventsByBookingId(int $bookingId): array { return []; }
}

final class FeatureAdministratorReviewInvitationRepository implements ReviewInvitationRepository
{
    public function __construct(private ReviewInvitation $invitation)
    {
    }

    public function create(
        int $bookingId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): ReviewInvitation {
        throw new RuntimeException('Not supported.');
    }

    public function findById(int $id): ?ReviewInvitation
    {
        return $id === $this->invitation->id() ? $this->invitation : null;
    }

    public function findByBookingId(int $bookingId): ?ReviewInvitation { return null; }
    public function findByTokenHash(string $tokenHash): ?ReviewInvitation { return null; }
    public function lockByTokenHash(string $tokenHash): ?ReviewInvitation { return null; }
    public function markUsed(int $invitationId, DateTimeImmutable $usedAt): ?ReviewInvitation { return null; }
}

final class FeatureAdministratorReviewAuditRepository implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];

    public function append(AuditLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class FeatureAdministratorReviewTransaction implements ReviewTransaction
{
    private bool $active = false;

    public function begin(): void { $this->active = true; }
    public function commit(): void { $this->active = false; }
    public function rollBack(): void { $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}

final class FeatureAdministratorReviewAuthenticator implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
