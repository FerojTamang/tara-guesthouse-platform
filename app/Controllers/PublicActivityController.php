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
        $query = $request->query();

        if (is_array($query) && $query !== []) {
            return $this->image($request);
        }

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

    private function image(Request $request): Response
    {
        $query = $request->query();

        if (
            !is_array($query)
            || array_keys($query) !== ['image']
            || !is_string($query['image'])
            || preg_match('/^[1-9][0-9]*$/D', $query['image']) !== 1
        ) {
            return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
        }

        $identifier = filter_var($query['image'], FILTER_VALIDATE_INT);

        if (!is_int($identifier) || $identifier <= 0) {
            return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
        }

        try {
            $image = $this->services->service()->image($identifier);

            return new Response($image->bytes(), 200, [
                'Content-Type' => $image->mediaType(),
                'Content-Length' => (string) $image->size(),
                'Content-Disposition' => sprintf(
                    'inline; filename="activity-image-%d.%s"',
                    $identifier,
                    $image->extension(),
                ),
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (ActivityException $exception) {
            return $exception->isNotFound()
                ? Response::text('Not Found', 404, ['Cache-Control' => 'no-store'])
                : Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }
}
