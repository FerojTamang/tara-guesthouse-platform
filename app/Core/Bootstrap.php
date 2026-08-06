<?php

declare(strict_types=1);

namespace App\Core;

use App\Authentication\AdministratorSession;
use App\Authentication\LoginRateLimiter;
use App\Controllers\AdministratorActivityController;
use App\Controllers\AdministratorAmenityController;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorBookingController;
use App\Controllers\AdministratorCmsController;
use App\Controllers\AdministratorContactController;
use App\Repositories\PdoAdministratorDashboardMetricsRepository;
use App\Controllers\AdministratorGalleryController;
use App\Controllers\AdministratorInvoiceController;
use App\Controllers\AdministratorNotificationController;
use App\Controllers\AdministratorPaymentController;
use App\Controllers\AdministratorReviewController;
use App\Controllers\AdministratorPaymentMethodController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Controllers\AdministratorRoomController;
use App\Controllers\AdministratorRoomImageController;
use App\Controllers\AdministratorRoomTypeController;
use App\Controllers\PublicActivityController;
use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicGalleryController;
use App\Controllers\PublicBookingController;
use App\Controllers\PublicBookingPageController;
use App\Controllers\PublicCmsController;
use App\Controllers\PublicContactController;
use App\Controllers\PublicPaymentController;
use App\Controllers\PublicPaymentPageController;
use App\Controllers\PublicPaymentMethodController;
use App\Controllers\PublicReviewController;
use App\Controllers\PublicRoomController;
use App\Exceptions\ConfigurationException;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Middleware\PublicContactRateLimitMiddleware;
use App\Middleware\PublicPaymentRateLimitMiddleware;
use App\Middleware\PublicReviewRateLimitMiddleware;
use App\Middleware\RequireAdministratorMiddleware;
use App\Repositories\ActivityRepository;
use App\Repositories\AmenityRepository;
use App\Repositories\BookingRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoAvailabilityRepository;
use App\Repositories\PdoBookingStatusWriteRepository;
use App\Repositories\PdoBookingWriteRepository;
use App\Repositories\PdoContactMessageRepository;
use App\Repositories\PdoContentMediaRepository;
use App\Repositories\PdoInvoiceRepository;
use App\Repositories\PdoNotificationRepository;
use App\Repositories\PdoNavigationRepository;
use App\Repositories\PdoPageRepository;
use App\Repositories\PdoPublicContentCatalogRepository;
use App\Repositories\PdoPageContentProfileRepository;
use App\Repositories\PdoSettingRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PdoAdministratorPaymentRepository;
use App\Repositories\PdoPaymentWriteRepository;
use App\Repositories\PdoReviewInvitationRepository;
use App\Repositories\PdoReviewRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTypeRepository;
use App\Repositories\UserRepository;
use App\Services\AdministratorActivityService;
use App\Services\AdministratorCmsService;
use App\Services\AdministratorContentMediaService;
use App\Services\AdministratorGalleryService;
use App\Services\AdministratorAuthenticationService;
use App\Services\AdministratorSessionValidator;
use App\Services\AdministratorDashboardMetricsService;
use App\Services\AdministratorDashboardMetricsServiceProvider;
use App\Services\ActivityManagementServiceProvider;
use App\Services\AvailabilityService;
use App\Services\AdministratorBookingService;
use App\Services\AdministratorBookingServiceProvider;
use App\Services\AdministratorBookingStatusService;
use App\Services\AdministratorBookingStatusServiceProvider;
use App\Services\AdministratorInvoiceService;
use App\Services\AdministratorInvoiceServiceProvider;
use App\Services\AdministratorNotificationService;
use App\Services\AdministratorNotificationServiceProvider;
use App\Services\AdministratorNavigationService;
use App\Services\BookingAccessToken;
use App\Services\BookingCreationService;
use App\Services\BookingDeadlinePolicy;
use App\Services\BookingReferenceGenerator;
use App\Services\BookingService;
use App\Services\BookingSubmissionService;
use App\Services\BookingSubmissionWorkflow;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSuccessFlash;
use App\Services\CmsManagementServiceProvider;
use App\Services\NavigationManagementServiceProvider;
use App\Services\NavigationRouteCatalog;
use App\Services\ContentMediaManagementServiceProvider;
use App\Services\ContentMediaStorageProvider;
use App\Services\CmsPageContentPolicyProvider;
use App\Services\CmsPageService;
use App\Services\CmsSettingsService;
use App\Services\CmsSettingsServiceProvider;
use App\Services\ContactMessageService;
use App\Services\ContactMessageServiceProvider;
use App\Services\ContactReplyMailSender;
use App\Services\ContactSubmissionFlash;
use App\Services\GuestPaymentAccessValidator;
use App\Services\GuestPaymentContinuation;
use App\Services\GuestPaymentPageService;
use App\Services\GuestPaymentPageServiceProvider;
use App\Services\GuestPaymentSubmissionService;
use App\Services\GuestPaymentSubmissionServiceProvider;
use App\Services\GuestPaymentSuccessFlash;
use App\Services\GalleryImageStorageProvider;
use App\Services\GalleryManagementServiceProvider;
use App\Services\PdoGalleryTransaction;
use App\Services\InvoiceIssueService;
use App\Services\InvoiceIssueServiceProvider;
use App\Services\AdministratorLogoutService;
use App\Services\AdministratorPaymentService;
use App\Services\AdministratorPaymentServiceProvider;
use App\Services\AdministratorPaymentStatusService;
use App\Services\AdministratorPaymentStatusServiceProvider;
use App\Services\AdministratorReviewService;
use App\Services\AdministratorReviewServiceProvider;
use App\Services\VerifiedPaymentBookingConfirmationService;
use App\Services\LazyAdministratorAuthenticator;
use App\Services\LazyCmsPageContentSanitizer;
use App\Services\LoggingTransactionalMailDispatcher;
use App\Services\LoggingContactReplyMailSender;
use App\Services\MailTemplateRenderer;
use App\Services\NullTransactionalMailDispatcher;
use App\Services\NullContactReplyMailSender;
use App\Services\ReviewInvitationMailDelivery;
use App\Services\SmtpMailTransport;
use App\Services\TransactionalMailDispatcher;
use App\Services\TransactionalMailService;
use App\Services\TransactionalMailServiceProvider;
use App\Services\AmenityCatalogService;
use App\Services\PdoActivityTransaction;
use App\Services\PdoCmsTransaction;
use App\Services\PdoContactTransaction;
use App\Services\PdoBookingTransaction;
use App\Services\PdoRoomCatalogTransaction;
use App\Services\PdoReviewTransaction;
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
use App\Services\ReviewCheckoutInvitationCoordinator;
use App\Services\ReviewInvitationPolicyProvider;
use App\Services\ReviewModerationPolicyProvider;
use App\Services\ReviewInvitationService;
use App\Services\ReviewInvitationServiceProvider;
use App\Services\ReviewTokenHasher;
use App\Services\ReviewSubmissionFlash;
use App\Services\SettingDefinitionRegistryProvider;
use App\Services\PublicActivityService;
use App\Services\PublicActivityServiceProvider;
use App\Services\PublicAvailabilityService;
use App\Services\PublicAvailabilityServiceProvider;
use App\Services\PublicGalleryService;
use App\Services\PublicGalleryServiceProvider;
use App\Services\PublicBookingRateLimiter;
use App\Services\PublicCmsService;
use App\Services\PublicCmsServiceProvider;
use App\Services\PublicNavigationService;
use App\Services\PublicNavigationServiceProvider;
use App\Services\PublicContactRateLimiter;
use App\Services\PublicPaymentMethodService;
use App\Services\PublicPaymentMethodServiceProvider;
use App\Services\PublicPaymentRateLimiter;
use App\Services\PublicReviewRateLimiter;
use App\Services\PublicReviewService;
use App\Services\PublicReviewServiceProvider;
use App\Services\PublicRoomCatalogService;
use App\Services\PublicRoomCatalogServiceProvider;
use App\Repositories\RoomImageRepository;
use App\Storage\LazyGalleryImageReader;
use App\Storage\LocalPaymentMethodQrStorage;
use App\Storage\LocalSiteLogoStorage;
use App\Storage\LazyGalleryImageStorage;
use SensitiveParameter;

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
        $responseSecurityConfiguration = $config->require('http.security_headers');

        if (!is_array($sessionConfiguration) || !is_array($responseSecurityConfiguration)) {
            throw new ConfigurationException('The configured session or response security settings are invalid.');
        }

        $responseSecurityPolicy = new ResponseSecurityPolicy($responseSecurityConfiguration);

        $session = Session::start($sessionConfiguration);

        $viewRenderer = new ViewRenderer(
            $resolvedBasePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
        );
        $applicationName = $config->require('app.name');
        $applicationUrl = $config->get('app.url');
        $mailConfiguration = $config->require('mail');

        if (
            !is_string($applicationName)
            || ($applicationUrl !== null && !is_string($applicationUrl))
            || !is_array($mailConfiguration)
        ) {
            throw new ConfigurationException('Application mail configuration is invalid.');
        }

        $mailServices = self::mailServices(
            $mailConfiguration,
            $applicationName,
            $applicationUrl,
            $timezone,
            $viewRenderer,
            $logger,
        );
        $mail = $mailServices['dispatcher'];
        $contactReplyMail = $mailServices['contact_replies'];
        $databaseConfiguration = $config->require('database');
        $loginPolicy = $config->require('security.administrator_login');
        $publicBookingPolicy = $config->require('security.public_booking');
        $publicPaymentPolicy = $config->require('security.public_payment');
        $publicContactPolicy = $config->require('security.public_contact');
        $publicReviewPolicy = $config->require('security.public_review');
        $bookingConfiguration = $config->require('booking');

        if (
            !is_array($databaseConfiguration)
            || !is_array($loginPolicy)
            || !is_array($publicBookingPolicy)
            || !is_array($publicPaymentPolicy)
            || !is_array($publicContactPolicy)
            || !is_array($publicReviewPolicy)
            || !is_array($bookingConfiguration)
        ) {
            throw new ConfigurationException('Application service configuration is invalid.');
        }

        $connectionFactory = new ConnectionFactory($databaseConfiguration);
        $csrfTokens = new CsrfTokenManager($session);
        $administratorSession = new AdministratorSession(
            $session,
            $csrfTokens,
            new AdministratorSessionValidator(
                static fn (): UserRepository => new UserRepository($connectionFactory->create()),
                $logger,
            ),
        );
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
        $catalogConnection = null;
        $catalogConnectionProvider = static function () use ($connectionFactory, &$catalogConnection): \PDO {
            $catalogConnection ??= $connectionFactory->create();

            return $catalogConnection;
        };
        $controller = new AdministratorAuthenticationController(
            $viewRenderer,
            $authenticator,
            new AdministratorLogoutService($administratorSession, $logger),
            $administratorSession,
            $csrfTokens,
            $logger,
            $loginPolicy,
            new AdministratorDashboardMetricsServiceProvider(
                static fn (): AdministratorDashboardMetricsService => new AdministratorDashboardMetricsService(
                    new PdoAdministratorDashboardMetricsRepository($catalogConnectionProvider()),
                ),
            ),
        );
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
                static function () use (
                    $catalogConnectionProvider,
                    $logger,
                    $mail,
                    $resolvedBasePath,
                ): AdministratorBookingStatusService {
                    $connection = $catalogConnectionProvider();
                    $reviewPolicy = new ReviewInvitationPolicyProvider($resolvedBasePath);
                    $reviewServices = new ReviewInvitationServiceProvider(
                        static fn (): ReviewInvitationService => new ReviewInvitationService(
                            new BookingRepository($connection),
                            new PdoReviewInvitationRepository($connection),
                            new PdoReviewRepository($connection),
                            new PdoAuditLogRepository($connection),
                            new PdoReviewTransaction($connection),
                            new ReviewTokenHasher(),
                            $reviewPolicy->policy(),
                        ),
                    );

                    $bookingCatalog = new BookingRepository($connection);

                    return new AdministratorBookingStatusService(
                        new PdoBookingStatusWriteRepository($connection),
                        new PdoAuditLogRepository($connection),
                        new PdoBookingTransaction($connection),
                        null,
                        new ReviewCheckoutInvitationCoordinator(
                            $reviewServices,
                            new ReviewInvitationMailDelivery($bookingCatalog, $mail),
                            $logger,
                        ),
                        $bookingCatalog,
                        $mail,
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
                    new LocalPaymentMethodQrStorage(
                        PUBLIC_PATH . DIRECTORY_SEPARATOR . 'payment-methods',
                    ),
                ),
            ),
            $csrfTokens,
            new PaymentMethodFlashMessages($session),
        );
        $galleryImageStorage = new GalleryImageStorageProvider($resolvedBasePath);
        $administratorActivityController = new AdministratorActivityController(
            $viewRenderer,
            new ActivityManagementServiceProvider(
                static function () use (
                    $catalogConnectionProvider,
                    $galleryImageStorage,
                ): AdministratorActivityService {
                    $connection = $catalogConnectionProvider();

                    return new AdministratorActivityService(
                        new ActivityRepository($connection),
                        new PdoAuditLogRepository($connection),
                        new PdoActivityTransaction($connection),
                        imageStorage: new LazyGalleryImageStorage($galleryImageStorage->storage(...)),
                    );
                },
            ),
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
            $galleryImageStorage,
        );
        $administratorGalleryController = new AdministratorGalleryController(
            $viewRenderer,
            new GalleryManagementServiceProvider(
                static function () use (
                    $catalogConnectionProvider,
                    $galleryImageStorage,
                ): AdministratorGalleryService {
                    $connection = $catalogConnectionProvider();

                    return new AdministratorGalleryService(
                        new GalleryRepository($connection),
                        new LazyGalleryImageStorage($galleryImageStorage->storage(...)),
                        new PdoAuditLogRepository($connection),
                        new PdoGalleryTransaction($connection),
                    );
                },
            ),
            $galleryImageStorage,
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
        );
        $settingDefinitions = new SettingDefinitionRegistryProvider($resolvedBasePath);
        $pageContentPolicy = new CmsPageContentPolicyProvider($resolvedBasePath);
        $contentMediaStorage = new ContentMediaStorageProvider($resolvedBasePath);
        $contentMediaServices = new ContentMediaManagementServiceProvider(
            static function () use ($catalogConnectionProvider, $contentMediaStorage): AdministratorContentMediaService {
                $connection = $catalogConnectionProvider();

                return new AdministratorContentMediaService(
                    new PdoContentMediaRepository($connection),
                    $contentMediaStorage->storage(),
                    new PdoAuditLogRepository($connection),
                    new PdoCmsTransaction($connection),
                );
            },
        );
        $navigationServices = new NavigationManagementServiceProvider(
            static function () use ($catalogConnectionProvider): AdministratorNavigationService {
                $connection = $catalogConnectionProvider();

                return new AdministratorNavigationService(
                    new PdoNavigationRepository($connection),
                    new PdoPageRepository($connection),
                    new PdoAuditLogRepository($connection),
                    new PdoCmsTransaction($connection),
                );
            },
        );
        $administratorCmsController = new AdministratorCmsController(
            $viewRenderer,
            new CmsManagementServiceProvider(
                static function () use (
                    $catalogConnectionProvider,
                    $settingDefinitions,
                    $pageContentPolicy,
                ): AdministratorCmsService {
                    $connection = $catalogConnectionProvider();
                    return new AdministratorCmsService(
                        new CmsSettingsServiceProvider(
                            static fn (): CmsSettingsService => new CmsSettingsService(
                                new PdoSettingRepository($connection),
                                $settingDefinitions->registry(),
                            ),
                        ),
                        new CmsPageService(new PdoPageRepository($connection)),
                        new LazyCmsPageContentSanitizer($pageContentPolicy->sanitizer(...)),
                        new PdoAuditLogRepository($connection),
                        new PdoCmsTransaction($connection),
                        profiles: new PdoPageContentProfileRepository($connection),
                        contentMedia: new PdoContentMediaRepository($connection),
                    );
                },
            ),
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
            new LocalSiteLogoStorage(
                PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'branding',
            ),
            $contentMediaServices,
            $contentMediaStorage,
            $navigationServices,
        );
        $contactServices = new ContactMessageServiceProvider(
            static function () use ($catalogConnectionProvider, $contactReplyMail): ContactMessageService {
                $connection = $catalogConnectionProvider();

                return new ContactMessageService(
                    new PdoContactMessageRepository($connection),
                    new PdoAuditLogRepository($connection),
                    new PdoContactTransaction($connection),
                    $contactReplyMail,
                );
            },
        );
        $administratorContactController = new AdministratorContactController(
            $viewRenderer,
            $contactServices,
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
        );
        $publicContactController = new PublicContactController(
            $viewRenderer,
            $contactServices,
            new ContactSubmissionFlash($session),
            $csrfTokens,
        );
        $reviewModerationPolicy = new ReviewModerationPolicyProvider($resolvedBasePath);
        $administratorReviewController = new AdministratorReviewController(
            $viewRenderer,
            new AdministratorReviewServiceProvider(
                static function () use ($catalogConnectionProvider): AdministratorReviewService {
                    $connection = $catalogConnectionProvider();

                    return new AdministratorReviewService(
                        new PdoReviewRepository($connection),
                        new BookingRepository($connection),
                        new PdoReviewInvitationRepository($connection),
                        new PdoAuditLogRepository($connection),
                        new PdoReviewTransaction($connection),
                    );
                },
            ),
            $reviewModerationPolicy,
            $administratorSession,
            $csrfTokens,
            new \DateTimeZone($timezone),
        );
        $administratorNotificationController = new AdministratorNotificationController(
            $viewRenderer,
            new AdministratorNotificationServiceProvider(
                static fn (): AdministratorNotificationService => new AdministratorNotificationService(
                    new PdoNotificationRepository($catalogConnectionProvider()),
                ),
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
                static function () use (
                    $catalogConnectionProvider,
                    $mail,
                ): AdministratorPaymentStatusService {
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
                        null,
                        new BookingRepository($connection),
                        $mail,
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
        $publicActivityServices = new PublicActivityServiceProvider(
            static fn (): PublicActivityService => new PublicActivityService(
                new ActivityRepository($catalogConnectionProvider()),
                new LazyGalleryImageReader($galleryImageStorage->reader(...)),
            ),
        );
        $publicActivityController = new PublicActivityController(
            $viewRenderer,
            $publicActivityServices,
        );
        $publicGalleryServices = new PublicGalleryServiceProvider(
            static fn (): PublicGalleryService => new PublicGalleryService(
                new GalleryRepository($catalogConnectionProvider()),
                new LazyGalleryImageReader($galleryImageStorage->reader(...)),
            ),
        );
        $publicGalleryController = new PublicGalleryController(
            $viewRenderer,
            $publicGalleryServices,
        );
        $publicRoomServices = new PublicRoomCatalogServiceProvider(
            static fn (): PublicRoomCatalogService => new PublicRoomCatalogService(
                new RoomRepository($catalogConnectionProvider()),
                new RoomImageRepository($catalogConnectionProvider()),
                new RoomImageUrl(),
            ),
        );
        $publicRoomController = new PublicRoomController(
            $viewRenderer,
            $publicRoomServices,
        );
        $publicReviewServices = new PublicReviewServiceProvider(
            static function () use ($catalogConnectionProvider): PublicReviewService {
                $connection = $catalogConnectionProvider();

                return new PublicReviewService(
                    new BookingRepository($connection),
                    new PdoReviewInvitationRepository($connection),
                    new PdoReviewRepository($connection),
                    new PdoAuditLogRepository($connection),
                    new PdoReviewTransaction($connection),
                    new ReviewTokenHasher(),
                );
            },
        );
        $publicReviewController = new PublicReviewController(
            $viewRenderer,
            $publicReviewServices,
            new ReviewSubmissionFlash($session),
            $csrfTokens,
        );
        $publicCmsServices = new PublicCmsServiceProvider(
            static fn (): PublicCmsService => new PublicCmsService(
                new PdoPageRepository($catalogConnectionProvider()),
                new PdoSettingRepository($catalogConnectionProvider()),
                $settingDefinitions->registry(...),
                $publicRoomServices->service(),
                $publicActivityServices->service(),
                $publicGalleryServices->service(),
                $applicationName,
                $applicationUrl,
                static fn (): array => array_slice($publicReviewServices->service()->approved(), 0, 3),
                new PdoPageContentProfileRepository($catalogConnectionProvider()),
                new PdoContentMediaRepository($catalogConnectionProvider()),
                publicContent: new PdoPublicContentCatalogRepository($catalogConnectionProvider()),
            ),
        );
        $publicNavigationServices = new PublicNavigationServiceProvider(
            static fn (): PublicNavigationService => new PublicNavigationService(
                new PdoNavigationRepository($catalogConnectionProvider()),
                new PdoPageRepository($catalogConnectionProvider()),
                new NavigationRouteCatalog(),
                new PdoPageContentProfileRepository($catalogConnectionProvider()),
            ),
        );
        $viewRenderer->registerLayoutDataProvider(
            'layouts.base',
            static function (array $renderData = []) use (
                $applicationName,
                $publicCmsServices,
                $publicNavigationServices,
            ): array {
                if (($renderData['showPublicNavigation'] ?? true) !== true) {
                    return [
                        'siteName' => $applicationName,
                        'navigationItems' => [],
                        'footerNavigationItems' => [],
                        'footerSettings' => [],
                    ];
                }

                $navigation = $publicNavigationServices->service()->layout();

                return [
                    'siteName' => $applicationName,
                    'navigationItems' => $navigation['main'],
                    'footerNavigationItems' => $navigation['footer'],
                    'footerSettings' => $publicCmsServices->service()->footerLayoutSettings(),
                ];
            },
        );
        $publicCmsController = new PublicCmsController(
            $viewRenderer,
            $publicCmsServices,
        );
        $publicAvailabilityController = new PublicAvailabilityController(
            new PublicAvailabilityServiceProvider(
                static function () use (
                    $catalogConnectionProvider,
                    $publicRoomServices,
                ): PublicAvailabilityService {
                    $connection = $catalogConnectionProvider();
                    $availability = new PdoAvailabilityRepository($connection);

                    return new PublicAvailabilityService(
                        new AvailabilityService($availability),
                        new RoomRepository($connection),
                        $publicRoomServices,
                        $availability,
                    );
                },
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
                $mail,
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
                    $mail,
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
            $publicRoomServices,
            $logger,
        );
        $publicPaymentServices = new GuestPaymentSubmissionServiceProvider(
            static function () use (
                $catalogConnectionProvider,
                $bookingConfiguration,
                $paymentProofServices,
                $mail,
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
                    null,
                    $mail,
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
        $publicContactRateLimit = new PublicContactRateLimitMiddleware(
            new PublicContactRateLimiter(
                STORAGE_PATH
                    . DIRECTORY_SEPARATOR
                    . 'cache'
                    . DIRECTORY_SEPARATOR
                    . 'contact-rate-limits'
                    . DIRECTORY_SEPARATOR
                    . 'public',
                $publicContactPolicy,
            ),
        );
        $publicReviewRateLimit = new PublicReviewRateLimitMiddleware(
            new PublicReviewRateLimiter(
                STORAGE_PATH
                    . DIRECTORY_SEPARATOR
                    . 'cache'
                    . DIRECTORY_SEPARATOR
                    . 'review-rate-limits'
                    . DIRECTORY_SEPARATOR
                    . 'public',
                $publicReviewPolicy,
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
            $administratorGalleryController,
            $administratorCmsController,
            $administratorContactController,
            $administratorReviewController,
            $administratorNotificationController,
            $publicActivityController,
            $publicGalleryController,
            $publicCmsController,
            $publicContactController,
            $publicReviewController,
            $publicRoomController,
            $publicAvailabilityController,
            $publicPaymentMethodController,
            $publicBookingController,
            $publicBookingPageController,
            $publicBookingRateLimit,
            $publicPaymentController,
            $publicPaymentPageController,
            $publicPaymentRateLimit,
            $publicContactRateLimit,
            $publicReviewRateLimit,
        );

        return new Application($router, $errorHandler, $session, $responseSecurityPolicy);
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array{dispatcher: TransactionalMailDispatcher, contact_replies: ContactReplyMailSender}
     */
    private static function mailServices(
        #[SensitiveParameter] array $configuration,
        string $applicationName,
        ?string $applicationUrl,
        string $timezone,
        ViewRenderer $views,
        \Psr\Log\LoggerInterface $logger,
    ): array {
        $expectedKeys = [
            'enabled',
            'host',
            'port',
            'encryption',
            'username',
            'password',
            'from_address',
            'from_name',
            'reply_to_address',
            'reply_to_name',
            'timeout_seconds',
        ];

        if (array_keys($configuration) !== $expectedKeys || !is_bool($configuration['enabled'])) {
            throw new ConfigurationException('Application mail configuration is invalid.');
        }

        if ($configuration['enabled'] === false) {
            return [
                'dispatcher' => new NullTransactionalMailDispatcher(),
                'contact_replies' => new NullContactReplyMailSender(),
            ];
        }

        if (!is_string($applicationUrl) || $applicationUrl === '') {
            throw new ConfigurationException('APP_URL is required when SMTP is enabled.');
        }

        $provider = new TransactionalMailServiceProvider(
            static function () use (
                $configuration,
                $applicationName,
                $applicationUrl,
                $timezone,
                $views,
            ): TransactionalMailService {
                return new TransactionalMailService(
                    new SmtpMailTransport(
                        self::mailString($configuration, 'host'),
                        self::mailInteger($configuration, 'port'),
                        self::mailString($configuration, 'encryption'),
                        self::mailNullableString($configuration, 'username'),
                        self::mailNullableString($configuration, 'password'),
                        self::mailString($configuration, 'from_address'),
                        self::mailString($configuration, 'from_name'),
                        self::mailNullableString($configuration, 'reply_to_address'),
                        self::mailNullableString($configuration, 'reply_to_name'),
                        self::mailInteger($configuration, 'timeout_seconds'),
                    ),
                    new MailTemplateRenderer($views),
                    $applicationName,
                    rtrim($applicationUrl, '/'),
                    new \DateTimeZone($timezone),
                );
            },
        );

        return [
            'dispatcher' => new LoggingTransactionalMailDispatcher($provider, $logger),
            'contact_replies' => new LoggingContactReplyMailSender($provider, $logger),
        ];
    }

    /** @param array<string, mixed> $configuration */
    private static function mailString(#[SensitiveParameter] array $configuration, string $key): string
    {
        $value = $configuration[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new ConfigurationException('Application mail configuration is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $configuration */
    private static function mailNullableString(#[SensitiveParameter] array $configuration, string $key): ?string
    {
        $value = $configuration[$key] ?? null;

        if ($value !== null && !is_string($value)) {
            throw new ConfigurationException('Application mail configuration is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $configuration */
    private static function mailInteger(#[SensitiveParameter] array $configuration, string $key): int
    {
        $value = $configuration[$key] ?? null;

        if (!is_int($value)) {
            throw new ConfigurationException('Application mail configuration is invalid.');
        }

        return $value;
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
        AdministratorGalleryController $administratorGalleryController,
        AdministratorCmsController $administratorCmsController,
        AdministratorContactController $administratorContactController,
        AdministratorReviewController $administratorReviewController,
        AdministratorNotificationController $administratorNotificationController,
        PublicActivityController $publicActivityController,
        PublicGalleryController $publicGalleryController,
        PublicCmsController $publicCmsController,
        PublicContactController $publicContactController,
        PublicReviewController $publicReviewController,
        PublicRoomController $publicRoomController,
        PublicAvailabilityController $publicAvailabilityController,
        PublicPaymentMethodController $publicPaymentMethodController,
        PublicBookingController $publicBookingController,
        PublicBookingPageController $publicBookingPageController,
        PublicBookingRateLimitMiddleware $publicBookingRateLimit,
        PublicPaymentController $publicPaymentController,
        PublicPaymentPageController $publicPaymentPageController,
        PublicPaymentRateLimitMiddleware $publicPaymentRateLimit,
        PublicContactRateLimitMiddleware $publicContactRateLimit,
        PublicReviewRateLimitMiddleware $publicReviewRateLimit,
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
                $administratorGalleryController,
                $administratorCmsController,
                $administratorContactController,
                $administratorReviewController,
                $administratorNotificationController,
                $publicActivityController,
                $publicGalleryController,
                $publicCmsController,
                $publicContactController,
                $publicReviewController,
                $publicRoomController,
                $publicAvailabilityController,
                $publicPaymentMethodController,
                $publicBookingController,
                $publicBookingPageController,
                $publicBookingRateLimit,
                $publicPaymentController,
                $publicPaymentPageController,
                $publicPaymentRateLimit,
                $publicContactRateLimit,
                $publicReviewRateLimit,
            );
        }
    }
}
