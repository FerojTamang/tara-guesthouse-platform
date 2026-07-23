<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\AdministratorIdentity;
use App\Authentication\AdministratorSession;
use App\Controllers\AdministratorAmenityController;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Core\Application;
use App\Core\CsrfTokenManager;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\ViewRenderer;
use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
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
final class AdministratorAmenityManagementTest extends TestCase
{
    private string $savePath;

    /** @var array<string, mixed> */
    private array $sessionConfiguration;

    private Session $session;

    private AdministratorSession $administratorSession;

    private CsrfTokenManager $csrfTokens;

    private Application $application;

    private AmenityManagementRepositoryFake $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'tgh-admin-amenity-management-'
            . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->savePath, 0700));
        $this->sessionConfiguration = $this->configuration();
        $this->session = Session::start($this->sessionConfiguration);
        $this->csrfTokens = new CsrfTokenManager($this->session);
        $this->administratorSession = new AdministratorSession($this->session, $this->csrfTokens);
        $this->repository = new AmenityManagementRepositoryFake();
        $roomTypeRepository = $this->createStub(RoomTypeCatalogRepository::class);
        $logger = new NullLogger();
        $renderer = new ViewRenderer(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $authenticationController = new AdministratorAuthenticationController(
            $renderer,
            new AmenityManagementAuthenticatorFake(),
            new AdministratorLogoutService($this->administratorSession, $logger),
            $this->administratorSession,
            $this->csrfTokens,
            $logger,
            self::loginPolicy(),
        );
        $services = new RoomCatalogServiceProvider(
            fn (): RoomTypeCatalogService => new RoomTypeCatalogService($roomTypeRepository),
            fn (): AmenityCatalogService => new AmenityCatalogService($this->repository),
        );
        $flashMessages = new RoomCatalogFlashMessages($this->session);
        $catalogController = new AdministratorRoomCatalogController(
            $renderer,
            $services,
            $this->csrfTokens,
            $flashMessages,
        );
        $amenityController = new AdministratorAmenityController(
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
            null,
            $amenityController,
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

        $getResponse = $this->application->handle(Request::create('GET', '/admin/amenities/create'));

        self::assertSame(302, $getResponse->statusCode());
        self::assertSame('/admin/login', $getResponse->headers()['Location']);

        $this->reopenSession();
        $postResponse = $this->application->handle(Request::create(
            'POST',
            '/admin/amenities',
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

        $response = $this->application->handle(Request::create('GET', '/admin/amenities/create'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('<title>Create amenity | Tara Guest House</title>', $response->body());
        self::assertStringContainsString('method="post" action="/admin/amenities"', $response->body());
        self::assertStringContainsString('name="_csrf" value="' . $token . '"', $response->body());
        self::assertStringContainsString('name="icon"', $response->body());
        self::assertStringContainsString('name="active"', $response->body());
        self::assertStringContainsString('checked', $response->body());
    }

    public function testInvalidCreateReturns422AndPreservesEscapedInputWithoutWriting(): void
    {
        $this->authenticate();
        $input = $this->validInput();
        $input['name'] = '<script>Unsafe</script>';
        $input['icon'] = 'Invalid Icon';
        $input['_csrf'] = $this->csrfTokens->token();

        $response = $this->application->handle(Request::create('POST', '/admin/amenities', input: $input));

        self::assertSame(422, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Icon identifier is invalid.', $response->body());
        self::assertStringContainsString('&lt;script&gt;Unsafe&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>Unsafe</script>', $response->body());
        self::assertSame(0, $this->repository->createCalls);
    }

    public function testDuplicateCreateReturns409WithSafeConflictAndNormalizedValues(): void
    {
        $this->authenticate();
        $this->repository->createFailure = RoomCatalogException::duplicateAmenityName();
        $input = [...$this->validInput(), '_csrf' => $this->csrfTokens->token()];

        $response = $this->application->handle(Request::create('POST', '/admin/amenities', input: $input));

        self::assertSame(409, $response->statusCode());
        self::assertStringContainsString('An amenity with this name already exists.', $response->body());
        self::assertStringContainsString('value="wifi-router"', $response->body());
        self::assertSame(1, $this->repository->createCalls);
    }

    public function testSuccessfulCreateRedirectsAndShowsOneTimeNotice(): void
    {
        $this->authenticate();
        $response = $this->application->handle(Request::create(
            'POST',
            '/admin/amenities',
            input: [...$this->validInput(), '_csrf' => $this->csrfTokens->token()],
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/amenities', $response->headers()['Location']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame(1, $this->repository->createCalls);

        $this->reopenSession();
        $firstIndex = $this->application->handle(Request::create('GET', '/admin/amenities'));
        self::assertStringContainsString('Amenity created successfully.', $firstIndex->body());
        self::assertStringContainsString('Wi-Fi', $firstIndex->body());

        $this->reopenSession();
        $secondIndex = $this->application->handle(Request::create('GET', '/admin/amenities'));
        self::assertStringNotContainsString('Amenity created successfully.', $secondIndex->body());
    }

    public function testEditFormEscapesStoredValuesAndUpdatePersistsValidatedInput(): void
    {
        $this->repository->items[7] = self::amenity(7, '<b>Existing</b>', 'car-front', false);
        $this->authenticate();

        $formResponse = $this->application->handle(Request::create('GET', '/admin/amenities/7/edit'));

        self::assertSame(200, $formResponse->statusCode());
        self::assertStringContainsString('action="/admin/amenities/7"', $formResponse->body());
        self::assertStringContainsString('value="&lt;b&gt;Existing&lt;/b&gt;"', $formResponse->body());
        self::assertStringContainsString('value="car-front"', $formResponse->body());
        self::assertStringNotContainsString('<b>Existing</b>', $formResponse->body());

        $this->reopenSession();
        $input = $this->validInput();
        $input['name'] = 'Updated amenity';
        $input['icon'] = '';
        $input['active'] = '0';
        $input['_csrf'] = $this->csrfTokens->token();
        $updateResponse = $this->application->handle(Request::create(
            'POST',
            '/admin/amenities/7',
            input: $input,
        ));

        self::assertSame(303, $updateResponse->statusCode());
        self::assertSame('/admin/amenities', $updateResponse->headers()['Location']);
        self::assertSame(1, $this->repository->updateCalls);
        self::assertSame('Updated amenity', $this->repository->items[7]->name());
        self::assertNull($this->repository->items[7]->icon());
        self::assertFalse($this->repository->items[7]->isActive());
    }

    public function testMissingOrMalformedEditTargetsReturn404WithoutDisclosure(): void
    {
        $this->authenticate();

        $missing = $this->application->handle(Request::create('GET', '/admin/amenities/999/edit'));
        self::assertSame(404, $missing->statusCode());
        self::assertSame('Not Found', $missing->body());
        self::assertSame('no-store', $missing->headers()['Cache-Control']);

        $this->reopenSession();
        $malformed = $this->application->handle(Request::create('GET', '/admin/amenities/not-a-number/edit'));
        self::assertSame(404, $malformed->statusCode());
        self::assertSame('Not Found', $malformed->body());
    }

    public function testActivateAndDeactivateArePostOnlyCsrfProtectedActions(): void
    {
        $this->repository->items[4] = self::amenity(4, 'Breakfast', null, false);
        $this->authenticate();

        $getResponse = $this->application->handle(Request::create('GET', '/admin/amenities/4/activate'));
        self::assertSame(405, $getResponse->statusCode());
        self::assertSame('POST', $getResponse->headers()['Allow']);

        $this->reopenSession();
        $forbidden = $this->application->handle(Request::create('POST', '/admin/amenities/4/activate'));
        self::assertSame(403, $forbidden->statusCode());
        self::assertSame(0, $this->repository->setActiveCalls);

        $this->reopenSession();
        $activate = $this->application->handle(Request::create(
            'POST',
            '/admin/amenities/4/activate',
            input: ['_csrf' => $this->csrfTokens->token()],
        ));
        self::assertSame(303, $activate->statusCode());
        self::assertTrue($this->repository->items[4]->isActive());

        $this->reopenSession();
        $deactivate = $this->application->handle(Request::create(
            'POST',
            '/admin/amenities/4/deactivate',
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
            'name' => 'Wi-Fi',
            'icon' => 'wifi-router',
            'active' => '1',
        ];
    }

    private static function amenity(int $id, string $name, ?string $icon, bool $active): Amenity
    {
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');

        return new Amenity($id, $name, $icon, $active, $timestamp, $timestamp);
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

final class AmenityManagementRepositoryFake implements AmenityCatalogRepository
{
    /** @var array<int, Amenity> */
    public array $items = [];

    public int $createCalls = 0;

    public int $updateCalls = 0;

    public int $setActiveCalls = 0;

    public ?RoomCatalogException $createFailure = null;

    public function findById(int $id): ?Amenity
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
            static fn (Amenity $amenity): bool => $amenity->isActive(),
        ));
    }

    public function create(string $name, ?string $icon, bool $active): Amenity
    {
        $this->createCalls++;

        if ($this->createFailure !== null) {
            throw $this->createFailure;
        }

        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;
        $timestamp = new DateTimeImmutable('2026-07-22 10:11:12 UTC');
        $amenity = new Amenity($id, $name, $icon, $active, $timestamp, $timestamp);
        $this->items[$id] = $amenity;

        return $amenity;
    }

    public function update(int $id, string $name, ?string $icon, bool $active): ?Amenity
    {
        $this->updateCalls++;
        $existing = $this->items[$id] ?? null;

        if (!$existing instanceof Amenity) {
            return null;
        }

        $updated = new Amenity(
            $id,
            $name,
            $icon,
            $active,
            $existing->createdAt(),
            new DateTimeImmutable('2026-07-22 10:12:12 UTC'),
        );
        $this->items[$id] = $updated;

        return $updated;
    }

    public function setActive(int $id, bool $active): ?Amenity
    {
        $this->setActiveCalls++;
        $existing = $this->items[$id] ?? null;

        if (!$existing instanceof Amenity) {
            return null;
        }

        $updated = new Amenity(
            $id,
            $existing->name(),
            $existing->icon(),
            $active,
            $existing->createdAt(),
            new DateTimeImmutable('2026-07-22 10:12:12 UTC'),
        );
        $this->items[$id] = $updated;

        return $updated;
    }
}

final class AmenityManagementAuthenticatorFake implements AdministratorAuthenticator
{
    public function authenticate(
        AdministratorLoginRequest $request,
        string $remoteAddress,
        string $context = 'http',
    ): AdministratorIdentity {
        return new AdministratorIdentity(91);
    }
}
