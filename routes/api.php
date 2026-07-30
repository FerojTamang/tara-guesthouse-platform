<?php

declare(strict_types=1);

use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorBookingController;
use App\Controllers\AdministratorPaymentController;
use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicBookingController;
use App\Controllers\PublicPaymentController;
use App\Controllers\PublicPaymentMethodController;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Middleware\PublicPaymentRateLimitMiddleware;
use App\Middleware\RequireAdministratorMiddleware;

return static function (
    Router $router,
    ViewRenderer $viewRenderer,
    mixed ...$dependencies,
): void {
    $controller = null;
    $administratorBooking = null;
    $administratorPayment = null;
    $guestOnly = null;
    $requireAdministrator = null;
    $csrf = null;
    $publicAvailability = null;
    $publicBooking = null;
    $publicPayment = null;
    $publicPaymentMethods = null;
    $publicBookingRateLimit = null;
    $publicPaymentRateLimit = null;

    foreach ($dependencies as $dependency) {
        if ($dependency instanceof AdministratorAuthenticationController) {
            $controller ??= $dependency;
        } elseif ($dependency instanceof AdministratorBookingController) {
            $administratorBooking ??= $dependency;
        } elseif ($dependency instanceof AdministratorPaymentController) {
            $administratorPayment ??= $dependency;
        } elseif ($dependency instanceof GuestOnlyMiddleware) {
            $guestOnly ??= $dependency;
        } elseif ($dependency instanceof RequireAdministratorMiddleware) {
            $requireAdministrator ??= $dependency;
        } elseif ($dependency instanceof CsrfMiddleware) {
            $csrf ??= $dependency;
        } elseif ($dependency instanceof PublicAvailabilityController) {
            $publicAvailability ??= $dependency;
        } elseif ($dependency instanceof PublicBookingController) {
            $publicBooking ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentController) {
            $publicPayment ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentMethodController) {
            $publicPaymentMethods ??= $dependency;
        } elseif ($dependency instanceof PublicBookingRateLimitMiddleware) {
            $publicBookingRateLimit ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentRateLimitMiddleware) {
            $publicPaymentRateLimit ??= $dependency;
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

    if (
        $administratorDependencyCount !== 0
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator JSON routes require complete authentication dependencies.',
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
};
