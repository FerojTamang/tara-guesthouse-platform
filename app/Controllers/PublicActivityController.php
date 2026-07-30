<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\ActivityException;
use App\Services\PublicActivityServiceProvider;

/**
 * Handles the public Version 1 activity page and JSON catalogue.
 */
final readonly class PublicActivityController
{
    public function __construct(
        private ViewRenderer $views,
        private PublicActivityServiceProvider $services,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function page(Request $request, array $parameters): Response
    {
        try {
            $html = $this->views->render(
                'public.activities.index',
                [
                    'title' => 'Activities | Tara Guest House',
                    'activities' => $this->services->service()->list(),
                ],
                'layouts.base',
            );

            return Response::html(
                $html,
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ActivityException) {
            return Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            return Response::json(
                [
                    'success' => true,
                    'data' => $this->services->service()->list(),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ActivityException) {
            return Response::json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error.',
                ],
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }
}
