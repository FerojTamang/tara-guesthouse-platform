<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\CmsException;
use App\Services\PublicCmsServiceProvider;

/** Handles the public homepage, content collections, published CMS pages, and exact read APIs. */
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
                    'latestNews' => $home['latest_news'],
                    'activeNotice' => $home['active_notice'],
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
    public function newsIndex(Request $request, array $parameters): Response
    {
        try {
            $news = $this->services->service()->newsIndex();
            $html = $this->views->render(
                'public.news.index',
                [
                    'title' => $news['title'],
                    'metaDescription' => $news['meta_description'],
                    'canonicalUrl' => $news['canonical_url'],
                    'openGraphTitle' => $news['title'],
                    'openGraphDescription' => $news['meta_description'],
                    'items' => $news['items'],
                    'isPublishing' => true,
                ],
                'layouts.base',
            );

            return Response::html($html, 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException) {
            return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }

    /** @param array<string, string> $parameters */
    public function noticesIndex(Request $request, array $parameters): Response
    {
        try {
            $notices = $this->services->service()->noticesIndex();
            $html = $this->views->render(
                'public.notices.index',
                [
                    'title' => $notices['title'],
                    'metaDescription' => $notices['meta_description'],
                    'canonicalUrl' => $notices['canonical_url'],
                    'openGraphTitle' => $notices['title'],
                    'openGraphDescription' => $notices['meta_description'],
                    'items' => $notices['items'],
                    'isPublishing' => true,
                ],
                'layouts.base',
            );

            return Response::html($html, 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException) {
            return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }

    /** @param array<string, string> $parameters */
    public function newsArticle(Request $request, array $parameters): Response
    {
        try {
            return $this->renderPage(
                $this->services->service()->newsArticle($parameters['slug'] ?? ''),
                '/news',
                'All news',
            );
        } catch (CmsException $exception) {
            return $this->contentFailure($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function notice(Request $request, array $parameters): Response
    {
        try {
            return $this->renderPage(
                $this->services->service()->notice($parameters['slug'] ?? ''),
                '/notices',
                'All notices',
            );
        } catch (CmsException $exception) {
            return $this->contentFailure($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function page(Request $request, array $parameters): Response
    {
        try {
            $page = $this->services->service()->page($parameters['slug'] ?? '');
            $contentType = $page['content_type'] ?? 'page';
            if ($contentType === 'news') {
                return Response::redirect('/news/' . $page['slug'], 301, ['Cache-Control' => 'no-store']);
            }
            if ($contentType === 'notice') {
                return Response::redirect('/notices/' . $page['slug'], 301, ['Cache-Control' => 'no-store']);
            }

            return $this->renderPage($page);
        } catch (CmsException $exception) {
            return $this->contentFailure($exception);
        }
    }

    /** @param array<string, mixed> $page */
    private function renderPage(array $page, ?string $indexHref = null, ?string $indexLabel = null): Response
    {
        $service = $this->services->service();
        $layout = $service->layoutContext();
        $title = $page['meta_title'] ?? $page['title'];
        $description = $page['meta_description'] ?? $page['summary'];
        $socialTitle = $page['social_title'] ?? $title;
        $socialDescription = $page['social_description'] ?? $description;
        $html = $this->views->render(
            'public.pages.show',
            [
                'title' => $title,
                'page' => $page,
                'siteName' => $page['site_name'],
                'metaDescription' => $description,
                'canonicalUrl' => $page['canonical_url'],
                'openGraphTitle' => $socialTitle,
                'openGraphDescription' => $socialDescription,
                'openGraphImage' => $page['social_image'],
                'openGraphType' => in_array($page['content_type'] ?? null, ['news', 'notice'], true)
                    ? 'article'
                    : 'website',
                'robotsNoindex' => $page['seo_noindex'],
                'navigationPages' => $layout['navigation_pages'],
                'footerSettings' => $layout['footer_settings'],
                'contentIndexHref' => $indexHref,
                'contentIndexLabel' => $indexLabel,
                'isPublishing' => true,
            ],
            'layouts.base',
        );

        return Response::html($html, 200, ['Cache-Control' => 'no-store']);
    }

    private function contentFailure(CmsException $exception): Response
    {
        if ($exception->isNotFound()) {
            return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
        }

        return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
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
