<?php

declare(strict_types=1);

namespace App\Core;

use App\Authentication\AdministratorSession;
use App\Authentication\LoginRateLimiter;
use App\Controllers\AdministratorActivityController;
use App\Controllers\AdministratorAmenityController;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorBookingController;
use App\Controllers\AdministratorInvoiceController;
use App\Controllers\AdministratorPaymentController;
use App\Controllers\AdministratorPaymentMethodController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Controllers\AdministratorRoomController;
use App\Controllers\AdministratorRoomImageController;
use App\Controllers\AdministratorRoomTypeController;
use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicBookingController;
use App\Controllers\PublicBookingPageController;
use App\Controllers\PublicPaymentController;
use App\Controllers\PublicPaymentPageController;
use App\Controllers\PublicPaymentMethodController;
use App\Controllers\PublicRoomController;
use App\Exceptions\ConfigurationException;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Middleware\PublicPaymentRateLimitMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\ActivityRepository;
use App\Repositories\AmenityRepository;
use App\Repositories\BookingRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoAvailabilityRepository;
use App\Repositories\PdoBookingStatusWriteRepository;
use App\Repositories\PdoBookingWriteRepository;
use App\Repositories\PdoInvoiceRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PdoAdministratorPaymentRepository;
use App\Repositories\PdoPaymentWriteRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTypeRepository;
use App\Repositories\UserRepository;
use App\Services\AdministratorActivityService;
use App\Services\AdministratorAuthenticationService;
use App\Services\ActivityManagementServiceProvider;
use App\Services\AvailabilityService;
use App\Services\AdministratorBookingService;
use App\Services\AdministratorBookingServiceProvider;
use App\Services\AdministratorBookingStatusService;
use App\Services\AdministratorBookingStatusServiceProvider;
use App\Services\AdministratorInvoiceService;
use App\Services\AdministratorInvoiceServiceProvider;
use App\Services\BookingAccessToken;
use App\Services\BookingCreationService;
use App\Services\BookingDeadlinePolicy;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingService;
use App\Services\BookingSubmissionService;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSuccessFlash;
use App\Services\GuestPaymentAccessValidator;
use App\Services\GuestPaymentContinuation;
use App\Services\GuestPaymentPageService;
use App\Services\GuestPaymentPageServiceProvider;
use App\Services\GuestPaymentSubmissionService;
use App\Services\GuestPaymentSubmissionServiceProvider;
use App\Services\GuestPaymentSuccessFlash;
use App\Services\InvoiceIssueService;
use App\Services\InvoiceIssueServiceProvider;
use App\Services\AdministratorLogoutService;
use App\Services\AdministratorPaymentService;
use App\Services\AdministratorPaymentServiceProvider;
use App\Services\AdministratorPaymentStatusService;
use App\Services\AdministratorPaymentStatusServiceProvider;
use App\Services\VerifiedPaymentBookingConfirmationService;
use App\Services\LazyAdministratorAuthenticator;
use App\Services\AmenityCatalogService;
use App\Services\PdoActivityTransaction;
use App\Services\PdoBookingTransaction;
use App\Services\PdoRoomCatalogTransaction;
use App\Services\PaymentMethodFlashMessages;
use App\Services\PaymentMethodManagementService;
use App\Services\PaymentMethodManagementServiceProvider;
use App\Services\PaymentProofStorageProvider;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogService;
use App\Services\RoomCatalogServiceProvider;
use App\Services\RoomImageServiceProvider;
use App\Presentation\RoomImageUrl;
use App\Services\RoomTypeCatalogService;
use App\Services\PublicAvailabilityService;
use App\Services\PublicAvailabilityServiceProvider;
use App\Services\PublicBookingRateLimiter;
use App\Services\PublicPaymentMethodService;
use App\Services\PublicPaymentMethodServiceProvider;
use App\Services\PublicPaymentRateLimiter;
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
        $publicPaymentPolicy = $config->require('security.public_payment');
        $bookingConfiguration = $config->require('booking');

        if (
            !is_array($databaseConfiguration)
            || !is_array($loginPolicy)
            || !is_array($publicBookingPolicy)
            || !is_array($publicPaymentPolicy)
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
        $paymentProofServices = new PaymentProofStorageProvider($resolvedBasePath);
        $administratorInvoiceServices = new AdministratorInvoiceServiceProvider(
            static fn (): AdministratorInvoiceService => new AdministratorInvoiceService(
                new PdoInvoiceRepository($catalogConnectionProvider()),
            ),
        );
        $invoiceIssueServices = new InvoiceIssueServiceProvider(
            static function () use (
                $catalogConnectionProvider,
                $timezone,
            ): InvoiceIssueService {
                $connection = $catalogConnectionProvider();

                return new InvoiceIssueService(
                    new PdoInvoiceRepository($connection),
                    new PdoAuditLogRepository($connection),
                    new PdoBookingTransaction($connection),
                    new \DateTimeZone($timezone),
                );
            },
        );
        $administratorBookingController = new AdministratorBookingController(
            $viewRenderer,
            new AdministratorBookingServiceProvider(
                static fn (): AdministratorBookingService => new AdministratorBookingService(
                    new BookingRepository($catalogConnectionProvider()),
                ),
            ),
            new AdministratorBookingStatusServiceProvider(
                static function () use ($catalogConnectionProvider): AdministratorBookingStatusService {
                    $connection = $catalogConnectionProvider();

                    return new AdministratorBookingStatusService(
                        new PdoBookingStatusWriteRepository($connection),
                        new PdoAuditLogRepository($connection),
                        new PdoBookingTransaction($connection),
                    );
                },
            ),
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
            $administratorInvoiceServices,
        );
        $paymentMethodController = new AdministratorPaymentMethodController(
            $viewRenderer,
            new PaymentMethodManagementServiceProvider(
                static fn (): PaymentMethodManagementService => new PaymentMethodManagementService(
                    new PaymentMethodRepository($catalogConnectionProvider()),
                ),
            ),
            $csrfTokens,
            new PaymentMethodFlashMessages($session),
        );
        $administratorActivityController = new AdministratorActivityController(
            $viewRenderer,
            new ActivityManagementServiceProvider(
                static function () use ($catalogConnectionProvider): AdministratorActivityService {
                    $connection = $catalogConnectionProvider();

                    return new AdministratorActivityService(
                        new ActivityRepository($connection),
                        new PdoAuditLogRepository($connection),
                        new PdoActivityTransaction($connection),
                    );
                },
            ),
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
        );
        $administratorPaymentController = new AdministratorPaymentController(
            $viewRenderer,
            new AdministratorPaymentServiceProvider(
                static function () use (
                    $catalogConnectionProvider,
                    $paymentProofServices,
                ): AdministratorPaymentService {
                    $connection = $catalogConnectionProvider();

                    return new AdministratorPaymentService(
                        new PdoAdministratorPaymentRepository($connection),
                        $paymentProofServices->reader(),
                        new PdoAuditLogRepository($connection),
                        new PdoBookingTransaction($connection),
                    );
                },
            ),
            new AdministratorPaymentStatusServiceProvider(
                static function () use ($catalogConnectionProvider): AdministratorPaymentStatusService {
                    $connection = $catalogConnectionProvider();

                    $auditLogs = new PdoAuditLogRepository($connection);

                    return new AdministratorPaymentStatusService(
                        new PdoPaymentWriteRepository($connection),
                        $auditLogs,
                        new VerifiedPaymentBookingConfirmationService(
                            new PdoBookingStatusWriteRepository($connection),
                            $auditLogs,
                        ),
                        new PdoBookingTransaction($connection),
                    );
                },
            ),
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
        );
        $administratorInvoiceController = new AdministratorInvoiceController(
            $viewRenderer,
            $administratorInvoiceServices,
            $invoiceIssueServices,
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
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
        $publicPaymentMethodServices = new PublicPaymentMethodServiceProvider(
            static fn (): PublicPaymentMethodService => new PublicPaymentMethodService(
                new PaymentMethodRepository($catalogConnectionProvider()),
            ),
        );
        $publicPaymentMethodController = new PublicPaymentMethodController(
            $publicPaymentMethodServices,
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
        $paymentContinuation = new GuestPaymentContinuation($session);
        $publicBookingPageController = new PublicBookingPageController(
            $viewRenderer,
            $publicBookingWorkflows,
            new BookingSuccessFlash($session, $responseTimezone),
            $paymentContinuation,
            $csrfTokens,
        );
        $publicPaymentServices = new GuestPaymentSubmissionServiceProvider(
            static function () use (
                $catalogConnectionProvider,
                $bookingConfiguration,
                $paymentProofServices,
            ): GuestPaymentSubmissionService {
                $accessTokenKey = $bookingConfiguration['access_token_key'] ?? null;

                if (!is_string($accessTokenKey)) {
                    throw new ConfigurationException(
                        'Public payment submission is not configured.',
                    );
                }

                $connection = $catalogConnectionProvider();
                $bookings = new BookingRepository($connection);
                $paymentMethods = new PaymentMethodRepository($connection);
                $tokens = new BookingAccessToken($accessTokenKey);

                return new GuestPaymentSubmissionService(
                    new GuestPaymentAccessValidator(
                        $bookings,
                        $paymentMethods,
                        $tokens,
                    ),
                    $tokens,
                    new PdoPaymentWriteRepository($connection),
                    $paymentProofServices->storage(),
                    new PdoBookingTransaction($connection),
                );
            },
        );
        $publicPaymentController = new PublicPaymentController(
            $publicPaymentServices,
            $paymentProofServices->uploadConfiguration(...),
        );
        $publicPaymentPageServices = new GuestPaymentPageServiceProvider(
            static function () use (
                $catalogConnectionProvider,
                $bookingConfiguration,
            ): GuestPaymentPageService {
                $accessTokenKey = $bookingConfiguration['access_token_key'] ?? null;

                if (!is_string($accessTokenKey)) {
                    throw new ConfigurationException(
                        'Public payment page is not configured.',
                    );
                }

                return new GuestPaymentPageService(
                    new BookingRepository($catalogConnectionProvider()),
                    new PaymentMethodRepository($catalogConnectionProvider()),
                    new BookingAccessToken($accessTokenKey),
                );
            },
        );
        $publicPaymentPageController = new PublicPaymentPageController(
            $viewRenderer,
            $publicPaymentPageServices,
            $publicPaymentServices,
            $paymentContinuation,
            new GuestPaymentSuccessFlash($session, $responseTimezone),
            $csrfTokens,
            $paymentProofServices->uploadConfiguration(...),
            $responseTimezone,
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
        $publicPaymentRateLimit = new PublicPaymentRateLimitMiddleware(
            new PublicPaymentRateLimiter(
                STORAGE_PATH
                    . DIRECTORY_SEPARATOR
                    . 'cache'
                    . DIRECTORY_SEPARATOR
                    . 'payment-rate-limits'
                    . DIRECTORY_SEPARATOR
                    . 'public',
                $publicPaymentPolicy,
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
            $administratorBookingController,
            $paymentMethodController,
            $administratorPaymentController,
            $administratorInvoiceController,
            $administratorActivityController,
            $publicRoomController,
            $publicAvailabilityController,
            $publicPaymentMethodController,
            $publicBookingController,
            $publicBookingPageController,
            $publicBookingRateLimit,
            $publicPaymentController,
            $publicPaymentPageController,
            $publicPaymentRateLimit,
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
        AdministratorBookingController $administratorBookingController,
        AdministratorPaymentMethodController $paymentMethodController,
        AdministratorPaymentController $administratorPaymentController,
        AdministratorInvoiceController $administratorInvoiceController,
        AdministratorActivityController $administratorActivityController,
        PublicRoomController $publicRoomController,
        PublicAvailabilityController $publicAvailabilityController,
        PublicPaymentMethodController $publicPaymentMethodController,
        PublicBookingController $publicBookingController,
        PublicBookingPageController $publicBookingPageController,
        PublicBookingRateLimitMiddleware $publicBookingRateLimit,
        PublicPaymentController $publicPaymentController,
        PublicPaymentPageController $publicPaymentPageController,
        PublicPaymentRateLimitMiddleware $publicPaymentRateLimit,
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
                $administratorBookingController,
                $paymentMethodController,
                $administratorPaymentController,
                $administratorInvoiceController,
                $administratorActivityController,
                $publicRoomController,
                $publicAvailabilityController,
                $publicPaymentMethodController,
                $publicBookingController,
                $publicBookingPageController,
                $publicBookingRateLimit,
                $publicPaymentController,
                $publicPaymentPageController,
                $publicPaymentRateLimit,
            );
        }
    }
}
