<?php

declare(strict_types=1);

namespace App\Core;

use App\Authentication\AdministratorSession;
use App\Authentication\LoginRateLimiter;
use App\Controllers\AdministratorAmenityController;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Controllers\AdministratorRoomController;
use App\Controllers\AdministratorRoomImageController;
use App\Controllers\AdministratorRoomTypeController;
use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicBookingController;
use App\Controllers\PublicBookingPageController;
use App\Controllers\PublicRoomController;
use App\Exceptions\ConfigurationException;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\AmenityRepository;
use App\Repositories\PdoAvailabilityRepository;
use App\Repositories\PdoBookingWriteRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTypeRepository;
use App\Repositories\UserRepository;
use App\Services\AdministratorAuthenticationService;
use App\Services\AvailabilityService;
use App\Services\BookingAccessToken;
use App\Services\BookingCreationService;
use App\Services\BookingDeadlinePolicy;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingService;
use App\Services\BookingSubmissionService;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSuccessFlash;
use App\Services\AdministratorLogoutService;
use App\Services\LazyAdministratorAuthenticator;
use App\Services\AmenityCatalogService;
use App\Services\PdoBookingTransaction;
use App\Services\PdoRoomCatalogTransaction;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogService;
use App\Services\RoomCatalogServiceProvider;
use App\Services\RoomImageServiceProvider;
use App\Presentation\RoomImageUrl;
use App\Services\RoomTypeCatalogService;
use App\Services\PublicAvailabilityService;
use App\Services\PublicAvailabilityServiceProvider;
use App\Services\PublicBookingRateLimiter;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;
use App\Repositories\RoomImageRepository;

/**
 * Builds the approved core application runtime.
 */
final class Bootstrap
{
    private const ROUTE_FILES = ['web.php', 'admin.php', 'api.php'];

    /**
     * Boots the application from one validated repository root.
     */
    public static function boot(string $basePath): Application
    {
        $resolvedBasePath = realpath($basePath);

        if ($resolvedBasePath === false || !is_dir($resolvedBasePath)) {
            throw new ConfigurationException('The supplied application base path is invalid.');
        }

        if (!is_file($resolvedBasePath . DIRECTORY_SEPARATOR . 'composer.json')) {
            throw new ConfigurationException('The supplied path is not a valid application repository.');
        }

        foreach (['app', 'config', 'routes', 'public', 'storage'] as $requiredDirectory) {
            if (!is_dir($resolvedBasePath . DIRECTORY_SEPARATOR . $requiredDirectory)) {
                throw new ConfigurationException('The supplied path is not a valid application repository.');
            }
        }

        self::definePaths($resolvedBasePath);

        $environment = Environment::load(BASE_PATH);
        $config = Config::load(CONFIG_PATH, $environment);
        $timezone = $config->require('app.timezone');

        if (!is_string($timezone) || !date_default_timezone_set($timezone)) {
            throw new ConfigurationException('The configured application timezone is invalid.');
        }

        $logger = LoggerFactory::create($config);
        $errorHandler = new ErrorHandler($logger, $config->require('app.debug') === true);
        $errorHandler->register();
        $sessionConfiguration = $config->require('session');

        if (!is_array($sessionConfiguration)) {
            throw new ConfigurationException('The configured session settings are invalid.');
        }

        $session = Session::start($sessionConfiguration);

        $viewRenderer = new ViewRenderer(
            $resolvedBasePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $csrfTokens = new CsrfTokenManager($session);
        $administratorSession = new AdministratorSession($session, $csrfTokens);
        $databaseConfiguration = $config->require('database');
        $loginPolicy = $config->require('security.administrator_login');
        $publicBookingPolicy = $config->require('security.public_booking');
        $bookingConfiguration = $config->require('booking');

        if (
            !is_array($databaseConfiguration)
            || !is_array($loginPolicy)
            || !is_array($publicBookingPolicy)
            || !is_array($bookingConfiguration)
        ) {
            throw new ConfigurationException('Application service configuration is invalid.');
        }

        $connectionFactory = new ConnectionFactory($databaseConfiguration);
        $authenticator = new LazyAdministratorAuthenticator(
            static fn (): AdministratorAuthenticationService => new AdministratorAuthenticationService(
                new UserRepository($connectionFactory->create()),
                new LoginRateLimiter(
                    STORAGE_PATH
                        . DIRECTORY_SEPARATOR
                        . 'cache'
                        . DIRECTORY_SEPARATOR
                        . 'login-rate-limits'
                        . DIRECTORY_SEPARATOR
                        . 'administrator',
                    $loginPolicy,
                ),
                $logger,
            ),
        );
        $controller = new AdministratorAuthenticationController(
            $viewRenderer,
            $authenticator,
            new AdministratorLogoutService($administratorSession, $logger),
            $administratorSession,
            $csrfTokens,
            $logger,
            $loginPolicy,
        );
        $catalogConnection = null;
        $catalogConnectionProvider = static function () use ($connectionFactory, &$catalogConnection): \PDO {
            $catalogConnection ??= $connectionFactory->create();

            return $catalogConnection;
        };
        $catalogServices = new RoomCatalogServiceProvider(
            static fn (): RoomTypeCatalogService => new RoomTypeCatalogService(
                new RoomTypeRepository($catalogConnectionProvider()),
            ),
            static fn (): AmenityCatalogService => new AmenityCatalogService(
                new AmenityRepository($catalogConnectionProvider()),
            ),
            static fn (): RoomCatalogService => new RoomCatalogService(
                new RoomRepository($catalogConnectionProvider()),
                new RoomTypeRepository($catalogConnectionProvider()),
                new AmenityRepository($catalogConnectionProvider()),
                new PdoRoomCatalogTransaction($catalogConnectionProvider()),
            ),
        );
        $catalogFlashMessages = new RoomCatalogFlashMessages($session);
        $roomImageServices = new RoomImageServiceProvider($catalogConnectionProvider, $resolvedBasePath);
        $catalogController = new AdministratorRoomCatalogController(
            $viewRenderer,
            $catalogServices,
            $csrfTokens,
            $catalogFlashMessages,
            $roomImageServices->service(...),
            new RoomImageUrl(),
        );
        $roomController = new AdministratorRoomController(
            $viewRenderer,
            $catalogServices,
            $csrfTokens,
            $catalogFlashMessages,
            $roomImageServices->service(...),
            new RoomImageUrl(),
        );
        $roomTypeController = new AdministratorRoomTypeController(
            $viewRenderer,
            $catalogServices,
            $csrfTokens,
            $catalogFlashMessages,
        );
        $amenityController = new AdministratorAmenityController(
            $viewRenderer,
            $catalogServices,
            $csrfTokens,
            $catalogFlashMessages,
        );
        $roomImageController = new AdministratorRoomImageController(
            $roomImageServices->service(...),
            $roomImageServices->uploadConfiguration(...),
            $catalogFlashMessages,
        );
        $publicRoomController = new PublicRoomController(
            $viewRenderer,
            new PublicRoomCatalogServiceProvider(static fn (): PublicRoomCatalogService => new PublicRoomCatalogService(
                new RoomRepository($catalogConnectionProvider()), new RoomImageRepository($catalogConnectionProvider()), new RoomImageUrl(),
            ),
            ),
        );
        $publicAvailabilityController = new PublicAvailabilityController(
            new PublicAvailabilityServiceProvider(
                static fn (): PublicAvailabilityService => new PublicAvailabilityService(
                    new AvailabilityService(
                        new PdoAvailabilityRepository($catalogConnectionProvider()),
                    ),
                    new RoomRepository($catalogConnectionProvider()),
                ),
            ),
            $viewRenderer,
        );
        $responseTimezone = new \DateTimeZone($timezone);
        $publicBookingWorkflows = new BookingSubmissionWorkflowProvider(
            static function () use (
                $catalogConnectionProvider,
                $bookingConfiguration,
            ): BookingSubmissionWorkflow {
                $accessTokenKey = $bookingConfiguration['access_token_key'] ?? null;
                $deadlineMinutes = $bookingConfiguration['pending_deadline_minutes'] ?? null;

                if (!is_string($accessTokenKey) || !is_int($deadlineMinutes)) {
                    throw new ConfigurationException(
                        'Public booking submission is not configured.',
                    );
                }

                $connection = $catalogConnectionProvider();
                $rooms = new RoomRepository($connection);
                $creator = new BookingCreationService(
                    $rooms,
                    new BookingService(
                        $rooms,
                        new PdoAvailabilityRepository($connection),
                    ),
                    new PdoBookingWriteRepository($connection),
                    new PdoBookingTransaction($connection),
                );

                return new BookingSubmissionWorkflow(
                    new BookingSubmissionService(
                        $creator,
                        new BookingReferenceGenerator(),
                        new BookingDeadlinePolicy($deadlineMinutes),
                    ),
                    new BookingAccessToken($accessTokenKey),
                );
            },
        );
        $publicBookingController = new PublicBookingController(
            $publicBookingWorkflows,
            $responseTimezone,
        );
        $publicBookingPageController = new PublicBookingPageController(
            $viewRenderer,
            $publicBookingWorkflows,
            new BookingSuccessFlash($session, $responseTimezone),
            $csrfTokens,
        );
        $publicBookingRateLimit = new PublicBookingRateLimitMiddleware(
            new PublicBookingRateLimiter(
                STORAGE_PATH
                    . DIRECTORY_SEPARATOR
                    . 'cache'
                    . DIRECTORY_SEPARATOR
                    . 'booking-rate-limits'
                    . DIRECTORY_SEPARATOR
                    . 'public',
                $publicBookingPolicy,
            ),
        );
        $router = new Router();
        self::loadRoutes(
            $router,
            $viewRenderer,
            $controller,
            new GuestOnlyMiddleware($administratorSession),
            new RequireAdministratorMiddleware($administratorSession),
            new CsrfMiddleware($csrfTokens),
            $catalogController,
            $roomTypeController,
            $amenityController,
            $roomController,
            $roomImageController,
            $publicRoomController,
            $publicAvailabilityController,
            $publicBookingController,
            $publicBookingPageController,
            $publicBookingRateLimit,
        );

        return new Application($router, $errorHandler, $session);
    }

    private static function definePaths(string $basePath): void
    {
        $paths = [
            'BASE_PATH' => $basePath,
            'APP_PATH' => $basePath . DIRECTORY_SEPARATOR . 'app',
            'CONFIG_PATH' => $basePath . DIRECTORY_SEPARATOR . 'config',
            'ROUTES_PATH' => $basePath . DIRECTORY_SEPARATOR . 'routes',
            'STORAGE_PATH' => $basePath . DIRECTORY_SEPARATOR . 'storage',
            'PUBLIC_PATH' => $basePath . DIRECTORY_SEPARATOR . 'public',
        ];

        foreach ($paths as $constant => $path) {
            if (defined($constant) && constant($constant) !== $path) {
                throw new ConfigurationException(sprintf('%s is already defined with another path.', $constant));
            }
        }

        foreach ($paths as $constant => $path) {
            if (!defined($constant) && !define($constant, $path)) {
                throw new ConfigurationException(sprintf('%s could not be defined.', $constant));
            }
        }
    }

    private static function loadRoutes(
        Router $router,
        ViewRenderer $viewRenderer,
        AdministratorAuthenticationController $controller,
        GuestOnlyMiddleware $guestOnly,
        RequireAdministratorMiddleware $requireAdministrator,
        CsrfMiddleware $csrf,
        AdministratorRoomCatalogController $catalogController,
        AdministratorRoomTypeController $roomTypeController,
        AdministratorAmenityController $amenityController,
        AdministratorRoomController $roomController,
        AdministratorRoomImageController $roomImageController,
        PublicRoomController $publicRoomController,
        PublicAvailabilityController $publicAvailabilityController,
        PublicBookingController $publicBookingController,
        PublicBookingPageController $publicBookingPageController,
        PublicBookingRateLimitMiddleware $publicBookingRateLimit,
    ): void {
        foreach (self::ROUTE_FILES as $routeFile) {
            $path = ROUTES_PATH . DIRECTORY_SEPARATOR . $routeFile;

            if (!is_file($path)) {
                throw new ConfigurationException(sprintf('Required route file %s is missing.', $routeFile));
            }

            $registrar = require $path;

            if (!is_callable($registrar)) {
                throw new ConfigurationException(sprintf('Route file %s must return a callable registrar.', $routeFile));
            }

            $registrar(
                $router,
                $viewRenderer,
                $controller,
                $guestOnly,
                $requireAdministrator,
                $csrf,
                $catalogController,
                $roomTypeController,
                $amenityController,
                $roomController,
                $roomImageController,
                $publicRoomController,
                $publicAvailabilityController,
                $publicBookingController,
                $publicBookingPageController,
                $publicBookingRateLimit,
            );
        }
    }
}
