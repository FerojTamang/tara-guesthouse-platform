<?php

declare(strict_types=1);

use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicBookingPageController;
use App\Controllers\PublicRoomController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;

return static function (Router $router, ViewRenderer $viewRenderer, mixed ...$dependencies): void {
    $publicRooms = null;
    $publicAvailability = null;
    $publicBooking = null;
    $csrf = null;
    $publicBookingRateLimit = null;

    foreach ($dependencies as $dependency) {
        if ($dependency instanceof PublicRoomController) {
            $publicRooms ??= $dependency;
        } elseif ($dependency instanceof PublicAvailabilityController) {
            $publicAvailability ??= $dependency;
        } elseif ($dependency instanceof PublicBookingPageController) {
            $publicBooking ??= $dependency;
        } elseif ($dependency instanceof CsrfMiddleware) {
            $csrf ??= $dependency;
        } elseif ($dependency instanceof PublicBookingRateLimitMiddleware) {
            $publicBookingRateLimit ??= $dependency;
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

    if ($publicAvailability !== null) {
        $router->get('/availability', $publicAvailability->page(...));
    }

    if ($publicBooking !== null) {
        if ($csrf === null || $publicBookingRateLimit === null) {
            throw new \LogicException(
                'Public booking pages require complete security dependencies.',
            );
        }

        $router->get('/booking', $publicBooking->form(...));
        $router->post(
            '/booking',
            $publicBooking->submit(...),
            [$csrf, $publicBookingRateLimit->forWeb()],
        );
        $router->get('/booking/success', $publicBooking->success(...));
    }
};
