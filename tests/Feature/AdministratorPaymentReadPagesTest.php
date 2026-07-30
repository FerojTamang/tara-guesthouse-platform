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
use App\Models\AdministratorPaymentRecord;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Payment;
use App\Repositories\AdministratorPaymentCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\AdministratorPaymentService;
use App\Services\AdministratorPaymentServiceProvider;
use App\Services\AdministratorPaymentStatusServiceProvider;
use App\Services\BookingTransaction;
use App\Storage\PaymentProofReader;
use App\Storage\RetrievedPaymentProof;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorPaymentReadPagesTest extends TestCase
{
    private string $savePath;
    private Session $session;
    private AdministratorSession $administratorSession;
    private AdministratorPaymentCatalogRepository $repository;
    private AdministratorPaymentPageProofReaderFake $proofReader;
    private AdministratorPaymentPageAuditFake $audit;
    private AdministratorPaymentPageTransactionFake $transaction;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-payment-pages-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->session = Session::start($this->configuration());
        $csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession(
            $this->session,
            $csrfTokens,
        );
        $this->repository = $this->createStub(
            AdministratorPaymentCatalogRepository::class,
        );
        $this->proofReader = new AdministratorPaymentPageProofReaderFake();
        $this->audit = new AdministratorPaymentPageAuditFake();
        $this->transaction = new AdministratorPaymentPageTransactionFake();
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
            new AdministratorPaymentPageAuthenticatorFake(),
            new AdministratorLogoutService(
                $this->administratorSession,
                $logger,
            ),
            $this->administratorSession,
            $csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $paymentController = new AdministratorPaymentController(
            $renderer,
            new AdministratorPaymentServiceProvider(
                fn (): AdministratorPaymentService => new AdministratorPaymentService(
                    $this->repository,
                    $this->proofReader,
                    $this->audit,
                    $this->transaction,
                    static fn (): DateTimeImmutable => new DateTimeImmutable(
                        '2026-07-29 12:00:00 UTC',
                    ),
                ),
            ),
            new AdministratorPaymentStatusServiceProvider(
                static fn (): mixed => null,
            ),
            $this->administratorSession,
            $csrfTokens,
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
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($csrfTokens),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
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

    #[DataProvider('guestPaths')]
    public function testGuestCannotOpenPaymentPagesOrProof(string $path): void
    {
        $response = $this->router->dispatch(Request::create('GET', $path));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    /** @return iterable<string, array{string}> */
    public static function guestPaths(): iterable
    {
        yield 'list' => ['/admin/payments'];
        yield 'detail' => ['/admin/payments/44'];
        yield 'proof' => ['/admin/payments/44/proof'];
    }

    public function testListRendersEscapedAttemptsAndCanonicalFilter(): void
    {
        $this->repository->method('findAll')->willReturn([self::record()]);
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(
            Request::create('GET', '/admin/payments?status=submitted'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Payments | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('aria-current="page"', $response->body());
        self::assertStringContainsString('value="submitted"', $response->body());
        self::assertStringContainsString('selected', $response->body());
        self::assertStringContainsString('&lt;script&gt;Guest Example&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Guest Example</script>', $response->body());
        self::assertStringContainsString('TGH-2026-PAYMENT00000000001', $response->body());
        self::assertStringContainsString('NPR 18000.00', $response->body());
        self::assertStringContainsString('Proof attached', $response->body());
        self::assertStringContainsString('href="/admin/payments/44"', $response->body());
        self::assertStringNotContainsString('payment-proofs/', $response->body());
    }

    public function testInvalidStatusFilterReturnsEscaped422Page(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(
            Request::create('GET', '/admin/payments?status=completed'),
        );

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Payment status filter is invalid.', $response->body());
        self::assertStringNotContainsString('value="completed"', $response->body());
    }

    public function testDetailRendersPaymentBookingAndProtectedProofLink(): void
    {
        $this->repository->method('findById')->willReturn(self::record());
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(
            Request::create('GET', '/admin/payments/44'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Payment #44', $response->body());
        self::assertStringContainsString('BANK-&lt;REFERENCE&gt;', $response->body());
        self::assertStringContainsString('href="/admin/payments/44/proof"', $response->body());
        self::assertStringContainsString('href="/admin/bookings/31"', $response->body());
        self::assertStringContainsString('Account 123', $response->body());
        self::assertStringContainsString('2026-07-29 17:45', $response->body());
        self::assertStringNotContainsString('payment-proofs/', $response->body());
        self::assertStringContainsString('Verify payment', $response->body());
        self::assertStringContainsString('Reject payment', $response->body());
        self::assertStringContainsString('verification_note', $response->body());
        self::assertStringContainsString('/api/v1/admin/payments/44/status', $response->body());
    }

    public function testReviewedDetailDoesNotRenderReviewControls(): void
    {
        $this->repository->method('findById')->willReturn(
            self::record(status: Payment::STATUS_REJECTED),
        );
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(
            Request::create('GET', '/admin/payments/44'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringNotContainsString('Verify payment', $response->body());
        self::assertStringNotContainsString('Reject payment', $response->body());
        self::assertStringNotContainsString('data-payment-status-form', $response->body());
    }

    public function testProofReturnsValidatedBytesWithSafeHeadersAndAudit(): void
    {
        $this->repository->method('findById')->willReturn(self::record());
        $bytes = "%PDF-1.4\nproof\n%%EOF\n";
        $this->proofReader->proof = new RetrievedPaymentProof(
            $bytes,
            'pdf',
            'application/pdf',
        );
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(
            Request::create(
                'GET',
                '/admin/payments/44/proof',
                null,
                ['User-Agent' => "Test\t Agent"],
                [],
                '',
                '127.0.0.1',
            ),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame($bytes, $response->body());
        self::assertSame('application/pdf', $response->headers()['Content-Type']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame('same-origin', $response->headers()['Cross-Origin-Resource-Policy']);
        self::assertSame('no-referrer', $response->headers()['Referrer-Policy']);
        self::assertSame('inline; filename="payment-proof-44.pdf"', $response->headers()['Content-Disposition']);
        self::assertStringNotContainsString('payment-proofs/', $response->headers()['Content-Disposition']);
        self::assertCount(1, $this->audit->entries);
        self::assertSame('payment_proof_accessed', $this->audit->entries[0]->eventType());
        self::assertSame('Test Agent', $this->audit->entries[0]->userAgent());
        self::assertSame(1, $this->transaction->commitCount);
    }

    public function testProoflessPaymentReturns404WithoutAudit(): void
    {
        $this->repository->method('findById')->willReturn(self::record(null));
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(
            Request::create('GET', '/admin/payments/44/proof'),
        );

        self::assertSame(404, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([], $this->audit->entries);
        self::assertSame(0, $this->transaction->commitCount);
    }

    #[DataProvider('missingPaths')]
    public function testMalformedOrMissingPaymentReturns404(string $path): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->administratorSession->establish(new AdministratorIdentity(91));

        $response = $this->router->dispatch(Request::create('GET', $path));

        self::assertSame(404, $response->statusCode());
        self::assertSame('Not Found', $response->body());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    /** @return iterable<string, array{string}> */
    public static function missingPaths(): iterable
    {
        yield 'zero detail' => ['/admin/payments/0'];
        yield 'non numeric detail' => ['/admin/payments/nope'];
        yield 'missing detail' => ['/admin/payments/999'];
        yield 'missing proof' => ['/admin/payments/999/proof'];
    }

    private static function record(
    ?string $proofPath = 'payment-proofs/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.pdf',
    string $status = Payment::STATUS_SUBMITTED,
    ): AdministratorPaymentRecord
    {
        $createdAt = new DateTimeImmutable('2026-07-29 06:00:00 UTC');
        $payment = new Payment(
            44,
            31,
            2,
            Payment::CURRENCY_NPR,
            '18000.00',
            'BANK-<REFERENCE>',
            $proofPath,
            'Guest note.',
            $status,
            new DateTimeImmutable('2026-07-29 06:05:00 UTC'),
            $status === Payment::STATUS_VERIFIED
                ? new DateTimeImmutable('2026-07-29 06:30:00 UTC')
                : null,
            $status === Payment::STATUS_VERIFIED ? 91 : null,
            $status === Payment::STATUS_REJECTED
                ? new DateTimeImmutable('2026-07-29 06:30:00 UTC')
                : null,
            $status === Payment::STATUS_REJECTED ? 91 : null,
            in_array($status, [Payment::STATUS_VERIFIED, Payment::STATUS_REJECTED], true)
                ? 'Reviewed payment.'
                : null,
            $createdAt,
            $createdAt,
        );

        return new AdministratorPaymentRecord(
            $payment,
            'TGH-2026-PAYMENT00000000001',
            '<script>Guest Example</script>',
            'guest@example.test',
            '+9779812345678',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            Booking::STATUS_PENDING,
            '18000.00',
            new DateTimeImmutable('2026-07-29 12:00:00 UTC'),
            'Bank transfer',
            'Account 123',
            true,
            false,
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

final class AdministratorPaymentPageAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}

final class AdministratorPaymentPageProofReaderFake implements PaymentProofReader
{
    public ?RetrievedPaymentProof $proof = null;

    public function read(string $relativePath): ?RetrievedPaymentProof
    {
        return $this->proof;
    }
}

final class AdministratorPaymentPageAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];

    public function append(AuditLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class AdministratorPaymentPageTransactionFake implements BookingTransaction
{
    public int $commitCount = 0;
    private bool $active = false;

    public function begin(): void
    {
        $this->active = true;
    }

    public function commit(): void
    {
        $this->commitCount++;
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
