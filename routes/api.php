<?php

declare(strict_types=1);

use App\Controllers\AdministratorActivityController;
use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorBookingController;
use App\Controllers\AdministratorCmsController;
use App\Controllers\AdministratorContactController;
use App\Controllers\AdministratorGalleryController;
use App\Controllers\AdministratorInvoiceController;
use App\Controllers\AdministratorPaymentController;
use App\Controllers\AdministratorReviewController;
use App\Controllers\PublicActivityController;
use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicGalleryController;
use App\Controllers\PublicBookingController;
use App\Controllers\PublicCmsController;
use App\Controllers\PublicContactController;
use App\Controllers\PublicPaymentController;
use App\Controllers\PublicPaymentMethodController;
use App\Controllers\PublicReviewController;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Middleware\PublicContactRateLimitMiddleware;
use App\Middleware\PublicPaymentRateLimitMiddleware;
use App\Middleware\PublicReviewRateLimitMiddleware;
use App\Middleware\RequireAdministratorMiddleware;

return static function (
    Router $router,
    ViewRenderer $viewRenderer,
    mixed ...$dependencies,
): void {
    $controller = null;
    $administratorActivity = null;
    $administratorBooking = null;
    $administratorCms = null;
    $administratorContact = null;
    $administratorGallery = null;
    $administratorInvoice = null;
    $administratorPayment = null;
    $administratorReview = null;
    $guestOnly = null;
    $requireAdministrator = null;
    $csrf = null;
    $publicActivities = null;
    $publicAvailability = null;
    $publicGallery = null;
    $publicBooking = null;
    $publicCms = null;
    $publicContact = null;
    $publicPayment = null;
    $publicPaymentMethods = null;
    $publicReviews = null;
    $publicBookingRateLimit = null;
    $publicPaymentRateLimit = null;
    $publicContactRateLimit = null;
    $publicReviewRateLimit = null;

    foreach ($dependencies as $dependency) {
        if ($dependency instanceof AdministratorActivityController) {
            $administratorActivity ??= $dependency;
        } elseif ($dependency instanceof AdministratorAuthenticationController) {
            $controller ??= $dependency;
        } elseif ($dependency instanceof AdministratorBookingController) {
            $administratorBooking ??= $dependency;
        } elseif ($dependency instanceof AdministratorCmsController) {
            $administratorCms ??= $dependency;
        } elseif ($dependency instanceof AdministratorContactController) {
            $administratorContact ??= $dependency;
        } elseif ($dependency instanceof AdministratorGalleryController) {
            $administratorGallery ??= $dependency;
        } elseif ($dependency instanceof AdministratorPaymentController) {
            $administratorPayment ??= $dependency;
        } elseif ($dependency instanceof AdministratorReviewController) {
            $administratorReview ??= $dependency;
        } elseif ($dependency instanceof AdministratorInvoiceController) {
            $administratorInvoice ??= $dependency;
        } elseif ($dependency instanceof GuestOnlyMiddleware) {
            $guestOnly ??= $dependency;
        } elseif ($dependency instanceof RequireAdministratorMiddleware) {
            $requireAdministrator ??= $dependency;
        } elseif ($dependency instanceof CsrfMiddleware) {
            $csrf ??= $dependency;
        } elseif ($dependency instanceof PublicActivityController) {
            $publicActivities ??= $dependency;
        } elseif ($dependency instanceof PublicAvailabilityController) {
            $publicAvailability ??= $dependency;
        } elseif ($dependency instanceof PublicGalleryController) {
            $publicGallery ??= $dependency;
        } elseif ($dependency instanceof PublicBookingController) {
            $publicBooking ??= $dependency;
        } elseif ($dependency instanceof PublicCmsController) {
            $publicCms ??= $dependency;
        } elseif ($dependency instanceof PublicContactController) {
            $publicContact ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentController) {
            $publicPayment ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentMethodController) {
            $publicPaymentMethods ??= $dependency;
        } elseif ($dependency instanceof PublicReviewController) {
            $publicReviews ??= $dependency;
        } elseif ($dependency instanceof PublicBookingRateLimitMiddleware) {
            $publicBookingRateLimit ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentRateLimitMiddleware) {
            $publicPaymentRateLimit ??= $dependency;
        } elseif ($dependency instanceof PublicContactRateLimitMiddleware) {
            $publicContactRateLimit ??= $dependency;
        } elseif ($dependency instanceof PublicReviewRateLimitMiddleware) {
            $publicReviewRateLimit ??= $dependency;
        }
    }

    $administratorDependencyCount = count(array_filter(
        [$controller, $guestOnly, $requireAdministrator],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));
    $bookingDependencyCount = count(array_filter(
        [$publicBooking, $publicBookingRateLimit],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));
    $paymentDependencyCount = count(array_filter(
        [$publicPayment, $publicPaymentRateLimit],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));
    $contactDependencyCount = count(array_filter(
        [$publicContact, $publicContactRateLimit],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));

    if (
        $administratorDependencyCount !== 0
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator JSON routes require complete authentication dependencies.',
        );
    }

    if (
        $administratorActivity !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator activity JSON routes require complete security dependencies.',
        );
    }

    if (
        $administratorGallery !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator gallery JSON routes require complete security dependencies.',
        );
    }

    if (
        $administratorCms !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator CMS JSON routes require complete security dependencies.',
        );
    }

    if (
        $administratorReview !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator review JSON routes require complete security dependencies.',
        );
    }

    if (
        $administratorContact !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator contact JSON routes require complete security dependencies.',
        );
    }

    if (
        $administratorBooking !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator booking JSON routes require complete security dependencies.',
        );
    }

    if (
        $administratorPayment !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator payment JSON routes require complete security dependencies.',
        );
    }

    if (
        $administratorInvoice !== null
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator invoice JSON routes require complete security dependencies.',
        );
    }

    if (
        $bookingDependencyCount !== 0
        && ($bookingDependencyCount !== 2 || $csrf === null)
    ) {
        throw new \LogicException(
            'Public booking routes require complete security dependencies.',
        );
    }

    if (
        $paymentDependencyCount !== 0
        && ($paymentDependencyCount !== 2 || $csrf === null)
    ) {
        throw new \LogicException(
            'Public payment routes require complete security dependencies.',
        );
    }

    if (
        $contactDependencyCount !== 0
        && ($contactDependencyCount !== 2 || $csrf === null)
    ) {
        throw new \LogicException(
            'Public contact routes require complete security dependencies.',
        );
    }

    if ($publicReviewRateLimit !== null && ($publicReviews === null || $csrf === null)) {
        throw new \LogicException(
            'Public review submissions require complete security dependencies.',
        );
    }

    if ($publicActivities !== null) {
        $router->get('/api/v1/activities', $publicActivities->index(...));
    }

    if ($publicGallery !== null) {
        $router->get('/api/v1/gallery', $publicGallery->index(...));
    }

    if ($publicAvailability !== null) {
        $router->get('/api/v1/availability', $publicAvailability->index(...));
    }

    if ($publicPaymentMethods !== null) {
        $router->get('/api/v1/payment-methods', $publicPaymentMethods->index(...));
    }

    if ($bookingDependencyCount === 2) {
        $router->post(
            '/api/v1/bookings',
            $publicBooking->create(...),
            [$csrf->forJson(), $publicBookingRateLimit],
        );
    }

    if ($paymentDependencyCount === 2) {
        $router->post(
            '/api/v1/payments',
            $publicPayment->create(...),
            [$csrf->forJson(), $publicPaymentRateLimit],
        );
    }

    if ($contactDependencyCount === 2) {
        $router->post(
            '/api/v1/contact',
            [$publicContact, 'apiCreate'],
            [$csrf->forJson(), $publicContactRateLimit],
        );
    }

    if ($publicReviews !== null) {
        $router->get('/api/v1/reviews', [$publicReviews, 'apiIndex']);
    }

    if ($publicReviews !== null && $publicReviewRateLimit !== null) {
        $router->post(
            '/api/v1/reviews',
            [$publicReviews, 'apiCreate'],
            [$csrf->forJson(), $publicReviewRateLimit],
        );
    }

    if ($publicCms !== null) {
        $router->get('/api/v1/pages/{slug}', [$publicCms, 'apiPage']);
        $router->get('/api/v1/settings/public', [$publicCms, 'apiSettings']);
    }

    if ($administratorDependencyCount === 0) {
        return;
    }

    $router->post(
        '/api/v1/admin/auth/login',
        [$controller, 'apiLogin'],
        [$guestOnly->forJson(), $csrf->forJson()],
    );
    $router->post(
        '/api/v1/admin/auth/logout',
        [$controller, 'apiLogout'],
        [$requireAdministrator->forJson(), $csrf->forJson()],
    );

    if ($administratorActivity !== null) {
        $router->get(
            '/api/v1/admin/activities',
            [$administratorActivity, 'apiIndex'],
            [$requireAdministrator->forJson()],
        );
        $router->post(
            '/api/v1/admin/activities',
            [$administratorActivity, 'apiCreate'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->get(
            '/api/v1/admin/activities/{id}',
            [$administratorActivity, 'apiShow'],
            [$requireAdministrator->forJson()],
        );
        $router->put(
            '/api/v1/admin/activities/{id}',
            [$administratorActivity, 'apiUpdate'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->patch(
            '/api/v1/admin/activities/{id}/active',
            [$administratorActivity, 'apiSetActive'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->patch(
            '/api/v1/admin/activities/{id}/publication',
            [$administratorActivity, 'apiSetPublication'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->delete(
            '/api/v1/admin/activities/{id}',
            [$administratorActivity, 'apiDelete'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
    }

    if ($administratorGallery !== null) {
        $router->get(
            '/api/v1/admin/gallery-categories',
            [$administratorGallery, 'apiCategories'],
            [$requireAdministrator->forJson()],
        );
        $router->post(
            '/api/v1/admin/gallery-categories',
            [$administratorGallery, 'apiCreateCategory'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->put(
            '/api/v1/admin/gallery-categories/{id}',
            [$administratorGallery, 'apiUpdateCategory'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->get(
            '/api/v1/admin/gallery',
            [$administratorGallery, 'apiIndex'],
            [$requireAdministrator->forJson()],
        );
        $router->post(
            '/api/v1/admin/gallery',
            [$administratorGallery, 'apiCreate'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->put(
            '/api/v1/admin/gallery/{id}',
            [$administratorGallery, 'apiUpdate'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->patch(
            '/api/v1/admin/gallery/{id}/active',
            [$administratorGallery, 'apiSetActive'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->patch(
            '/api/v1/admin/gallery/{id}/publication',
            [$administratorGallery, 'apiSetPublication'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->patch(
            '/api/v1/admin/gallery/reorder',
            [$administratorGallery, 'apiReorder'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
        $router->delete(
            '/api/v1/admin/gallery/{id}',
            [$administratorGallery, 'apiDelete'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
    }

    if ($administratorCms !== null) {
        $router->get('/api/v1/admin/settings', [$administratorCms, 'apiSettings'], [$requireAdministrator->forJson()]);
        $router->put('/api/v1/admin/settings', [$administratorCms, 'apiUpdateSettings'], [$requireAdministrator->forJson(), $csrf->forJson()]);
        $router->get('/api/v1/admin/pages', [$administratorCms, 'apiPages'], [$requireAdministrator->forJson()]);
        $router->post('/api/v1/admin/pages', [$administratorCms, 'apiCreatePage'], [$requireAdministrator->forJson(), $csrf->forJson()]);
        $router->get('/api/v1/admin/pages/{id}', [$administratorCms, 'apiShowPage'], [$requireAdministrator->forJson()]);
        $router->put('/api/v1/admin/pages/{id}', [$administratorCms, 'apiUpdatePage'], [$requireAdministrator->forJson(), $csrf->forJson()]);
        $router->patch('/api/v1/admin/pages/{id}/status', [$administratorCms, 'apiTransitionPage'], [$requireAdministrator->forJson(), $csrf->forJson()]);
    }

    if ($administratorReview !== null) {
        $router->get(
            '/api/v1/admin/reviews',
            [$administratorReview, 'apiIndex'],
            [$requireAdministrator->forJson()],
        );
        $router->get(
            '/api/v1/admin/reviews/{id}',
            [$administratorReview, 'apiShow'],
            [$requireAdministrator->forJson()],
        );
        $router->patch(
            '/api/v1/admin/reviews/{id}/status',
            [$administratorReview, 'apiTransition'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
    }

    if ($administratorContact !== null) {
        $router->get(
            '/api/v1/admin/contact-messages',
            [$administratorContact, 'apiIndex'],
            [$requireAdministrator->forJson()],
        );
        $router->get(
            '/api/v1/admin/contact-messages/{id}',
            [$administratorContact, 'apiShow'],
            [$requireAdministrator->forJson()],
        );
    }

    if ($administratorBooking !== null) {
        $router->patch(
            '/api/v1/admin/bookings/{id}/status',
            [$administratorBooking, 'updateStatus'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
    }

    if ($administratorPayment !== null) {
        $router->patch(
            '/api/v1/admin/payments/{id}/status',
            [$administratorPayment, 'updateStatus'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
    }

    if ($administratorInvoice !== null) {
        $router->post(
            '/api/v1/admin/bookings/{bookingId}/invoice',
            [$administratorInvoice, 'issue'],
            [$requireAdministrator->forJson(), $csrf->forJson()],
        );
    }
};
