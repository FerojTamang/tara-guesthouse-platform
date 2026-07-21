<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', static function (Request $request, array $parameters): Response {
        return Response::text('Tara Guest House application bootstrap is running.');
    });
};
