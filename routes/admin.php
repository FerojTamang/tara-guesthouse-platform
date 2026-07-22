<?php

declare(strict_types=1);

use App\Controllers\AdministratorAuthenticationController;
use App\Controllers\AdministratorRoomCatalogController;
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
): void {
    $dependencyCount = count(array_filter(
        [$controller, $guestOnly, $requireAdministrator, $csrf],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));

    if ($dependencyCount === 0 && $catalogController === null) {
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

    if ($catalogController === null) {
        return;
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
};
