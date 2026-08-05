<?php

declare(strict_types=1);

use App\Controllers\AdministratorAmenityController;
use App\Controllers\AdministratorActivityController;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorBookingController;
use App\Controllers\AdministratorCmsController;
use App\Controllers\AdministratorContactController;
use App\Controllers\AdministratorGalleryController;
use App\Controllers\AdministratorInvoiceController;
use App\Controllers\AdministratorNotificationController;
use App\Controllers\AdministratorPaymentController;
use App\Controllers\AdministratorPaymentMethodController;
use App\Controllers\AdministratorReviewController;
use App\Controllers\AdministratorRoomCatalogController;
use App\Controllers\AdministratorRoomController;
use App\Controllers\AdministratorRoomImageController;
use App\Controllers\AdministratorRoomTypeController;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\RequireAdministratorMiddleware;

return static function (
    Router $router,
    ViewRenderer $viewRenderer,
    ?AdministratorAuthenticationController $controller = null,
    ?GuestOnlyMiddleware $guestOnly = null,
    ?RequireAdministratorMiddleware $requireAdministrator = null,
    ?CsrfMiddleware $csrf = null,
    ?AdministratorRoomCatalogController $catalogController = null,
    ?AdministratorRoomTypeController $roomTypeController = null,
    ?AdministratorAmenityController $amenityController = null,
    ?AdministratorRoomController $roomController = null,
    ?AdministratorRoomImageController $roomImageController = null,
    ?AdministratorBookingController $bookingController = null,
    ?AdministratorPaymentMethodController $paymentMethodController = null,
    ?AdministratorPaymentController $paymentController = null,
    ?AdministratorInvoiceController $invoiceController = null,
    ?AdministratorActivityController $activityController = null,
    ?AdministratorGalleryController $galleryController = null,
    ?AdministratorCmsController $cmsController = null,
    ?AdministratorContactController $contactController = null,
    ?AdministratorReviewController $reviewController = null,
    ?AdministratorNotificationController $notificationController = null,
): void {
    $dependencyCount = count(array_filter(
        [$controller, $guestOnly, $requireAdministrator, $csrf],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));

    if (
        $dependencyCount === 0
        && $catalogController === null
        && $roomController === null
        && $roomTypeController === null
        && $amenityController === null
        && $roomImageController === null
        && $bookingController === null
        && $paymentMethodController === null
        && $paymentController === null
        && $invoiceController === null
        && $activityController === null
        && $galleryController === null
        && $cmsController === null
        && $contactController === null
        && $reviewController === null
        && $notificationController === null
    ) {
        return;
    }

    if ($dependencyCount !== 4) {
        throw new \LogicException('Administrator routes require complete authentication dependencies.');
    }

    $router->get(
        '/admin/login',
        [$controller, 'showLogin'],
        [$guestOnly],
    );
    $router->post(
        '/admin/login',
        [$controller, 'login'],
        [$guestOnly, $csrf],
    );
    $router->get(
        '/admin/dashboard',
        [$controller, 'dashboard'],
        [$requireAdministrator],
    );
    $router->post(
        '/admin/logout',
        [$controller, 'logout'],
        [$requireAdministrator, $csrf],
    );

    if ($bookingController !== null) {
        $router->get(
            '/admin/bookings',
            [$bookingController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/bookings/{id}',
            [$bookingController, 'show'],
            [$requireAdministrator],
        );
    }

    if ($paymentController !== null) {
        $router->get(
            '/admin/payments',
            [$paymentController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/payments/{id}',
            [$paymentController, 'show'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/payments/{id}/proof',
            [$paymentController, 'proof'],
            [$requireAdministrator],
        );
    }

    if ($invoiceController !== null) {
        $router->get(
            '/admin/invoices',
            [$invoiceController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/invoices/{id}/print',
            [$invoiceController, 'print'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/invoices/{id}',
            [$invoiceController, 'show'],
            [$requireAdministrator],
        );
    }

    if ($activityController !== null) {
        $router->get(
            '/admin/activities',
            [$activityController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/activities/create',
            [$activityController, 'createForm'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/activities/{id}/edit',
            [$activityController, 'editForm'],
            [$requireAdministrator],
        );
    }

    if ($galleryController !== null) {
        $router->get(
            '/admin/gallery',
            [$galleryController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/gallery/categories',
            [$galleryController, 'categories'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/gallery/create',
            [$galleryController, 'createForm'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/gallery/{id}/edit',
            [$galleryController, 'editForm'],
            [$requireAdministrator],
        );
    }

    if ($cmsController !== null) {
        $router->get('/admin/settings', [$cmsController, 'settings'], [$requireAdministrator]);
        $router->get('/admin/media', [$cmsController, 'mediaLibrary'], [$requireAdministrator]);
        $router->get('/admin/navigation', [$cmsController, 'navigation'], [$requireAdministrator]);
        $router->get('/admin/pages', [$cmsController, 'pages'], [$requireAdministrator]);
        $router->get('/admin/pages/create', [$cmsController, 'createPageForm'], [$requireAdministrator]);
        $router->get('/admin/pages/{id}/edit', [$cmsController, 'editPageForm'], [$requireAdministrator]);
        $router->get('/admin/pages/{id}', [$cmsController, 'showPage'], [$requireAdministrator]);
    }

    if ($reviewController !== null) {
        $router->get(
            '/admin/reviews',
            [$reviewController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/reviews/{id}',
            [$reviewController, 'show'],
            [$requireAdministrator],
        );
    }

    if ($notificationController !== null) {
        $router->get(
            '/admin/notifications',
            [$notificationController, 'index'],
            [$requireAdministrator],
        );
    }

    if ($contactController !== null) {
        $router->get(
            '/admin/contact-messages',
            [$contactController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/contact-messages/{id}',
            [$contactController, 'show'],
            [$requireAdministrator],
        );
    }

    if ($paymentMethodController !== null) {
        $router->get(
            '/admin/payment-methods',
            [$paymentMethodController, 'index'],
            [$requireAdministrator],
        );
        $router->get(
            '/admin/payment-methods/create',
            [$paymentMethodController, 'createForm'],
            [$requireAdministrator],
        );
        $router->post(
            '/admin/payment-methods',
            [$paymentMethodController, 'create'],
            [$requireAdministrator, $csrf],
        );
        $router->get(
            '/admin/payment-methods/{id}/edit',
            [$paymentMethodController, 'editForm'],
            [$requireAdministrator],
        );
        $router->post(
            '/admin/payment-methods/{id}',
            [$paymentMethodController, 'update'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/payment-methods/{id}/activate',
            [$paymentMethodController, 'activate'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/payment-methods/{id}/deactivate',
            [$paymentMethodController, 'deactivate'],
            [$requireAdministrator, $csrf],
        );
    }

    if ($roomImageController !== null) {
        $router->post(
            '/admin/rooms/{roomId}/images',
            [$roomImageController, 'upload'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/rooms/{roomId}/images/{imageId}',
            [$roomImageController, 'updateMetadata'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/rooms/{roomId}/images/{imageId}/primary',
            [$roomImageController, 'setPrimary'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/rooms/{roomId}/images/{imageId}/delete',
            [$roomImageController, 'delete'],
            [$requireAdministrator, $csrf],
        );
    }

    if ($catalogController === null) {
        return;
    }

    $router->get(
        '/admin/rooms',
        [$catalogController, 'rooms'],
        [$requireAdministrator],
    );

    if ($roomController !== null) {
        $router->get(
            '/admin/rooms/create',
            [$roomController, 'createForm'],
            [$requireAdministrator],
        );
        $router->post(
            '/admin/rooms',
            [$roomController, 'create'],
            [$requireAdministrator, $csrf],
        );
        $router->get(
            '/admin/rooms/{id}/edit',
            [$roomController, 'editForm'],
            [$requireAdministrator],
        );
        $router->post(
            '/admin/rooms/{id}',
            [$roomController, 'update'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/rooms/{id}/delete',
            [$roomController, 'softDelete'],
            [$requireAdministrator, $csrf],
        );
    }

    $router->get(
        '/admin/room-types',
        [$catalogController, 'roomTypes'],
        [$requireAdministrator],
    );
    $router->get(
        '/admin/amenities',
        [$catalogController, 'amenities'],
        [$requireAdministrator],
    );

    if ($roomTypeController !== null) {
        $router->get(
            '/admin/room-types/create',
            [$roomTypeController, 'createForm'],
            [$requireAdministrator],
        );
        $router->post(
            '/admin/room-types',
            [$roomTypeController, 'create'],
            [$requireAdministrator, $csrf],
        );
        $router->get(
            '/admin/room-types/{id}/edit',
            [$roomTypeController, 'editForm'],
            [$requireAdministrator],
        );
        $router->post(
            '/admin/room-types/{id}',
            [$roomTypeController, 'update'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/room-types/{id}/activate',
            [$roomTypeController, 'activate'],
            [$requireAdministrator, $csrf],
        );
        $router->post(
            '/admin/room-types/{id}/deactivate',
            [$roomTypeController, 'deactivate'],
            [$requireAdministrator, $csrf],
        );
    }

    if ($amenityController === null) {
        return;
    }

    $router->get(
        '/admin/amenities/create',
        [$amenityController, 'createForm'],
        [$requireAdministrator],
    );
    $router->post(
        '/admin/amenities',
        [$amenityController, 'create'],
        [$requireAdministrator, $csrf],
    );
    $router->get(
        '/admin/amenities/{id}/edit',
        [$amenityController, 'editForm'],
        [$requireAdministrator],
    );
    $router->post(
        '/admin/amenities/{id}',
        [$amenityController, 'update'],
        [$requireAdministrator, $csrf],
    );
    $router->post(
        '/admin/amenities/{id}/activate',
        [$amenityController, 'activate'],
        [$requireAdministrator, $csrf],
    );
    $router->post(
        '/admin/amenities/{id}/deactivate',
        [$amenityController, 'deactivate'],
        [$requireAdministrator, $csrf],
    );
};
