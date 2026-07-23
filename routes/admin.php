<?php

declare(strict_types=1);

use App\Controllers\AdministratorAmenityController;
use App\Controllers\AdministratorAuthenticationController;
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
