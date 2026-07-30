<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorPaymentMethodController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Models\PaymentMethod;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\PaymentMethodFlashMessages;
use App\Services\PaymentMethodManagementService;
use App\Services\PaymentMethodManagementServiceProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorPaymentMethodManagementTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;

    private AdministratorSession $administratorSession;

    private CsrfTokenManager $csrfTokens;

    private Application $application;

    private PaymentMethodManagementRepositoryFake $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-payment-method-management-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession(
            $this->session,
            $this->csrfTokens,
        );
        $this->repository = new PaymentMethodManagementRepositoryFake();
        $logger = new NullLogger();
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2)
                . DIRECTORY_SEPARATOR
                . 'resources'
                . DIRECTORY_SEPARATOR
                . 'views',
        );
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new PaymentMethodManagementAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $paymentMethodController = new AdministratorPaymentMethodController(
            $renderer,
            new PaymentMethodManagementServiceProvider(
                fn (): PaymentMethodManagementService => new PaymentMethodManagementService(
                    $this->repository,
                ),
            ),
            $this->csrfTokens,
            new PaymentMethodFlashMessages($this->session),
        );
        $router = new Router();
        $registrar = require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'routes'
            . DIRECTORY_SEPARATOR
            . 'admin.php';
        $registrar(
            $router,
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
            $paymentMethodController,
        );
        $this->application = new Application(
            $router,
            new ErrorHandler($logger, false),
            $this->session,
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
                unlink($file);
            }
        }

        if (is_dir($this->savePath)) {
            rmdir($this->savePath);
        }

        parent::tearDown();
    }

    public function testGuestCannotOpenOrSubmitPaymentMethodManagement(): void
    {
        $token = $this->csrfTokens->token();

        foreach (['/admin/payment-methods', '/admin/payment-methods/create'] as $path) {
            $response = $this->application->handle(Request::create('GET', $path));

            self::assertSame(302, $response->statusCode());
            self::assertSame('/admin/login', $response->headers()['Location']);
            $this->reopenSession();
        }

        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/payment-methods',
            input: [...$this->validInput(), '_csrf' => $token],
        ));

        self::assertSame(302, $response->statusCode());
        self::assertSame('/admin/login', $response->headers()['Location']);
        self::assertSame(0, $this->repository->createCalls);
    }

    public function testIndexRendersEscapedConfigurationWithoutDisclosingQrPath(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-28 10:11:12 UTC');
        $this->repository->items[7] = new PaymentMethod(
            7,
            '<script>Bank</script>',
            'Transfer instructions.',
            '<b>BANK-001</b>',
            'payment-methods/private-qr.png',
            true,
            true,
            $timestamp,
            $timestamp,
        );
        $this->authenticate();
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(
            Request::create('GET', '/admin/payment-methods'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString(
            '<title>Payment methods | Tara Guest House</title>',
            $response->body(),
        );
        self::assertStringContainsString(
            'href="/admin/payment-methods"',
            $response->body(),
        );
        self::assertStringContainsString(
            'href="/admin/payment-methods/create"',
            $response->body(),
        );
        self::assertStringContainsString(
            'href="/admin/payment-methods/7/edit"',
            $response->body(),
        );
        self::assertStringContainsString(
            'action="/admin/payment-methods/7/deactivate"',
            $response->body(),
        );
        self::assertStringContainsString(
            'name="_csrf" value="' . $token . '"',
            $response->body(),
        );
        self::assertStringContainsString(
            '&lt;script&gt;Bank&lt;/script&gt;',
            $response->body(),
        );
        self::assertStringNotContainsString(
            '<script>Bank</script>',
            $response->body(),
        );
        self::assertStringContainsString(
            '&lt;b&gt;BANK-001&lt;/b&gt;',
            $response->body(),
        );
        self::assertStringNotContainsString(
            'payment-methods/private-qr.png',
            $response->body(),
        );
        self::assertStringContainsString('Required', $response->body());
        self::assertStringContainsString('Configured', $response->body());
        self::assertStringContainsString('Active', $response->body());
        self::assertSame(
            1,
            substr_count($response->body(), 'aria-current="page"'),
        );
    }

    public function testCreateFormUsesPostOnlyCsrfProtectedDefaultsWithoutQrInput(): void
    {
        $this->authenticate();
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(
            Request::create('GET', '/admin/payment-methods/create'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString(
            '<title>Create payment method | Tara Guest House</title>',
            $response->body(),
        );
        self::assertStringContainsString(
            'method="post" action="/admin/payment-methods"',
            $response->body(),
        );
        self::assertStringContainsString(
            'name="_csrf" value="' . $token . '"',
            $response->body(),
        );
        self::assertStringContainsString('name="requires_proof"', $response->body());
        self::assertStringContainsString('name="active"', $response->body());
        self::assertStringNotContainsString('name="qr_image_path"', $response->body());
        self::assertSame(2, substr_count($response->body(), 'checked'));
    }

    public function testInvalidCreateReturns422AndPreservesEscapedInputWithoutWriting(): void
    {
        $this->authenticate();
        $input = $this->validInput();
        $input['name'] = '<script>Unsafe</script>';
        $input['account_reference'] = "BANK\n001";
        $input['_csrf'] = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/payment-methods',
            input: $input,
        ));

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString(
            'Account reference is invalid.',
            $response->body(),
        );
        self::assertStringContainsString(
            '&lt;script&gt;Unsafe&lt;/script&gt;',
            $response->body(),
        );
        self::assertStringNotContainsString(
            '<script>Unsafe</script>',
            $response->body(),
        );
        self::assertSame(0, $this->repository->createCalls);
    }

    public function testSuccessfulCreateRedirectsAndShowsOneTimeNotice(): void
    {
        $this->authenticate();

        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/payment-methods',
            input: [
                ...$this->validInput(),
                '_csrf' => $this->csrfTokens->token(),
            ],
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/payment-methods', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(1, $this->repository->createCalls);
        self::assertNull($this->repository->lastCreatedQrImagePath);

        $this->reopenSession();
        $firstIndex = $this->application->handle(
            Request::create('GET', '/admin/payment-methods'),
        );
        self::assertStringContainsString(
            'Payment method created successfully.',
            $firstIndex->body(),
        );
        self::assertStringContainsString('Bank transfer', $firstIndex->body());

        $this->reopenSession();
        $secondIndex = $this->application->handle(
            Request::create('GET', '/admin/payment-methods'),
        );
        self::assertStringNotContainsString(
            'Payment method created successfully.',
            $secondIndex->body(),
        );
    }

    public function testEditEscapesStoredValuesAndUpdatePreservesExistingQrPath(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-28 10:11:12 UTC');
        $this->repository->items[7] = new PaymentMethod(
            7,
            '<b>Existing</b>',
            "Line one\n<script>Line two</script>",
            '<em>ACCOUNT</em>',
            'payment-methods/existing.png',
            true,
            false,
            $timestamp,
            $timestamp,
        );
        $this->authenticate();

        $formResponse = $this->application->handle(
            Request::create('GET', '/admin/payment-methods/7/edit'),
        );

        self::assertSame(200, $formResponse->statusCode());
        self::assertStringContainsString(
            'action="/admin/payment-methods/7"',
            $formResponse->body(),
        );
        self::assertStringContainsString(
            'value="&lt;b&gt;Existing&lt;/b&gt;"',
            $formResponse->body(),
        );
        self::assertStringContainsString(
            '&lt;script&gt;Line two&lt;/script&gt;',
            $formResponse->body(),
        );
        self::assertStringContainsString(
            'An existing QR image is configured and will be preserved.',
            $formResponse->body(),
        );
        self::assertStringNotContainsString(
            'payment-methods/existing.png',
            $formResponse->body(),
        );

        $this->reopenSession();
        $input = $this->validInput();
        $input['name'] = 'Updated transfer';
        $input['requires_proof'] = '0';
        $input['_csrf'] = $this->csrfTokens->token();
        $updateResponse = $this->application->handle(Request::create(
            'POST',
            '/admin/payment-methods/7',
            input: $input,
        ));

        self::assertSame(303, $updateResponse->statusCode());
        self::assertSame('/admin/payment-methods', $updateResponse->headers()['Location']);
        self::assertSame(1, $this->repository->updateCalls);
        self::assertSame(
            'payment-methods/existing.png',
            $this->repository->lastUpdatedQrImagePath,
        );
        self::assertSame('Updated transfer', $this->repository->items[7]->name());
        self::assertFalse($this->repository->items[7]->requiresProof());
    }

    public function testMissingOrMalformedEditTargetsReturn404WithoutDisclosure(): void
    {
        $this->authenticate();

        $missing = $this->application->handle(
            Request::create('GET', '/admin/payment-methods/999/edit'),
        );
        self::assertSame(404, $missing->statusCode());
        self::assertSame('Not Found', $missing->body());
        self::assertSame('no-store', $missing->headers()['Cache-Control']);

        $this->reopenSession();
        $malformed = $this->application->handle(
            Request::create('GET', '/admin/payment-methods/not-a-number/edit'),
        );
        self::assertSame(404, $malformed->statusCode());
        self::assertSame('Not Found', $malformed->body());
    }

    public function testNoPhysicalDeleteRouteIsRegistered(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-28 10:11:12 UTC');
        $this->repository->items[7] = new PaymentMethod(
            7,
            'Bank transfer',
            null,
            null,
            null,
            true,
            false,
            $timestamp,
            $timestamp,
        );
        $this->authenticate();

        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/payment-methods/7/delete',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));

        self::assertSame(404, $response->statusCode());
        self::assertArrayHasKey(7, $this->repository->items);
    }

    public function testActivateAndDeactivateArePostOnlyCsrfProtectedActions(): void
    {
        $timestamp = new DateTimeImmutable('2026-07-28 10:11:12 UTC');
        $this->repository->items[4] = new PaymentMethod(
            4,
            'Cash deposit',
            null,
            null,
            null,
            false,
            false,
            $timestamp,
            $timestamp,
        );
        $this->authenticate();

        $getResponse = $this->application->handle(
            Request::create('GET', '/admin/payment-methods/4/activate'),
        );
        self::assertSame(405, $getResponse->statusCode());
        self::assertSame('POST', $getResponse->headers()['Allow']);

        $this->reopenSession();
        $forbidden = $this->application->handle(
            Request::create('POST', '/admin/payment-methods/4/activate'),
        );
        self::assertSame(403, $forbidden->statusCode());
        self::assertSame(0, $this->repository->setActiveCalls);

        $this->reopenSession();
        $activate = $this->application->handle(Request::create(
            'POST',
            '/admin/payment-methods/4/activate',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(303, $activate->statusCode());
        self::assertTrue($this->repository->items[4]->isActive());

        $this->reopenSession();
        $deactivate = $this->application->handle(Request::create(
            'POST',
            '/admin/payment-methods/4/deactivate',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(303, $deactivate->statusCode());
        self::assertFalse($this->repository->items[4]->isActive());
        self::assertSame(2, $this->repository->setActiveCalls);
    }

    private function authenticate(): void
    {
        $this->administratorSession->establish(new AdministratorIdentity(91));
    }

    private function reopenSession(): void
    {
        $this->session = Session::start($this->sessionConfiguration);
    }

    /** @return array<string, string> */
    private function validInput(): array
    {
        return [
            'name' => 'Bank transfer',
            'instructions' => 'Transfer the exact booking total.',
            'account_reference' => 'BANK-001',
            'requires_proof' => '1',
            'active' => '1',
        ];
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

final class PaymentMethodManagementRepositoryFake implements PaymentMethodCatalogRepository
{
    /** @var array<int, PaymentMethod> */
    public array $items = [];

    public int $createCalls = 0;

    public int $updateCalls = 0;

    public int $setActiveCalls = 0;

    public ?string $lastCreatedQrImagePath = null;

    public ?string $lastUpdatedQrImagePath = null;

    public function findById(int $id): ?PaymentMethod
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        ksort($this->items);

        return array_values($this->items);
    }

    public function findAllActive(): array
    {
        return array_values(array_filter(
            $this->findAll(),
            static fn (PaymentMethod $method): bool => $method->isActive(),
        ));
    }

    public function create(
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): PaymentMethod {
        $this->createCalls++;
        $this->lastCreatedQrImagePath = $qrImagePath;
        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;
        $timestamp = new DateTimeImmutable('2026-07-28 10:11:12 UTC');
        $method = new PaymentMethod(
            $id,
            $name,
            $instructions,
            $accountReference,
            $qrImagePath,
            $requiresProof,
            $active,
            $timestamp,
            $timestamp,
        );
        $this->items[$id] = $method;

        return $method;
    }

    public function update(
        int $id,
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): ?PaymentMethod {
        $this->updateCalls++;
        $this->lastUpdatedQrImagePath = $qrImagePath;
        $current = $this->items[$id] ?? null;

        if (!$current instanceof PaymentMethod) {
            return null;
        }

        $method = new PaymentMethod(
            $id,
            $name,
            $instructions,
            $accountReference,
            $qrImagePath,
            $requiresProof,
            $active,
            $current->createdAt(),
            new DateTimeImmutable('2026-07-28 10:12:12 UTC'),
        );
        $this->items[$id] = $method;

        return $method;
    }

    public function setActive(int $id, bool $active): ?PaymentMethod
    {
        $this->setActiveCalls++;
        $current = $this->items[$id] ?? null;

        if (!$current instanceof PaymentMethod) {
            return null;
        }

        $method = new PaymentMethod(
            $id,
            $current->name(),
            $current->instructions(),
            $current->accountReference(),
            $current->qrImagePath(),
            $current->requiresProof(),
            $active,
            $current->createdAt(),
            new DateTimeImmutable('2026-07-28 10:12:12 UTC'),
        );
        $this->items[$id] = $method;

        return $method;
    }
}

final class PaymentMethodManagementAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
