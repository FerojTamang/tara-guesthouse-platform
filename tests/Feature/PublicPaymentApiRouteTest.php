<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicPaymentController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicPaymentRateLimitMiddleware;
use App\Models\Payment;
use App\Requests\GuestPaymentRequest;
use App\Services\GuestPaymentSubmissionServiceProvider;
use App\Services\GuestPaymentSubmitter;
use App\Services\PublicPaymentRateLimiter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PublicPaymentApiRouteTest extends TestCase
{
    private string $savePath;

    private string $rateLimitPath;

    private Session $session;

    private CsrfTokenManager $tokens;

    private Router $router;

    private PublicPaymentRouteSubmitterFake $submitter;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-route-session-'
            . $suffix;
        $this->rateLimitPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-payment-route-limit-'
            . $suffix;
        self::assertTrue(mkdir($this->savePath, 0700));

        $this->session = Session::start($this->sessionConfiguration());
        $this->tokens = new CsrfTokenManager($this->session);
        $this->submitter = new PublicPaymentRouteSubmitterFake();
        $configuration = require dirname(__DIR__, 2) . '/config/upload.php';
        $controller = new PublicPaymentController(
            new GuestPaymentSubmissionServiceProvider(
                fn (): GuestPaymentSubmitter => $this->submitter,
            ),
            static fn (): array => $configuration['payment_proofs'],
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
            Request::create('GET', '/api/v1/payments'),
        );
        self::assertSame(405, $wrongMethod->statusCode());
        self::assertSame('POST', $wrongMethod->headers()['Allow']);

        $missingToken = $this->router->dispatch(
            Request::create(
                'POST',
                '/api/v1/payments',
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(self::payload(), JSON_THROW_ON_ERROR),
                remoteAddress: '203.0.113.20',
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

    public function testValidSubmissionIncludesTheSeparatePaymentRateLimitHeaders(): void
    {
        $response = $this->router->dispatch($this->request('203.0.113.21'));

        self::assertSame(201, $response->statusCode());
        self::assertSame('4', $response->headers()['X-RateLimit-Remaining']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(1, $this->submitter->calls);
    }

    public function testSixthCsrfValidPaymentAttemptIsBlockedBeforeSubmission(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            self::assertSame(
                201,
                $this->router
                    ->dispatch($this->request('203.0.113.22'))
                    ->statusCode(),
            );
        }

        $blocked = $this->router->dispatch($this->request('203.0.113.22'));

        self::assertSame(429, $blocked->statusCode());
        self::assertSame('0', $blocked->headers()['X-RateLimit-Remaining']);
        self::assertGreaterThan(0, (int) $blocked->headers()['Retry-After']);
        self::assertSame(5, $this->submitter->calls);
    }

    private function request(string $remoteAddress): Request
    {
        return Request::create(
            'POST',
            '/api/v1/payments',
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
            'booking_reference' => 'TGH-7F3A9C2D',
            'booking_access_token' => 'v1.' . str_repeat('A', 43),
            'payment_method_id' => '7',
            'amount' => '18000.00',
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

final class PublicPaymentRouteSubmitterFake implements GuestPaymentSubmitter
{
    public int $calls = 0;

    public function submit(GuestPaymentRequest $request): Payment
    {
        $this->calls++;
        $timestamp = new DateTimeImmutable('2026-08-01 12:05:00 UTC');

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
            $timestamp,
            null,
            null,
            null,
            null,
            null,
            $timestamp,
            $timestamp,
        );
    }
}
