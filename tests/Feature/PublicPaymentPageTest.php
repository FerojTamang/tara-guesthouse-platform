<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicPaymentPageController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicPaymentRateLimitMiddleware;
use App\Models\Booking;
use App\Models\BookingSubmissionResult;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Requests\GuestPaymentRequest;
use App\Services\BookingAccessToken;
use App\Services\GuestPaymentContinuation;
use App\Services\GuestPaymentPageService;
use App\Services\GuestPaymentPageServiceProvider;
use App\Services\GuestPaymentSubmissionServiceProvider;
use App\Services\GuestPaymentSubmitter;
use App\Services\GuestPaymentSuccessFlash;
use App\Services\PublicPaymentRateLimiter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublicPaymentPageTest extends TestCase
{
    private const KEY = 'public-payment-page-feature-key-32-bytes-minimum';

    private string $sessionPath;

    private string $rateLimitPath;

    private Session $session;

    private CsrfTokenManager $tokens;

    private Router $router;

    private GuestPaymentContinuation $continuation;

    private PublicPaymentPageSubmitterFake $submitter;

    private Booking $booking;

    private string $bookingAccessToken;

    /** @var list<PaymentMethod> */
    private array $activePaymentMethods = [];

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->sessionPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-page-session-'
            . $suffix;
        $this->rateLimitPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-page-limit-'
            . $suffix;
        self::assertTrue(mkdir($this->sessionPath, 0700));
        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->continuation = new GuestPaymentContinuation($this->session);
        $this->submitter = new PublicPaymentPageSubmitterFake();
        $this->booking = self::booking();
        $bookingTokens = new BookingAccessToken(self::KEY);
        $this->bookingAccessToken = $bookingTokens->issue(
            $this->booking->bookingReference(),
            $this->booking->confirmationDeadlineAt(),
        );

        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findByReference')->willReturnCallback(
            fn (string $reference): ?Booking => $reference === $this->booking->bookingReference()
                ? $this->booking
                : null,
        );
        $this->activePaymentMethods = [
            self::method(7, 'Bank Transfer', false, 'storage/private/secret.png'),
            self::method(8, 'Digital Wallet', true, 'payment-methods/wallet.png'),
        ];
        $paymentMethods = $this->createStub(PaymentMethodCatalogRepository::class);
        $paymentMethods->method('findAllActive')->willReturnCallback(
            fn (): array => $this->activePaymentMethods,
        );
        $pageServices = new GuestPaymentPageServiceProvider(
            fn (): GuestPaymentPageService => new GuestPaymentPageService(
                $bookings,
                $paymentMethods,
                $bookingTokens,
                static fn (): DateTimeImmutable => self::timestamp('2026-08-01 12:00:00'),
            ),
        );
        $configuration = require dirname(__DIR__, 2) . '/config/upload.php';
        $controller = new PublicPaymentPageController(
            new ViewRenderer(
                dirname(__DIR__, 2)
                    . DIRECTORY_SEPARATOR
                    . 'resources'
                    . DIRECTORY_SEPARATOR
                    . 'views',
            ),
            $pageServices,
            new GuestPaymentSubmissionServiceProvider(
                fn (): GuestPaymentSubmitter => $this->submitter,
            ),
            $this->continuation,
            new GuestPaymentSuccessFlash(
                $this->session,
                new DateTimeZone('Asia/Kathmandu'),
            ),
            $this->tokens,
            static fn (): array => $configuration['payment_proofs'],
            new DateTimeZone('Asia/Kathmandu'),
        );
        $rateLimit = new PublicPaymentRateLimitMiddleware(
            new PublicPaymentRateLimiter($this->rateLimitPath, [
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

    public function testPaymentPageRequiresPrivateSessionContinuation(): void
    {
        $response = $this->router->dispatch(
            Request::create('GET', '/booking/payment'),
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/booking', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testPaymentPageRendersSafeServerControlledBookingFacts(): void
    {
        $this->storeContinuation();
        $response = $this->router->dispatch(
            Request::create('GET', '/booking/payment'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Submit your payment details', $response->body());
        self::assertStringContainsString('TGH-7F3A9C2D', $response->body());
        self::assertStringContainsString('NPR 18000.00', $response->body());
        self::assertStringContainsString('Bank Transfer', $response->body());
        self::assertStringContainsString('Digital Wallet — proof required', $response->body());
        self::assertStringContainsString('Use the configured account.', $response->body());
        self::assertStringContainsString('TGH-ACCOUNT-8', $response->body());
        self::assertStringContainsString('src="/payment-methods/wallet.png"', $response->body());
        self::assertStringContainsString('Scan this QR code to pay', $response->body());
        self::assertStringContainsString('tg-payment-method-card__qr', $response->body());
        self::assertStringNotContainsString('storage/private/secret.png', $response->body());
        self::assertStringContainsString(
            'value="' . $this->tokens->token() . '"',
            $response->body(),
        );
        self::assertStringContainsString('/assets/js/public-payment.js', $response->body());
        self::assertStringNotContainsString($this->bookingAccessToken, $response->body());
        self::assertStringNotContainsString('booking_access_token', $response->body());
        self::assertStringNotContainsString('name="booking_reference"', $response->body());
        self::assertStringNotContainsString('name="amount"', $response->body());
        self::assertStringNotContainsString('payment_status', $response->body());
        self::assertStringNotContainsString('guest@example.test', $response->body());
    }

    public function testPaymentPageWithNoActiveMethodsShowsExplicitNonSubmittableState(): void
    {
        $this->activePaymentMethods = [];
        $this->storeContinuation();

        $response = $this->router->dispatch(
            Request::create('GET', '/booking/payment'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Payment instructions are unavailable', $response->body());
        self::assertStringContainsString('TGH-7F3A9C2D', $response->body());
        self::assertStringContainsString('/contact', $response->body());
        self::assertStringNotContainsString('name="payment_method_id"', $response->body());
        self::assertStringNotContainsString('Submit payment for review', $response->body());
        self::assertStringNotContainsString($this->bookingAccessToken, $response->body());
    }

    public function testPostRequiresCsrfBeforeConsumingPaymentRateLimit(): void
    {
        $this->storeContinuation();
        $response = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking/payment',
                input: self::input(),
                remoteAddress: '203.0.113.30',
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

    public function testSuccessfulPostUsesSessionAccessAndServerAmountThenUsesPrg(): void
    {
        $this->storeContinuation();
        $input = self::input();
        $input['_csrf'] = $this->tokens->token();

        $submitted = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking/payment',
                input: $input,
                remoteAddress: '203.0.113.31',
            ),
        );

        self::assertSame(303, $submitted->statusCode());
        self::assertSame(
            '/booking/payment/success',
            $submitted->headers()['Location'],
        );
        self::assertSame('4', $submitted->headers()['X-RateLimit-Remaining']);
        self::assertSame(1, $this->submitter->calls);
        self::assertInstanceOf(GuestPaymentRequest::class, $this->submitter->lastRequest);
        self::assertSame(
            $this->booking->bookingReference(),
            $this->submitter->lastRequest->bookingReference(),
        );
        self::assertSame(
            $this->bookingAccessToken,
            $this->submitter->lastRequest->bookingAccessToken(),
        );
        self::assertSame('18000.00', $this->submitter->lastRequest->amount());
        self::assertSame(7, $this->submitter->lastRequest->paymentMethodId());
        self::assertSame('BANK-001', $this->submitter->lastRequest->transactionReference());
        self::assertSame('Transferred today', $this->submitter->lastRequest->guestNotes());

        $success = $this->router->dispatch(
            Request::create('GET', '/booking/payment/success'),
        );

        self::assertSame(200, $success->statusCode());
        self::assertSame('no-store', $success->headers()['Cache-Control']);
        self::assertStringContainsString('Your payment is under review', $success->body());
        self::assertStringContainsString('TGH-7F3A9C2D', $success->body());
        self::assertStringContainsString('submitted', $success->body());
        self::assertStringContainsString('NPR 18000.00', $success->body());
        self::assertStringContainsString('does not confirm or guarantee the room', $success->body());
        self::assertStringNotContainsString('booking confirmed', strtolower($success->body()));
        self::assertStringNotContainsString('payment verified', strtolower($success->body()));
        self::assertStringNotContainsString($this->bookingAccessToken, $success->body());
        self::assertStringNotContainsString('BANK-001', $success->body());
        self::assertStringNotContainsString('Transferred today', $success->body());
        self::assertStringNotContainsString('payment-proofs', $success->body());

        $refresh = $this->router->dispatch(
            Request::create('GET', '/booking/payment/success'),
        );
        self::assertSame(302, $refresh->statusCode());
        self::assertSame('/booking/payment', $refresh->headers()['Location']);
        self::assertNotNull($this->continuation->current());
    }

    public function testValidationErrorIsLinkedToItsFieldAndRetainsSafeInput(): void
    {
        $this->storeContinuation();
        $input = self::input();
        $input['_csrf'] = $this->tokens->token();
        $input['payment_method_id'] = '0';

        $response = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking/payment',
                input: $input,
                remoteAddress: '203.0.113.33',
            ),
        );

        self::assertSame(422, $response->statusCode());
        self::assertSame(0, $this->submitter->calls);
        self::assertStringContainsString('Payment method is invalid.', $response->body());
        self::assertStringContainsString('id="payment_method_id_error"', $response->body());
        self::assertStringContainsString('aria-describedby="payment_method_id_error"', $response->body());
        self::assertStringContainsString('value="BANK-001"', $response->body());
        self::assertStringContainsString('Transferred today', $response->body());
        self::assertStringNotContainsString($this->bookingAccessToken, $response->body());
    }

    public function testGuestControlledBookingTokenAmountAndStatusAreRejected(): void
    {
        $this->storeContinuation();
        $input = self::input();
        $input['_csrf'] = $this->tokens->token();
        $input['booking_reference'] = 'ATTACKER-REFERENCE';
        $input['booking_access_token'] = 'v1.' . str_repeat('B', 43);
        $input['amount'] = '1.00';
        $input['payment_status'] = Payment::STATUS_VERIFIED;

        $response = $this->router->dispatch(
            Request::create(
                'POST',
                '/booking/payment',
                input: $input,
                remoteAddress: '203.0.113.32',
            ),
        );

        self::assertSame(422, $response->statusCode());
        self::assertSame(0, $this->submitter->calls);
        self::assertStringContainsString('payload is invalid', $response->body());
        self::assertStringNotContainsString('ATTACKER-REFERENCE', $response->body());
        self::assertStringNotContainsString(str_repeat('B', 43), $response->body());
        self::assertStringNotContainsString('value="1.00"', $response->body());
        self::assertStringNotContainsString('verified', $response->body());
    }

    public function testWrongSignedContinuationIsClearedAndRedirected(): void
    {
        $this->session->set('public_payment_continuation', [
            'version' => 1,
            'booking_reference' => $this->booking->bookingReference(),
            'booking_access_token' => 'v1.' . str_repeat('B', 43),
        ]);

        $response = $this->router->dispatch(
            Request::create('GET', '/booking/payment'),
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/booking', $response->headers()['Location']);
        self::assertNull($this->continuation->current());
    }

    private function storeContinuation(): void
    {
        $this->continuation->store(
            new BookingSubmissionResult(
                $this->booking,
                $this->bookingAccessToken,
            ),
        );
    }

    /** @return array<string, mixed> */
    private static function input(): array
    {
        return [
            'payment_method_id' => '7',
            'transaction_reference' => 'BANK-001',
            'guest_notes' => 'Transferred today',
        ];
    }

    private static function booking(): Booking
    {
        return new Booking(
            7,
            'TGH-7F3A9C2D',
            null,
            'Guest Example',
            'guest@example.test',
            '+9779812345678',
            self::date('2026-08-10'),
            self::date('2026-08-13'),
            2,
            1,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            Booking::STATUS_PENDING,
            null,
            self::timestamp('2026-08-01 10:00:00'),
            'v1',
            self::timestamp('2026-08-01 18:00:00'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            self::timestamp('2026-08-01 10:00:00'),
            self::timestamp('2026-08-01 10:00:00'),
            null,
        );
    }

    private static function method(
        int $id,
        string $name,
        bool $requiresProof,
        ?string $qrImagePath = null,
    ): PaymentMethod {
        $timestamp = self::timestamp('2026-07-28 06:00:00');

        return new PaymentMethod(
            $id,
            $name,
            'Use the configured account.',
            'TGH-ACCOUNT-' . $id,
            $qrImagePath,
            $requiresProof,
            true,
            $timestamp,
            $timestamp,
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

    private static function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' 00:00:00 UTC');
    }

    private static function timestamp(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value . ' UTC');
    }
}

final class PublicPaymentPageSubmitterFake implements GuestPaymentSubmitter
{
    public int $calls = 0;

    public ?GuestPaymentRequest $lastRequest = null;

    public function submit(GuestPaymentRequest $request): Payment
    {
        $this->calls++;
        $this->lastRequest = $request;
        $submittedAt = new DateTimeImmutable('2026-08-01 12:05:00 UTC');

        return new Payment(
            44 + $this->calls,
            7,
            $request->paymentMethodId(),
            Payment::CURRENCY_NPR,
            $request->amount(),
            $request->transactionReference(),
            null,
            $request->guestNotes(),
            Payment::STATUS_SUBMITTED,
            $submittedAt,
            null,
            null,
            null,
            null,
            null,
            $submittedAt,
            $submittedAt,
        );
    }
}
