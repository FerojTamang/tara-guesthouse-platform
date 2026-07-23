<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Controllers\PublicRoomController;

return static function (Router $router, ViewRenderer $viewRenderer, mixed ...$dependencies): void {
    $publicRooms = null;
    foreach ($dependencies as $dependency) {
        if ($dependency instanceof PublicRoomController) {
            $publicRooms = $dependency;
            break;
        }
    }
    $router->get('/', static function (Request $request, array $parameters) use ($viewRenderer): Response {
        $html = $viewRenderer->render(
            'infrastructure.welcome',
            ['title' => 'Tara Guest House Application'],
            'layouts.base',
        );

        return Response::html($html);
    });

    if ($publicRooms !== null) {
        $router->get('/rooms', $publicRooms->index(...));
        $router->get('/rooms/{slug}', $publicRooms->show(...));
    }
};
