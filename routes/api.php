<?php

declare(strict_types=1);

use App\Controllers\AdministratorAuthenticationController;
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
): void {
    $dependencyCount = count(array_filter(
        [$controller, $guestOnly, $requireAdministrator, $csrf],
        static fn (object|null $dependency): bool => $dependency !== null,
    ));

    if ($dependencyCount === 0) {
        return;
    }

    if ($dependencyCount !== 4) {
        throw new \LogicException('Administrator JSON routes require complete authentication dependencies.');
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
