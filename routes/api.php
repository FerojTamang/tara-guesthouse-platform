<?php

declare(strict_types=1);

use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\PublicAvailabilityController;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestOnlyMiddleware;
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
        }
    }

    $dependencyCount = count(array_filter(
        [$controller, $guestOnly, $requireAdministrator, $csrf],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));

    if ($dependencyCount !== 0 && $dependencyCount !== 4) {
        throw new \LogicException(
            'Administrator JSON routes require complete authentication dependencies.',
        );
    }

    if ($publicAvailability !== null) {
        $router->get('/api/v1/availability', $publicAvailability->index(...));
    }

    if ($dependencyCount === 0) {
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
