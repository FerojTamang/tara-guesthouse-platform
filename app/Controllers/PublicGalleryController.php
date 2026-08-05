<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\GalleryException;
use App\Services\PublicGalleryServiceProvider;

/**
 * Handles the public Version 1 gallery page, JSON catalogue, and image delivery.
 */
final readonly class PublicGalleryController
{
    public function __construct(
        private ViewRenderer $views,
        private PublicGalleryServiceProvider $services,
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
                'public.gallery.index',
                [
                    'title' => 'Gallery | Tara Guest House',
                    'galleryItems' => $this->services->service()->list(),
                    'isGallery' => true,
                ],
                'layouts.base',
            );

            return Response::html($html, 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException) {
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
        } catch (GalleryException) {
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

            return new Response(
                $image->bytes(),
                200,
                [
                    'Content-Type' => $image->mediaType(),
                    'Content-Length' => (string) $image->size(),
                    'Content-Disposition' => sprintf(
                        'inline; filename="gallery-image-%d.%s"',
                        $identifier,
                        $image->extension(),
                    ),
                    'Cache-Control' => 'no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            );
        } catch (GalleryException $exception) {
            if ($exception->isNotFound()) {
                return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
            }

            return Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }
}
