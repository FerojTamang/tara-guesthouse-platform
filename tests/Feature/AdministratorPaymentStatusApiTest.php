<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorPaymentController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingStatusTransitionResult;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\PaymentWriteRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\AdministratorPaymentServiceProvider;
use App\Services\AdministratorPaymentStatusService;
use App\Services\AdministratorPaymentStatusServiceProvider;
use App\Services\BookingTransaction;
use App\Services\VerifiedPaymentBookingConfirmer;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorPaymentStatusApiTest extends TestCase
{
    private string $savePath;
    private Session $session;
    private CsrfTokenManager $csrfTokens;
    private AdministratorSession $administratorSession;
    private PaymentStatusApiWriterFake $payments;
    private PaymentStatusApiAuditFake $audit;
    private PaymentStatusApiBookingConfirmerFake $bookingConfirmer;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-payment-status-api-'
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
            new PaymentStatusApiAuthenticatorFake(),
            new AdministratorLogoutService(
                $this->administratorSession,
                $logger,
            ),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $this->payments = new PaymentStatusApiWriterFake();
        $this->payments->payment = self::payment();
        $this->payments->booking = self::booking();
        $this->audit = new PaymentStatusApiAuditFake();
        $this->bookingConfirmer = new PaymentStatusApiBookingConfirmerFake();
        $transaction = new PaymentStatusApiTransactionFake();
        $paymentController = new AdministratorPaymentController(
            $renderer,
            new AdministratorPaymentServiceProvider(
                static fn (): mixed => null,
            ),
            new AdministratorPaymentStatusServiceProvider(
                fn (): AdministratorPaymentStatusService => new AdministratorPaymentStatusService(
                    $this->payments,
                    $this->audit,
                    $this->bookingConfirmer,
                    $transaction,
                    static fn (): DateTimeImmutable => new DateTimeImmutable(
                        '2026-07-30 04:00:00 UTC',
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
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($this->csrfTokens),
            $paymentController,
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

    public function testGuestIsRejectedBeforeCsrfAndPaymentService(): void
    {
        $response = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/payments/44/status',
            headers: ['Content-Type' => 'application/json'],
            body: self::verifiedJson(),
        ));

        self::assertSame(401, $response->statusCode());
        self::assertSame('{"message":"Authentication required."}', $response->body());
        self::assertSame([], $this->payments->operations);
    }

    public function testAuthenticatedRequestRequiresCsrfBeforePaymentService(): void
    {
        $this->authenticate();
        $response = $this->router->dispatch(Request::create(
            'PATCH',
            '/api/v1/admin/payments/44/status',
            headers: ['Content-Type' => 'application/json'],
            body: self::verifiedJson(),
        ));

        self::assertSame(403, $response->statusCode());
        self::assertSame('{"message":"Forbidden."}', $response->body());
        self::assertSame([], $this->payments->operations);
    }

    public function testRouteIsPatchOnly(): void
    {
        $response = $this->router->dispatch(
            Request::create('GET', '/api/v1/admin/payments/44/status'),
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('PATCH', $response->headers()['Allow']);
    }

    public function testSuccessfulVerificationReturnsNarrowNoStoreContract(): void
    {
        $this->authenticate();
        $response = $this->router->dispatch($this->request(
            self::verifiedJson(),
            headers: ['User-Agent' => "  Tara\tPayment Agent  "],
            remoteAddress: '203.0.113.60',
        ));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'message' => 'Payment status updated.',
            'data' => [
                'payment_id' => 44,
                'booking_id' => 31,
                'previous_status' => Payment::STATUS_SUBMITTED,
                'payment_status' => Payment::STATUS_VERIFIED,
                'changed_at' => '2026-07-30T09:45:00+05:45',
            ],
        ], json_decode(
            $response->body(),
            true,
            16,
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame(Payment::STATUS_VERIFIED, $this->payments->reviewedStatus);
        self::assertCount(1, $this->bookingConfirmer->calls);
        self::assertSame(44, $this->bookingConfirmer->calls[0]['payment_id']);
        self::assertCount(1, $this->audit->entries);
        self::assertSame('payment_verified', $this->audit->entries[0]->eventType());
        self::assertSame('203.0.113.60', $this->audit->entries[0]->ipAddress());
        self::assertSame('Tara Payment Agent', $this->audit->entries[0]->userAgent());
        self::assertStringNotContainsString('verification_note', $response->body());
        self::assertStringNotContainsString('proof_path', $response->body());
        self::assertStringNotContainsString('guest_email', $response->body());
    }

    public function testContentTypeAndMalformedBodiesFailWithoutWrite(): void
    {
        $this->authenticate();
        $unsupported = $this->router->dispatch($this->request(
            self::verifiedJson(),
            contentType: 'text/plain',
        ));
        self::assertSame(415, $unsupported->statusCode());

        $malformed = $this->router->dispatch($this->request('{'));
        self::assertSame(400, $malformed->statusCode());

        $list = $this->router->dispatch($this->request('[]'));
        self::assertSame(400, $list->statusCode());
        self::assertSame([], $this->payments->operations);
    }

    public function testInvalidPayloadsReturn422WithoutWrite(): void
    {
        $this->authenticate();
        $payloads = [
            '{}',
            '{"status":"verified"}',
            '{"status":"refunded","verification_note":"No."}',
            '{"status":"verified","verification_note":""}',
            '{"status":"rejected","verification_note":"Valid","user_id":91}',
        ];

        foreach ($payloads as $payload) {
            $response = $this->router->dispatch($this->request($payload));
            self::assertSame(422, $response->statusCode());
            self::assertSame('no-store', $response->headers()['Cache-Control']);
        }

        self::assertSame([], $this->payments->operations);
    }

    public function testMissingAndReviewedPaymentReturn404And409(): void
    {
        $this->authenticate();
        $this->payments->payment = null;
        $missing = $this->router->dispatch($this->request(
            self::rejectedJson(),
            paymentId: 999,
        ));
        self::assertSame(404, $missing->statusCode());

        $this->payments->payment = self::payment(Payment::STATUS_REJECTED);
        $conflict = $this->router->dispatch($this->request(self::verifiedJson()));
        self::assertSame(409, $conflict->statusCode());
        self::assertSame(
            'The payment is no longer eligible for this action.',
            json_decode($conflict->body(), true, 16, JSON_THROW_ON_ERROR)['message'],
        );
    }

    public function testRejectionDoesNotRequireOrMutateBooking(): void
    {
        $this->authenticate();
        $this->payments->booking = null;
        $response = $this->router->dispatch($this->request(self::rejectedJson()));

        self::assertSame(200, $response->statusCode());
        self::assertSame(Payment::STATUS_REJECTED, $this->payments->reviewedStatus);
        self::assertSame(['payment:44'], $this->payments->operations);
        self::assertSame([], $this->bookingConfirmer->calls);
        self::assertSame('payment_rejected', $this->audit->entries[0]->eventType());
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    /** @param array<string, string> $headers */
    private function request(
        string $body,
        string $contentType = 'application/json; charset=UTF-8',
        array $headers = [],
        ?string $remoteAddress = null,
        int $paymentId = 44,
    ): Request {
        return Request::create(
            'PATCH',
            '/api/v1/admin/payments/' . $paymentId . '/status',
            headers: [
                'Content-Type' => $contentType,
                'X-CSRF-Token' => $this->csrfTokens->token(),
                ...$headers,
            ],
            body: $body,
            remoteAddress: $remoteAddress,
        );
    }

    private static function verifiedJson(): string
    {
        return '{"status":"verified","verification_note":"Reference and proof confirmed."}';
    }

    private static function rejectedJson(): string
    {
        return '{"status":"rejected","verification_note":"Reference could not be matched."}';
    }

    private static function payment(string $status = Payment::STATUS_SUBMITTED): Payment
    {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Payment(
            44,
            31,
            2,
            Payment::CURRENCY_NPR,
            '18000.00',
            'BANK-44',
            null,
            null,
            $status,
            new DateTimeImmutable('2026-07-30 03:10:00 UTC'),
            null,
            null,
            $status === Payment::STATUS_REJECTED
                ? new DateTimeImmutable('2026-07-30 03:30:00 UTC')
                : null,
            $status === Payment::STATUS_REJECTED ? 91 : null,
            $status === Payment::STATUS_REJECTED ? 'Rejected.' : null,
            $createdAt,
            $createdAt,
        );
    }

    private static function booking(): Booking
    {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Booking(
            31,
            'TGH-2026-PAYMENTAPI0000001',
            null,
            'Payment API Guest',
            'payment-api@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            Booking::STATUS_PENDING,
            null,
            $createdAt,
            'v1',
            new DateTimeImmutable('2026-07-30 05:00:00 UTC'),
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

final class PaymentStatusApiAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}

final class PaymentStatusApiWriterFake implements PaymentWriteRepository
{
    public ?Payment $payment = null;
    public ?Booking $booking = null;
    /** @var list<string> */
    public array $operations = [];
    public ?string $reviewedStatus = null;

    public function lockBookingByReference(string $bookingReference): ?Booking
    {
        return $this->booking;
    }

    public function lockBookingById(int $bookingId): ?Booking
    {
        $this->operations[] = 'booking:' . $bookingId;

        return $this->booking;
    }

    public function lockPaymentMethodById(int $paymentMethodId): ?PaymentMethod
    {
        return null;
    }

    public function lockCurrent(int $paymentId): ?Payment
    {
        $this->operations[] = 'payment:' . $paymentId;

        return $this->payment;
    }

    public function createSubmitted(
        int $bookingId,
        int $paymentMethodId,
        string $amount,
        ?string $transactionReference,
        ?string $proofPath,
        ?string $guestNotes,
        DateTimeImmutable $submittedAt,
    ): Payment {
        throw new RuntimeException('Not used.');
    }

    public function hasVerifiedForBooking(
        int $bookingId,
        ?int $excludingPaymentId = null,
    ): bool {
        return false;
    }

    public function markVerified(
        Payment $current,
        int $verifiedBy,
        string $note,
        DateTimeImmutable $verifiedAt,
    ): Payment {
        return $this->review(
            $current,
            Payment::STATUS_VERIFIED,
            $verifiedBy,
            $note,
            $verifiedAt,
        );
    }

    public function markRejected(
        Payment $current,
        int $rejectedBy,
        string $note,
        DateTimeImmutable $rejectedAt,
    ): Payment {
        return $this->review(
            $current,
            Payment::STATUS_REJECTED,
            $rejectedBy,
            $note,
            $rejectedAt,
        );
    }

    private function review(
        Payment $current,
        string $status,
        int $actor,
        string $note,
        DateTimeImmutable $changedAt,
    ): Payment {
        $this->reviewedStatus = $status;

        return new Payment(
            $current->id(),
            $current->bookingId(),
            $current->paymentMethodId(),
            $current->currency(),
            $current->amount(),
            $current->transactionReference(),
            $current->proofPath(),
            $current->guestNotes(),
            $status,
            $current->submittedAt(),
            $status === Payment::STATUS_VERIFIED ? $changedAt : null,
            $status === Payment::STATUS_VERIFIED ? $actor : null,
            $status === Payment::STATUS_REJECTED ? $changedAt : null,
            $status === Payment::STATUS_REJECTED ? $actor : null,
            $note,
            $current->createdAt(),
            $changedAt,
        );
    }
}

final class PaymentStatusApiBookingConfirmerFake implements VerifiedPaymentBookingConfirmer
{
    /** @var list<array{booking_id: int, payment_id: int}> */
    public array $calls = [];

    public function confirm(
        Booking $booking,
        Payment $verifiedPayment,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeImmutable $confirmedAt,
    ): BookingStatusTransitionResult {
        $this->calls[] = [
            'booking_id' => $booking->id(),
            'payment_id' => $verifiedPayment->id(),
        ];

        return new BookingStatusTransitionResult(
            $booking->id(),
            $booking->bookingReference(),
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            $confirmedAt,
        );
    }
}

final class PaymentStatusApiAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];

    public function append(AuditLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class PaymentStatusApiTransactionFake implements BookingTransaction
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
