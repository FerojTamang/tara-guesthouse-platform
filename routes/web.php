<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ViewRenderer;

return static function (Router $router, ViewRenderer $viewRenderer): void {
    $router->get('/', static function (Request $request, array $parameters) use ($viewRenderer): Response {
        $html = $viewRenderer->render(
            'infrastructure.welcome',
            ['title' => 'Tara Guest House Application'],
            'layouts.base',
        );

        return Response::html($html);
    });
};
