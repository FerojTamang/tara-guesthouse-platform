<?php

declare(strict_types=1);

use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicBookingController;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Middleware\RequireAdministratorMiddleware;

return static function (
    Router $router,
    ViewRenderer $viewRenderer,
    mixed ...$dependencies,
): void {
    $controller = null;
    $guestOnly = null;
    $requireAdministrator = null;
    $csrf = null;
    $publicAvailability = null;
    $publicBooking = null;
    $publicBookingRateLimit = null;

    foreach ($dependencies as $dependency) {
        if ($dependency instanceof AdministratorAuthenticationController) {
            $controller ??= $dependency;
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
        } elseif ($dependency instanceof PublicBookingRateLimitMiddleware) {
            $publicBookingRateLimit ??= $dependency;
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

    if (
        $administratorDependencyCount !== 0
        && ($administratorDependencyCount !== 3 || $csrf === null)
    ) {
        throw new \LogicException(
            'Administrator JSON routes require complete authentication dependencies.',
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

    if ($publicAvailability !== null) {
        $router->get('/api/v1/availability', $publicAvailability->index(...));
    }

    if ($bookingDependencyCount === 2) {
        $router->post(
            '/api/v1/bookings',
            $publicBooking->create(...),
            [$csrf->forJson(), $publicBookingRateLimit],
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
};
