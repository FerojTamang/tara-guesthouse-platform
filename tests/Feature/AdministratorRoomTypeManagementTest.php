<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Controllers\AdministratorRoomTypeController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Exceptions\RoomCatalogException;
use App\Models\RoomType;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\AmenityCatalogRepository;
use App\Repositories\RoomTypeCatalogRepository;
use App\Requests\AdministratorLoginRequest;
use App\Services\AdministratorAuthenticator;
use App\Services\AdministratorLogoutService;
use App\Services\AmenityCatalogService;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogServiceProvider;
use App\Services\RoomTypeCatalogService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdministratorRoomTypeManagementTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;

    private AdministratorSession $administratorSession;

    private CsrfTokenManager $csrfTokens;

    private Application $application;

    private RoomTypeManagementRepositoryFake $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-room-type-management-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->repository = new RoomTypeManagementRepositoryFake();
        $amenityRepository = $this->createStub(AmenityCatalogRepository::class);
        $logger = new NullLogger();
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new RoomTypeManagementAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $services = new RoomCatalogServiceProvider(
            fn (): RoomTypeCatalogService => new RoomTypeCatalogService($this->repository),
            fn (): AmenityCatalogService => new AmenityCatalogService($amenityRepository),
        );
        $flashMessages = new RoomCatalogFlashMessages($this->session);
        $catalogController = new AdministratorRoomCatalogController(
            $renderer,
            $services,
            $this->csrfTokens,
            $flashMessages,
        );
        $roomTypeController = new AdministratorRoomTypeController(
            $renderer,
            $services,
            $this->csrfTokens,
            $flashMessages,
        );
        $router = new Router();
        $registrar = require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'admin.php';
        $registrar(
            $router,
            $renderer,
            $authenticationController,
            new GuestOnlyMiddleware($this->administratorSession),
            new RequireAdministratorMiddleware($this->administratorSession),
            new CsrfMiddleware($this->csrfTokens),
            $catalogController,
            $roomTypeController,
        );
        $this->application = new Application($router, new ErrorHandler($logger, false), $this->session);
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

    public function testGuestCannotOpenOrSubmitTheCreateWorkflow(): void
    {
        $token = $this->csrfTokens->token();

        $getResponse = $this->application->handle(Request::create('GET', '/admin/room-types/create'));

        self::assertSame(302, $getResponse->statusCode());
        self::assertSame('/admin/login', $getResponse->headers()['Location']);

        $this->reopenSession();
        $postResponse = $this->application->handle(Request::create(
            'POST',
            '/admin/room-types',
            input: [...$this->validInput(), '_csrf' => $token],
        ));

        self::assertSame(302, $postResponse->statusCode());
        self::assertSame('/admin/login', $postResponse->headers()['Location']);
        self::assertSame(0, $this->repository->createCalls);
    }

    public function testCreateFormRendersPostOnlyCsrfProtectedDefaults(): void
    {
        $this->authenticate();
        $token = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('GET', '/admin/room-types/create'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Create room type | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('method="post" action="/admin/room-types"', $response->body());
        self::assertStringContainsString('name="_csrf" value="' . $token . '"', $response->body());
        self::assertStringContainsString('name="capacity_adults"', $response->body());
        self::assertStringContainsString('name="active"', $response->body());
        self::assertStringContainsString('checked', $response->body());
    }

    public function testInvalidCreateReturns422AndPreservesEscapedInputWithoutWriting(): void
    {
        $this->authenticate();
        $input = $this->validInput();
        $input['name'] = '<script>Unsafe</script>';
        $input['slug'] = 'Invalid Slug';
        $input['_csrf'] = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('POST', '/admin/room-types', input: $input));

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Slug is invalid.', $response->body());
        self::assertStringContainsString('&lt;script&gt;Unsafe&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Unsafe</script>', $response->body());
        self::assertSame(0, $this->repository->createCalls);
    }

    public function testDuplicateCreateReturns409WithSafeConflictAndNormalizedValues(): void
    {
        $this->authenticate();
        $this->repository->createFailure = RoomCatalogException::duplicateRoomTypeSlug();
        $input = [...$this->validInput(), '_csrf' => $this->csrfTokens->token()];

        $response = $this->application->handle(Request::create('POST', '/admin/room-types', input: $input));

        self::assertSame(409, $response->statusCode());
        self::assertStringContainsString('A room type with this slug already exists.', $response->body());
        self::assertStringContainsString('value="4500.00"', $response->body());
        self::assertSame(1, $this->repository->createCalls);
    }

    public function testSuccessfulCreateRedirectsAndShowsOneTimeNotice(): void
    {
        $this->authenticate();
        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/room-types',
            input: [...$this->validInput(), '_csrf' => $this->csrfTokens->token()],
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/room-types', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(1, $this->repository->createCalls);

        $this->reopenSession();
        $firstIndex = $this->application->handle(Request::create('GET', '/admin/room-types'));
        self::assertStringContainsString('Room type created successfully.', $firstIndex->body());
        self::assertStringContainsString('Deluxe room', $firstIndex->body());

        $this->reopenSession();
        $secondIndex = $this->application->handle(Request::create('GET', '/admin/room-types'));
        self::assertStringNotContainsString('Room type created successfully.', $secondIndex->body());
    }

    public function testEditFormEscapesStoredValuesAndUpdatePersistsValidatedInput(): void
    {
        $this->repository->items[7] = self::roomType(
            7,
            '<b>Existing</b>',
            'existing-room',
            "Line one\n<script>Line two</script>",
            '3000.00',
            2,
            0,
            false,
        );
        $this->authenticate();

        $formResponse = $this->application->handle(Request::create('GET', '/admin/room-types/7/edit'));

        self::assertSame(200, $formResponse->statusCode());
        self::assertStringContainsString('action="/admin/room-types/7"', $formResponse->body());
        self::assertStringContainsString('value="&lt;b&gt;Existing&lt;/b&gt;"', $formResponse->body());
        self::assertStringContainsString('&lt;script&gt;Line two&lt;/script&gt;', $formResponse->body());
        self::assertStringNotContainsString('<script>Line two</script>', $formResponse->body());

        $this->reopenSession();
        $input = $this->validInput();
        $input['name'] = 'Updated room';
        $input['active'] = '0';
        $input['_csrf'] = $this->csrfTokens->token();
        $updateResponse = $this->application->handle(Request::create(
            'POST',
            '/admin/room-types/7',
            input: $input,
        ));

        self::assertSame(303, $updateResponse->statusCode());
        self::assertSame('/admin/room-types', $updateResponse->headers()['Location']);
        self::assertSame(1, $this->repository->updateCalls);
        self::assertSame('Updated room', $this->repository->items[7]->name());
        self::assertFalse($this->repository->items[7]->isActive());
    }

    public function testMissingOrMalformedEditTargetsReturn404WithoutDisclosure(): void
    {
        $this->authenticate();

        $missing = $this->application->handle(Request::create('GET', '/admin/room-types/999/edit'));
        self::assertSame(404, $missing->statusCode());
        self::assertSame('Not Found', $missing->body());
        self::assertSame('no-store', $missing->headers()['Cache-Control']);

        $this->reopenSession();
        $malformed = $this->application->handle(Request::create('GET', '/admin/room-types/not-a-number/edit'));
        self::assertSame(404, $malformed->statusCode());
        self::assertSame('Not Found', $malformed->body());
    }

    public function testActivateAndDeactivateArePostOnlyCsrfProtectedActions(): void
    {
        $this->repository->items[4] = self::roomType(4, 'Standard', 'standard', null, '2500.00', 1, 0, false);
        $this->authenticate();

        $getResponse = $this->application->handle(Request::create('GET', '/admin/room-types/4/activate'));
        self::assertSame(405, $getResponse->statusCode());
        self::assertSame('POST', $getResponse->headers()['Allow']);

        $this->reopenSession();
        $forbidden = $this->application->handle(Request::create('POST', '/admin/room-types/4/activate'));
        self::assertSame(403, $forbidden->statusCode());
        self::assertSame(0, $this->repository->setActiveCalls);

        $this->reopenSession();
        $activate = $this->application->handle(Request::create(
            'POST',
            '/admin/room-types/4/activate',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(303, $activate->statusCode());
        self::assertTrue($this->repository->items[4]->isActive());

        $this->reopenSession();
        $deactivate = $this->application->handle(Request::create(
            'POST',
            '/admin/room-types/4/deactivate',
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
            'name' => 'Deluxe room',
            'slug' => 'deluxe-room',
            'description' => 'A comfortable room type.',
            'base_price' => '4500',
            'capacity_adults' => '2',
            'capacity_children' => '1',
            'active' => '1',
        ];
    }

    private static function roomType(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): RoomType {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new RoomType(
            $id,
            $name,
            $slug,
            $description,
            $basePrice,
            $capacityAdults,
            $capacityChildren,
            $active,
            $timestamp,
            $timestamp,
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

final class RoomTypeManagementRepositoryFake implements RoomTypeCatalogRepository
{
    /** @var array<int, RoomType> */
    public array $items = [];

    public int $createCalls = 0;

    public int $updateCalls = 0;

    public int $setActiveCalls = 0;

    public ?RoomCatalogException $createFailure = null;

    public function findById(int $id): ?RoomType
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
            static fn (RoomType $roomType): bool => $roomType->isActive(),
        ));
    }

    public function create(
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): RoomType {
        $this->createCalls++;

        if ($this->createFailure !== null) {
            throw $this->createFailure;
        }

        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');
        $roomType = new RoomType(
            $id,
            $name,
            $slug,
            $description,
            $basePrice,
            $capacityAdults,
            $capacityChildren,
            $active,
            $timestamp,
            $timestamp,
        );
        $this->items[$id] = $roomType;

        return $roomType;
    }

    public function update(
        int $id,
        string $name,
        string $slug,
        ?string $description,
        string $basePrice,
        int $capacityAdults,
        int $capacityChildren,
        bool $active,
    ): ?RoomType {
        $this->updateCalls++;
        $existing = $this->items[$id] ?? null;

        if (!$existing instanceof RoomType) {
            return null;
        }

        $updated = new RoomType(
            $id,
            $name,
            $slug,
            $description,
            $basePrice,
            $capacityAdults,
            $capacityChildren,
            $active,
            $existing->createdAt(),
            new DateTimeImmutable('2026-07-22 10:12:12 UTC'),
        );
        $this->items[$id] = $updated;

        return $updated;
    }

    public function setActive(int $id, bool $active): ?RoomType
    {
        $this->setActiveCalls++;
        $existing = $this->items[$id] ?? null;

        if (!$existing instanceof RoomType) {
            return null;
        }

        $updated = new RoomType(
            $id,
            $existing->name(),
            $existing->slug(),
            $existing->description(),
            $existing->basePrice(),
            $existing->capacityAdults(),
            $existing->capacityChildren(),
            $active,
            $existing->createdAt(),
            new DateTimeImmutable('2026-07-22 10:12:12 UTC'),
        );
        $this->items[$id] = $updated;

        return $updated;
    }
}

final class RoomTypeManagementAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
