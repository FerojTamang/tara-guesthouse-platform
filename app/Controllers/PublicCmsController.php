<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\CmsException;
use App\Services\PublicCmsServiceProvider;

/** Handles public homepage, published CMS pages, and exact CMS read APIs. */
final readonly class PublicCmsController
{
    public function __construct(
        private ViewRenderer $views,
        private PublicCmsServiceProvider $services,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function home(Request $request, array $parameters): Response
    {
        try {
            $home = $this->services->service()->homepage();
            $html = $this->views->render(
                'public.home.index',
                [
                    'title' => $home['title'],
                    'siteName' => $home['site_name'],
                    'metaDescription' => $home['meta_description'],
                    'canonicalUrl' => $home['canonical_url'],
                    'openGraphTitle' => $home['title'],
                    'openGraphDescription' => $home['meta_description'],
                    'navigationPages' => $home['navigation_pages'],
                    'footerSettings' => $home['footer_settings'],
                    'rooms' => $home['rooms'],
                    'activities' => $home['activities'],
                    'galleryItems' => $home['gallery'],
                    'reviews' => $home['reviews'],
                    'isHome' => true,
                ],
                'layouts.base',
            );

            return Response::html($html, 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException) {
            return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }

    /** @param array<string, string> $parameters */
    public function page(Request $request, array $parameters): Response
    {
        try {
            $service = $this->services->service();
            $page = $service->page($parameters['slug'] ?? '');
            $layout = $service->layoutContext();
            $title = $page['meta_title'] ?? $page['title'];
            $description = $page['meta_description'];
            $html = $this->views->render(
                'public.pages.show',
                [
                    'title' => $title,
                    'page' => $page,
                    'siteName' => $page['site_name'],
                    'metaDescription' => $description,
                    'canonicalUrl' => $page['canonical_url'],
                    'openGraphTitle' => $title,
                    'openGraphDescription' => $description,
                    'navigationPages' => $layout['navigation_pages'],
                    'footerSettings' => $layout['footer_settings'],
                ],
                'layouts.base',
            );

            return Response::html($html, 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            if ($exception->isNotFound()) {
                return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
            }

            return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiPage(Request $request, array $parameters): Response
    {
        try {
            $page = $this->services->service()->page($parameters['slug'] ?? '');

            return Response::json(
                [
                    'success' => true,
                    'data' => [
                        'page_id' => $page['page_id'],
                        'title' => $page['title'],
                        'slug' => $page['slug'],
                        'content' => $page['content'],
                        'meta_title' => $page['meta_title'],
                        'meta_description' => $page['meta_description'],
                        'published_at' => $page['published_at'],
                    ],
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (CmsException $exception) {
            if ($exception->isNotFound()) {
                return Response::json(
                    ['success' => false, 'message' => 'Not Found.'],
                    404,
                    ['Cache-Control' => 'no-store'],
                );
            }

            return Response::json(
                ['success' => false, 'message' => 'Internal Server Error.'],
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }

    /** @param array<string, string> $parameters */
    public function apiSettings(Request $request, array $parameters): Response
    {
        try {
            return Response::json(
                ['success' => true, 'data' => $this->services->service()->publicSettings()],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (CmsException) {
            return Response::json(
                ['success' => false, 'message' => 'Internal Server Error.'],
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }
}
