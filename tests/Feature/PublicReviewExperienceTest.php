<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicReviewController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicReviewRateLimitMiddleware;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Repositories\ReviewRepository;
use App\Services\PublicReviewRateLimiter;
use App\Services\PublicReviewService;
use App\Services\PublicReviewServiceProvider;
use App\Services\ReviewSubmissionFlash;
use App\Services\ReviewTokenHasher;
use App\Services\ReviewTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublicReviewExperienceTest extends TestCase
{
    private string $sessionPath;
    private string $rateLimitPath;
    private Session $session;
    private CsrfTokenManager $tokens;
    private Router $router;
    private ViewRenderer $renderer;
    private PublicReviewController $controller;
    private FeatureReviewInvitationRepository $invitations;
    private FeatureReviewRepository $reviews;
    private string $reviewToken;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = bin2hex(random_bytes(8));
        $this->sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-review-session-' . $suffix;
        $this->rateLimitPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tgh-review-limit-' . $suffix;
        self::assertTrue(mkdir($this->sessionPath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->reviewToken = str_repeat('ab', 32);
        $booking = self::booking();
        $bookings = new FeatureReviewBookingRepository($booking);
        $this->invitations = new FeatureReviewInvitationRepository(new ReviewInvitation(
            7,
            $booking->id(),
            hash('sha256', $this->reviewToken),
            new DateTimeImmutable('2026-08-08 00:00:00 UTC'),
            null,
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        ));
        $this->reviews = new FeatureReviewRepository();
        $service = new PublicReviewService(
            $bookings,
            $this->invitations,
            $this->reviews,
            new FeatureReviewAuditRepository(),
            new FeatureReviewTransaction(),
            new ReviewTokenHasher(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
        );
        $this->renderer = new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
        $this->controller = new PublicReviewController(
            $this->renderer,
            new PublicReviewServiceProvider(static fn (): PublicReviewService => $service),
            new ReviewSubmissionFlash($this->session),
            $this->tokens,
        );
        $csrf = new CsrfMiddleware($this->tokens);
        $rateLimit = new PublicReviewRateLimitMiddleware(new PublicReviewRateLimiter($this->rateLimitPath, [
            'max_attempts' => 5,
            'attempt_window_seconds' => 900,
            'block_duration_seconds' => 900,
        ]));
        $this->router = new Router();
        $web = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $web($this->router, $this->renderer, $csrf, $this->controller, $rateLimit);
        $api = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $api($this->router, $this->renderer, $csrf, $this->controller, $rateLimit);
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

    public function testApprovedReadRoutesRegisterWithoutSubmissionSecurityDependencies(): void
    {
        $router = new Router();
        $web = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        $web($router, $this->renderer, $this->controller);
        $api = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
        $api($router, $this->renderer, $this->controller);

        self::assertSame(200, $router->dispatch(Request::create('GET', '/reviews'))->statusCode());
        self::assertSame(200, $router->dispatch(Request::create('GET', '/review/' . $this->reviewToken))->statusCode());
        self::assertSame(200, $router->dispatch(Request::create('GET', '/api/v1/reviews'))->statusCode());
        self::assertSame(405, $router->dispatch(Request::create('POST', '/review/' . $this->reviewToken))->statusCode());
        self::assertSame(405, $router->dispatch(Request::create('POST', '/api/v1/reviews'))->statusCode());
    }

    public function testApprovedReviewsPageAndApiEscapeAndExposeOnlySafeFields(): void
    {
        $this->reviews->seed(self::review(
            Review::STATUS_APPROVED,
            '<script>Verified Guest</script>',
            '<b>Warm stay</b>',
            '<img src=x onerror=alert(1)>\nHelpful team.',
            11,
        ));
        $this->reviews->seed(self::review(Review::STATUS_PENDING, 'Pending Guest', null, 'Not public.', 12));

        $page = $this->router->dispatch(Request::create('GET', '/reviews'));
        self::assertSame(200, $page->statusCode());
        self::assertStringContainsString('href="/reviews">Reviews</a>', $page->body());
        self::assertStringContainsString('&lt;script&gt;Verified Guest&lt;/script&gt;', $page->body());
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $page->body());
        self::assertStringNotContainsString('Pending Guest', $page->body());
        self::assertStringNotContainsString('<script>Verified Guest</script>', $page->body());

        $api = $this->router->dispatch(Request::create('GET', '/api/v1/reviews'));
        self::assertSame(200, $api->statusCode());
        $decoded = json_decode($api->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame([
            'guest_name', 'rating', 'title', 'comment', 'created_at',
        ], array_keys($decoded['data'][0]));
        self::assertStringNotContainsString('review_invitation_id', $api->body());
        self::assertStringNotContainsString('moderated_by', $api->body());
        self::assertStringNotContainsString('Pending Guest', $api->body());
    }

    public function testInvitationPageIsNoStoreNoReferrerAndContainsCsrfWithoutBookingData(): void
    {
        $response = $this->router->dispatch(Request::create('GET', '/review/' . $this->reviewToken));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store, private, max-age=0', $response->headers()['Cache-Control']);
        self::assertSame('no-referrer', $response->headers()['Referrer-Policy']);
        self::assertSame('noindex, nofollow, noarchive', $response->headers()['X-Robots-Tag']);
        self::assertStringContainsString('action="/review/' . $this->reviewToken . '"', $response->body());
        self::assertStringContainsString('value="' . $this->tokens->token() . '"', $response->body());
        self::assertStringNotContainsString('verified@example.test', $response->body());
        self::assertStringNotContainsString('TGH-REVIEW-000023', $response->body());
    }

    public function testWebSubmissionRequiresCsrfThenUsesPrgAndConsumesTokenOnce(): void
    {
        $input = self::input();
        $withoutCsrf = $this->router->dispatch(Request::create(
            'POST',
            '/review/' . $this->reviewToken,
            input: $input,
            remoteAddress: '203.0.113.60',
        ));
        self::assertSame(403, $withoutCsrf->statusCode());
        self::assertSame('no-referrer', $withoutCsrf->headers()['Referrer-Policy']);
        self::assertSame('noindex, nofollow, noarchive', $withoutCsrf->headers()['X-Robots-Tag']);
        self::assertSame([], $this->reviews->all());
        self::assertFalse($this->invitations->invitation()->isUsed());

        $input['_csrf'] = $this->tokens->token();
        $submitted = $this->router->dispatch(Request::create(
            'POST',
            '/review/' . $this->reviewToken,
            headers: ['User-Agent' => 'Review Feature Test'],
            input: $input,
            remoteAddress: '203.0.113.60',
        ));
        self::assertSame(303, $submitted->statusCode());
        self::assertSame('/reviews', $submitted->headers()['Location']);
        self::assertStringNotContainsString($this->reviewToken, $submitted->headers()['Location']);
        self::assertCount(1, $this->reviews->all());
        self::assertSame(Review::STATUS_PENDING, $this->reviews->all()[0]->status());
        self::assertTrue($this->invitations->invitation()->isUsed());

        $success = $this->router->dispatch(Request::create('GET', '/reviews'));
        self::assertStringContainsString('submitted for moderation', $success->body());
        $refresh = $this->router->dispatch(Request::create('GET', '/reviews'));
        self::assertStringNotContainsString('submitted for moderation', $refresh->body());
        self::assertSame(404, $this->router->dispatch(Request::create('GET', '/review/' . $this->reviewToken))->statusCode());
    }

    public function testJsonSubmissionReturnsPendingReceiptAndRejectsUnsupportedFields(): void
    {
        $payload = ['review_token' => $this->reviewToken, ...self::input()];
        $response = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/reviews',
            headers: [
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $this->tokens->token(),
                'User-Agent' => 'Review API Test',
            ],
            body: json_encode($payload, JSON_THROW_ON_ERROR),
            remoteAddress: '203.0.113.61',
        ));
        self::assertSame(201, $response->statusCode());
        self::assertSame('no-referrer', $response->headers()['Referrer-Policy']);
        $decoded = json_decode($response->body(), true, 16, JSON_THROW_ON_ERROR);
        self::assertSame(['review_id' => 1, 'review_status' => 'pending'], $decoded['data']);
        self::assertStringNotContainsString($this->reviewToken, $response->body());
        self::assertStringNotContainsString('A warm stay', $response->body());

        $payload['review_status'] = 'approved';
        $invalid = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/reviews',
            headers: ['Content-Type' => 'application/json', 'X-CSRF-Token' => $this->tokens->token()],
            body: json_encode($payload, JSON_THROW_ON_ERROR),
            remoteAddress: '203.0.113.62',
        ));
        self::assertSame(422, $invalid->statusCode());
        self::assertStringNotContainsString('review_status', $invalid->body());
    }

    public function testValidationAssociatesTheErrorWithOnlyTheAffectedReviewField(): void
    {
        $input = self::input();
        $input['_csrf'] = $this->tokens->token();
        $input['rating'] = '6';

        $response = $this->router->dispatch(Request::create(
            'POST',
            '/review/' . $this->reviewToken,
            input: $input,
            remoteAddress: '203.0.113.64',
        ));

        self::assertSame(422, $response->statusCode());
        self::assertSame([], $this->reviews->all());
        self::assertStringContainsString('id="review-form-error"', $response->body());
        self::assertStringContainsString('aria-describedby="review-form-error"', $response->body());
        self::assertStringContainsString('href="#review-rating-1"', $response->body());
        self::assertStringContainsString('id="review-rating-error"', $response->body());
        self::assertMatchesRegularExpression(
            '/id="review-rating-1"[^>]*aria-invalid="true"/',
            $response->body(),
        );
        self::assertDoesNotMatchRegularExpression(
            '/id="review-title"[^>]*aria-invalid="true"/',
            $response->body(),
        );
        self::assertFalse($this->invitations->invitation()->isUsed());
    }

    public function testInvalidTokenAndSixthAttemptFailWithoutSensitiveDetails(): void
    {
        $invalid = $this->router->dispatch(Request::create('GET', '/review/not-a-token'));
        self::assertSame(404, $invalid->statusCode());
        self::assertSame('Not Found', $invalid->body());

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $input = self::input();
            $input['_csrf'] = $this->tokens->token();
            $response = $this->router->dispatch(Request::create(
                'POST',
                '/review/' . str_repeat('cd', 32),
                input: $input,
                remoteAddress: '203.0.113.63',
            ));
            self::assertSame($attempt <= 5 ? 404 : 429, $response->statusCode());
        }
    }

    /** @return array{rating: string, title: string, comment: string} */
    private static function input(): array
    {
        return [
            'rating' => '5',
            'title' => 'A warm stay',
            'comment' => 'The room was clean and the team was welcoming.',
        ];
    }

    private static function review(
        string $status,
        string $guest,
        ?string $title,
        string $comment,
        int $id,
    ): Review {
        $created = new DateTimeImmutable('2026-08-01 00:30:00 UTC');
        $moderated = $status === Review::STATUS_PENDING
            ? null
            : new DateTimeImmutable('2026-08-01 01:00:00 UTC');

        return new Review(
            $id,
            20 + $id,
            30 + $id,
            $guest,
            5,
            $title,
            $comment,
            $status,
            $status === Review::STATUS_PENDING ? null : 1,
            $moderated,
            null,
            $created,
            $moderated ?? $created,
        );
    }

    private static function booking(): Booking
    {
        return new Booking(
            23,
            'TGH-REVIEW-000023',
            null,
            'Verified Stay Guest',
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

final class FeatureReviewBookingRepository implements BookingCatalogRepository
{
    public function __construct(private Booking $booking) {}
    public function findAll(?string $status = null): array { return [$this->booking]; }
    public function findById(int $id): ?Booking { return $id === $this->booking->id() ? $this->booking : null; }
    public function findByReference(string $bookingReference): ?Booking { return null; }
    public function findRoomByBookingId(int $bookingId): ?BookingRoom { return null; }
    public function findEventsByBookingId(int $bookingId): array { return []; }
}

final class FeatureReviewInvitationRepository implements ReviewInvitationRepository
{
    public function __construct(private ReviewInvitation $stored) {}
    public function invitation(): ReviewInvitation { return $this->stored; }
    public function create(int $bookingId, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $createdAt): ReviewInvitation { return $this->stored; }
    public function findById(int $id): ?ReviewInvitation { return $id === $this->stored->id() ? $this->stored : null; }
    public function findByBookingId(int $bookingId): ?ReviewInvitation { return $bookingId === $this->stored->bookingId() ? $this->stored : null; }
    public function findByTokenHash(string $tokenHash): ?ReviewInvitation { return hash_equals($this->stored->tokenHash(), $tokenHash) ? $this->stored : null; }
    public function lockByTokenHash(string $tokenHash): ?ReviewInvitation { return $this->findByTokenHash($tokenHash); }
    public function markUsed(int $invitationId, DateTimeImmutable $usedAt): ?ReviewInvitation
    {
        if ($invitationId !== $this->stored->id() || $this->stored->isUsed() || $this->stored->isExpiredAt($usedAt)) {
            return null;
        }
        return $this->stored = new ReviewInvitation(
            $this->stored->id(),
            $this->stored->bookingId(),
            $this->stored->tokenHash(),
            $this->stored->expiresAt(),
            $usedAt,
            $this->stored->createdAt(),
        );
    }
}

final class FeatureReviewRepository implements ReviewRepository
{
    /** @var array<int, Review> */
    private array $reviews = [];
    public function seed(Review $review): void { $this->reviews[$review->id()] = $review; }
    /** @return list<Review> */
    public function all(): array { return array_values($this->reviews); }
    public function findAllApproved(): array
    {
        return array_values(array_filter($this->reviews, static fn (Review $review): bool => $review->isPublic()));
    }
    public function createPending(int $bookingId, int $reviewInvitationId, string $guestNameSnapshot, int $rating, ?string $title, string $comment, DateTimeImmutable $createdAt): Review
    {
        $review = new Review(count($this->reviews) + 1, $bookingId, $reviewInvitationId, $guestNameSnapshot, $rating, $title, $comment, Review::STATUS_PENDING, null, null, null, $createdAt, $createdAt);
        $this->reviews[$review->id()] = $review;
        return $review;
    }
    public function findById(int $id): ?Review { return $this->reviews[$id] ?? null; }
    public function findByBookingId(int $bookingId): ?Review
    {
        foreach ($this->reviews as $review) { if ($review->bookingId() === $bookingId) { return $review; } }
        return null;
    }
    public function findByInvitationId(int $reviewInvitationId): ?Review
    {
        foreach ($this->reviews as $review) { if ($review->reviewInvitationId() === $reviewInvitationId) { return $review; } }
        return null;
    }
}

final class FeatureReviewAuditRepository implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public function append(AuditLogEntry $entry): void { $this->entries[] = $entry; }
}

final class FeatureReviewTransaction implements ReviewTransaction
{
    private bool $active = false;
    public function begin(): void { $this->active = true; }
    public function commit(): void { $this->active = false; }
    public function rollBack(): void { $this->active = false; }
    public function inProgress(): bool { return $this->active; }
}
