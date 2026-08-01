<?php

declare(strict_types=1);

use App\Controllers\PublicActivityController;
use App\Controllers\PublicAvailabilityController;
use App\Controllers\PublicGalleryController;
use App\Controllers\PublicBookingPageController;
use App\Controllers\PublicCmsController;
use App\Controllers\PublicContactController;
use App\Controllers\PublicPaymentPageController;
use App\Controllers\PublicReviewController;
use App\Controllers\PublicRoomController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PublicBookingRateLimitMiddleware;
use App\Middleware\PublicContactRateLimitMiddleware;
use App\Middleware\PublicPaymentRateLimitMiddleware;
use App\Middleware\PublicReviewRateLimitMiddleware;

return static function (Router $router, ViewRenderer $viewRenderer, mixed ...$dependencies): void {
    $publicActivities = null;
    $publicCms = null;
    $publicContact = null;
    $publicGallery = null;
    $publicRooms = null;
    $publicAvailability = null;
    $publicBooking = null;
    $publicPayment = null;
    $publicReviews = null;
    $csrf = null;
    $publicBookingRateLimit = null;
    $publicPaymentRateLimit = null;
    $publicContactRateLimit = null;
    $publicReviewRateLimit = null;

    foreach ($dependencies as $dependency) {
        if ($dependency instanceof PublicActivityController) {
            $publicActivities ??= $dependency;
        } elseif ($dependency instanceof PublicCmsController) {
            $publicCms ??= $dependency;
        } elseif ($dependency instanceof PublicContactController) {
            $publicContact ??= $dependency;
        } elseif ($dependency instanceof PublicGalleryController) {
            $publicGallery ??= $dependency;
        } elseif ($dependency instanceof PublicRoomController) {
            $publicRooms ??= $dependency;
        } elseif ($dependency instanceof PublicAvailabilityController) {
            $publicAvailability ??= $dependency;
        } elseif ($dependency instanceof PublicBookingPageController) {
            $publicBooking ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentPageController) {
            $publicPayment ??= $dependency;
        } elseif ($dependency instanceof PublicReviewController) {
            $publicReviews ??= $dependency;
        } elseif ($dependency instanceof CsrfMiddleware) {
            $csrf ??= $dependency;
        } elseif ($dependency instanceof PublicBookingRateLimitMiddleware) {
            $publicBookingRateLimit ??= $dependency;
        } elseif ($dependency instanceof PublicPaymentRateLimitMiddleware) {
            $publicPaymentRateLimit ??= $dependency;
        } elseif ($dependency instanceof PublicContactRateLimitMiddleware) {
            $publicContactRateLimit ??= $dependency;
        } elseif ($dependency instanceof PublicReviewRateLimitMiddleware) {
            $publicReviewRateLimit ??= $dependency;
        }
    }

    if ($publicCms !== null) {
        $router->get('/', $publicCms->home(...));
    } else {
        $router->get('/', static function (Request $request, array $parameters) use ($viewRenderer): Response {
            $html = $viewRenderer->render(
                'infrastructure.welcome',
                ['title' => 'Tara Guest House Application'],
                'layouts.base',
            );

            return Response::html($html);
        });
    }

    if ($publicActivities !== null) {
        $router->get('/activities', $publicActivities->page(...));
    }

    if ($publicGallery !== null) {
        $router->get('/gallery', $publicGallery->page(...));
    }

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

    if ($publicPayment !== null) {
        if ($csrf === null || $publicPaymentRateLimit === null) {
            throw new \LogicException(
                'Public payment pages require complete security dependencies.',
            );
        }

        $router->get('/booking/payment', $publicPayment->form(...));
        $router->post(
            '/booking/payment',
            $publicPayment->submit(...),
            [$csrf, $publicPaymentRateLimit->forWeb()],
        );
        $router->get('/booking/payment/success', $publicPayment->success(...));
    }

    if ($publicContact !== null) {
        if ($csrf === null || $publicContactRateLimit === null) {
            throw new \LogicException(
                'Public contact pages require complete security dependencies.',
            );
        }

        $router->get('/contact', $publicContact->page(...));
        $router->post(
            '/contact',
            $publicContact->submit(...),
            [$csrf, $publicContactRateLimit->forWeb()],
        );
    }

    if ($publicReviewRateLimit !== null && ($publicReviews === null || $csrf === null)) {
        throw new \LogicException(
            'Public review submissions require complete security dependencies.',
        );
    }

    if ($publicReviews !== null) {
        $router->get('/reviews', $publicReviews->page(...));
        $router->get('/review/{token}', $publicReviews->invitation(...));

        if ($publicReviewRateLimit !== null) {
            $router->post(
                '/review/{token}',
                $publicReviews->submit(...),
                [$publicReviewRateLimit->forWeb(), $csrf],
            );
        }
    }

    if ($publicCms !== null) {
        $router->get('/{slug}', $publicCms->page(...));
    }
};
