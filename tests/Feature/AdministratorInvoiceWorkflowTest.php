<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorInvoiceController;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Models\AdministratorInvoiceRecord;
use App\Models\AdministratorInvoiceSummary;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Repositories\AdministratorInvoiceCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\InvoiceWriteRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorInvoiceService;
use App\Services\AdministratorInvoiceServiceProvider;
use App\Services\AdministratorLogoutService;
use App\Services\BookingTransaction;
use App\Services\InvoiceIssueService;
use App\Services\InvoiceIssueServiceProvider;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorInvoiceWorkflowTest extends TestCase
{
    private string $savePath;
    private Session $session;
    private CsrfTokenManager $csrfTokens;
    private AdministratorSession $administratorSession;
    private InvoiceWorkflowRepositoryFake $repository;
    private InvoiceWorkflowAuditFake $audit;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'tgh-admin-invoice-'
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
                . DIRECTORY_SEPARATOR . 'resources'
                . DIRECTORY_SEPARATOR . 'views',
        );
        $logger = new NullLogger();
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new InvoiceWorkflowAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $this->repository = new InvoiceWorkflowRepositoryFake();
        $this->repository->booking = self::booking();
        $this->repository->room = self::room();
        $this->repository->payments = [self::payment()];
        $this->repository->summaries = [self::summary()];
        $this->repository->record = self::record();
        $this->audit = new InvoiceWorkflowAuditFake();
        $transaction = new InvoiceWorkflowTransactionFake();
        $controller = new AdministratorInvoiceController(
            $renderer,
            new AdministratorInvoiceServiceProvider(
                fn (): AdministratorInvoiceService => new AdministratorInvoiceService(
                    $this->repository,
                ),
            ),
            new InvoiceIssueServiceProvider(
                fn (): InvoiceIssueService => new InvoiceIssueService(
                    $this->repository,
                    $this->audit,
                    $transaction,
                    new DateTimeZone('Asia/Kathmandu'),
                    static fn (): DateTimeImmutable => new DateTimeImmutable(
                        '2026-07-30 05:00:00 UTC',
                    ),
                ),
            ),
            $this->administratorSession,
            $this->csrfTokens,
            new DateTimeZone('Asia/Kathmandu'),
        );

        $this->router = new Router();
        $adminRegistrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'routes'
            . DIRECTORY_SEPARATOR . 'admin.php';
        $adminRegistrar(
            $this->router,
            $renderer,
            $authenticationController,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($this->csrfTokens),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $controller,
        );
        $apiRegistrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'routes'
            . DIRECTORY_SEPARATOR . 'api.php';
        $apiRegistrar(
            $this->router,
            $renderer,
            $authenticationController,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($this->csrfTokens),
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

        foreach (glob($this->savePath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->savePath);
        parent::tearDown();
    }

    public function testGuestCannotOpenInvoicePages(): void
    {
        foreach ([
            '/admin/invoices',
            '/admin/invoices/51',
            '/admin/invoices/51/print',
        ] as $path) {
            $response = $this->router->dispatch(Request::create('GET', $path));
            self::assertSame(302, $response->statusCode(), $path);
            self::assertSame('/admin/login', $response->headers()['Location'], $path);
            self::assertSame('no-store', $response->headers()['Cache-Control'], $path);
        }
    }

    public function testAuthenticatedListDetailAndPrintRenderEscapedStableSnapshot(): void
    {
        $this->authenticate();

        $list = $this->router->dispatch(Request::create('GET', '/admin/invoices'));
        self::assertSame(200, $list->statusCode());
        self::assertSame('no-store', $list->headers()['Cache-Control']);
        self::assertStringContainsString('TGH-INV-2026-000051', $list->body());
        self::assertStringContainsString('&lt;script&gt;Invoice Guest&lt;/script&gt;', $list->body());
        self::assertStringNotContainsString('<script>Invoice Guest</script>', $list->body());
        self::assertStringContainsString('href="/admin/invoices/51/print"', $list->body());

        $detail = $this->router->dispatch(Request::create('GET', '/admin/invoices/51'));
        self::assertSame(200, $detail->statusCode());
        self::assertStringContainsString('Accommodation', $detail->body());
        self::assertStringContainsString('NPR 18000.00', $detail->body());
        self::assertStringContainsString('NPR 0.00', $detail->body());
        self::assertStringContainsString('href="/admin/payments/44"', $detail->body());
        self::assertStringNotContainsString('Delete invoice', $detail->body());
        self::assertStringNotContainsString('Edit invoice', $detail->body());

        $print = $this->router->dispatch(Request::create('GET', '/admin/invoices/51/print'));
        self::assertSame(200, $print->statusCode());
        self::assertSame('no-store', $print->headers()['Cache-Control']);
        self::assertSame('no-cache', $print->headers()['Pragma']);
        self::assertSame('nosniff', $print->headers()['X-Content-Type-Options']);
        self::assertSame('SAMEORIGIN', $print->headers()['X-Frame-Options']);
        self::assertSame('no-referrer', $print->headers()['Referrer-Policy']);
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $print->body());
        self::assertStringContainsString('window.print()', $print->body());
    }

    public function testGuestAndMissingCsrfAreRejectedBeforeIssueService(): void
    {
        $guest = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/admin/bookings/31/invoice',
            headers: ['Content-Type' => 'application/json'],
            body: '{}',
        ));
        self::assertSame(401, $guest->statusCode());
        self::assertSame([], $this->repository->operations);

        $this->authenticate();
        $forbidden = $this->router->dispatch(Request::create(
            'POST',
            '/api/v1/admin/bookings/31/invoice',
            headers: ['Content-Type' => 'application/json'],
            body: '{}',
        ));
        self::assertSame(403, $forbidden->statusCode());
        self::assertSame([], $this->repository->operations);
    }

    public function testIssueRejectsGuestControlledFinancialFields(): void
    {
        $this->authenticate();
        $response = $this->router->dispatch($this->issueRequest(
            '{"invoice_number":"TGH-INV-2026-999999","total_amount":"1.00"}',
        ));

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([], $this->repository->operations);
        self::assertStringNotContainsString('999999', $response->body());
    }

    public function testIssueIsIdempotentAndReturnsStableNarrowContract(): void
    {
        $this->authenticate();

        $created = $this->router->dispatch($this->issueRequest('{}'));
        self::assertSame(201, $created->statusCode());
        self::assertSame('no-store', $created->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'message' => 'Invoice issued.',
            'data' => [
                'invoice_id' => 51,
                'booking_id' => 31,
                'invoice_number' => 'TGH-INV-2026-000051',
                'created' => true,
                'issued_at' => '2026-07-30T10:45:00+05:45',
                'detail_url' => '/admin/invoices/51',
                'print_url' => '/admin/invoices/51/print',
            ],
        ], json_decode($created->body(), true, 16, JSON_THROW_ON_ERROR));
        self::assertCount(1, $this->audit->entries);

        $this->repository->operations = [];
        $existing = $this->router->dispatch($this->issueRequest('{}'));
        self::assertSame(200, $existing->statusCode());
        self::assertFalse(json_decode(
            $existing->body(),
            true,
            16,
            JSON_THROW_ON_ERROR,
        )['data']['created']);
        self::assertSame(['booking:31', 'existing:31'], $this->repository->operations);
        self::assertCount(1, $this->audit->entries);
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    private function issueRequest(string $body): Request
    {
        return Request::create(
            'POST',
            '/api/v1/admin/bookings/31/invoice',
            headers: [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-CSRF-Token' => $this->csrfTokens->token(),
                'User-Agent' => 'Tara Invoice Agent',
            ],
            body: $body,
            remoteAddress: '203.0.113.70',
        );
    }

    private static function summary(): AdministratorInvoiceSummary
    {
        return new AdministratorInvoiceSummary(
            self::invoice(),
            'TGH-2026-INVOICE000000001',
            '<script>Invoice Guest</script>',
        );
    }

    private static function record(): AdministratorInvoiceRecord
    {
        return new AdministratorInvoiceRecord(
            self::invoice(),
            [new InvoiceItem(61, 51, 'Accommodation', '3.00', '6000.00', '18000.00')],
            'TGH-2026-INVOICE000000001',
            'Invoice Guest',
            'invoice@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            Booking::STATUS_CONFIRMED,
            44,
            'Bank Transfer',
            'BANK-44',
            new DateTimeImmutable('2026-07-30 04:00:00 UTC'),
            'Invoice Administrator',
        );
    }

    private static function invoice(): Invoice
    {
        $issuedAt = new DateTimeImmutable('2026-07-30 05:00:00 UTC');

        return new Invoice(
            51,
            31,
            'TGH-INV-2026-000051',
            Invoice::CURRENCY_NPR,
            '18000.00',
            Invoice::ZERO_TAX,
            '18000.00',
            $issuedAt,
            91,
            $issuedAt,
            $issuedAt,
        );
    }

    private static function booking(): Booking
    {
        $createdAt = new DateTimeImmutable('2026-07-30 03:00:00 UTC');

        return new Booking(
            31,
            'TGH-2026-INVOICE000000001',
            null,
            'Invoice Guest',
            'invoice@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-13 00:00:00 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            '18000.00',
            '18000.00',
            Booking::STATUS_CONFIRMED,
            null,
            $createdAt,
            'v1',
            new DateTimeImmutable('2026-07-30 05:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 04:00:00 UTC'),
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

    private static function room(): BookingRoom
    {
        return new BookingRoom(
            41,
            31,
            12,
            '6000.00',
            3,
            '18000.00',
            new DateTimeImmutable('2026-07-30 03:00:00 UTC'),
        );
    }

    private static function payment(): Payment
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
            Payment::STATUS_VERIFIED,
            new DateTimeImmutable('2026-07-30 03:10:00 UTC'),
            new DateTimeImmutable('2026-07-30 04:00:00 UTC'),
            91,
            null,
            null,
            'Verified.',
            $createdAt,
            $createdAt,
        );
    }

    /** @return array<string, int> */
    private static function loginPolicy(): array
    {
        return [
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

final class InvoiceWorkflowAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}

final class InvoiceWorkflowRepositoryFake implements AdministratorInvoiceCatalogRepository, InvoiceWriteRepository
{
    /** @var list<AdministratorInvoiceSummary> */
    public array $summaries = [];
    public ?AdministratorInvoiceRecord $record = null;
    public ?Booking $booking = null;
    public ?BookingRoom $room = null;
    /** @var list<Payment> */
    public array $payments = [];
    public ?Invoice $existing = null;
    /** @var list<string> */
    public array $operations = [];

    public function findAll(): array
    {
        return $this->summaries;
    }

    public function findById(int $invoiceId): ?AdministratorInvoiceRecord
    {
        return $invoiceId === 51 ? $this->record : null;
    }

    public function findInvoiceByBookingId(int $bookingId): ?Invoice
    {
        $this->operations[] = 'existing:' . $bookingId;

        return $this->existing;
    }

    public function lockBooking(int $bookingId): ?Booking
    {
        $this->operations[] = 'booking:' . $bookingId;

        return $this->booking;
    }

    public function lockRoom(int $bookingId): ?BookingRoom
    {
        $this->operations[] = 'room:' . $bookingId;

        return $this->room;
    }

    public function lockVerifiedPayments(int $bookingId): array
    {
        $this->operations[] = 'payments:' . $bookingId;

        return $this->payments;
    }

    public function createInvoice(
        int $bookingId,
        string $currency,
        string $subtotal,
        string $taxAmount,
        string $totalAmount,
        DateTimeImmutable $issuedAt,
        int $createdBy,
        int $invoiceYear,
    ): Invoice {
        $this->operations[] = 'create:' . $bookingId;

        return $this->existing = new Invoice(
            51,
            $bookingId,
            sprintf('TGH-INV-%04d-000051', $invoiceYear),
            $currency,
            $subtotal,
            $taxAmount,
            $totalAmount,
            $issuedAt,
            $createdBy,
            $issuedAt,
            $issuedAt,
        );
    }

    public function appendItem(
        int $invoiceId,
        string $description,
        string $quantity,
        string $unitPrice,
        string $lineTotal,
    ): InvoiceItem {
        $this->operations[] = 'item:' . $invoiceId;

        return new InvoiceItem(
            61,
            $invoiceId,
            $description,
            $quantity,
            $unitPrice,
            $lineTotal,
        );
    }
}

final class InvoiceWorkflowAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];

    public function append(AuditLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class InvoiceWorkflowTransactionFake implements BookingTransaction
{
    private bool $active = false;

    public function begin(): void
    {
        if ($this->active) {
            throw new RuntimeException('Transaction already active.');
        }

        $this->active = true;
    }

    public function commit(): void
    {
        if (!$this->active) {
            throw new RuntimeException('No transaction.');
        }

        $this->active = false;
    }

    public function rollBack(): void
    {
        if (!$this->active) {
            throw new RuntimeException('No transaction.');
        }

        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
