<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\UploadedFile;
use App\Core\ViewRenderer;
use App\Exceptions\CmsException;
use App\Exceptions\ContentMediaException;
use App\Models\CmsPage;
use App\Models\ContentMedia;
use App\Models\PageContentProfile;
use App\Models\NavigationItem;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Requests\CmsPageContentRequest;
use App\Requests\CmsPagePublishingRequest;
use App\Requests\ContentMediaMetadataRequest;
use App\Requests\ContentMediaUploadRequest;
use App\Requests\CmsPageStatusRequest;
use App\Requests\CmsSettingsUpdateRequest;
use App\Requests\NavigationItemRequest;
use App\Requests\NavigationMoveRequest;
use App\Requests\SiteLogoUploadRequest;
use App\Services\CmsManagementServiceProvider;
use App\Services\ContentMediaManagementServiceProvider;
use App\Services\ContentMediaStorageProvider;
use App\Services\SettingPresentationCatalog;
use App\Services\NavigationManagementServiceProvider;
use App\Services\NavigationRouteCatalog;
use App\Storage\SiteLogoStorage;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use stdClass;

/** Renders protected CMS pages and handles the exact Administrator CMS JSON API. */
final readonly class AdministratorCmsController
{
    public function __construct(
        private ViewRenderer $views,
        private CmsManagementServiceProvider $services,
        private AdministratorSession $administratorSession,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
        private ?SiteLogoStorage $siteLogos = null,
        private ?ContentMediaManagementServiceProvider $contentMedia = null,
        private ?ContentMediaStorageProvider $contentMediaStorage = null,
        private ?NavigationManagementServiceProvider $navigation = null,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function settings(Request $request, array $parameters): Response
    {
        try {
            $settings = $this->settingRows();

            return $this->page('admin.settings.index', [
                'title' => 'Settings | Tara Guest House',
                'pageTitle' => 'Settings',
                'activeNavigation' => 'settings',
                'settings' => $settings,
                'settingGroups' => (new SettingPresentationCatalog())->grouped($settings),
            ]);
        } catch (CmsException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function pages(Request $request, array $parameters): Response
    {
        try {
            $service = $this->services->service();
            $filters = $this->pageFilters($request);
            $rows = array_map(
                fn (CmsPage $page): array => $this->pageData($page, $service->pageProfile($page->id())),
                $service->listPages(),
            );
            $filtered = array_values(array_filter(
                $rows,
                static function (array $page) use ($filters): bool {
                    if ($filters['type'] !== '' && $page['contentType'] !== $filters['type']) {
                        return false;
                    }
                    if ($filters['status'] !== '' && $page['status'] !== $filters['status']) {
                        return false;
                    }
                    if ($filters['search'] !== '') {
                        $haystack = mb_strtolower($page['title'] . ' ' . $page['slug'], 'UTF-8');
                        if (!str_contains($haystack, mb_strtolower($filters['search'], 'UTF-8'))) {
                            return false;
                        }
                    }
                    return true;
                },
            ));

            return $this->page('admin.pages.index', [
                'title' => 'Pages | Tara Guest House',
                'pageTitle' => 'Pages',
                'activeNavigation' => 'pages',
                'pages' => $filtered,
                'totalPageCount' => count($rows),
                'filters' => $filters,
                'mediaLibraryAvailable' => $this->contentMedia instanceof ContentMediaManagementServiceProvider,
            ]);
        } catch (CmsException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function navigation(Request $request, array $parameters): Response
    {
        if (!$this->navigation instanceof NavigationManagementServiceProvider) {
            return Response::text('Navigation management is not configured.', 503, ['Cache-Control' => 'no-store']);
        }

        try {
            $service = $this->navigation->service();
            $pages = $service->listPages();
            $pageRows = [];
            $pageMap = [];
            foreach ($pages as $page) {
                $row = [
                    'id' => $page->id(),
                    'title' => $page->title(),
                    'slug' => $page->slug(),
                    'status' => $page->status(),
                ];
                $pageRows[] = $row;
                $pageMap[$page->id()] = $row;
            }

            $items = $service->listItems();
            $groups = [];
            foreach ($items as $item) {
                if ($item->isGroup()) {
                    $groups[] = ['id' => $item->id(), 'label' => $item->label()];
                }
            }

            return $this->page('admin.navigation.index', [
                'title' => 'Navigation | Tara Guest House',
                'pageTitle' => 'Navigation',
                'activeNavigation' => 'navigation',
                'items' => array_map(
                    fn (NavigationItem $item): array => $this->navigationPageData($item, $pageMap),
                    $items,
                ),
                'pages' => $pageRows,
                'groups' => $groups,
                'routes' => (new NavigationRouteCatalog())->all(),
            ]);
        } catch (CmsException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function mediaLibrary(Request $request, array $parameters): Response
    {
        if (
            !$this->contentMedia instanceof ContentMediaManagementServiceProvider
            || !$this->contentMediaStorage instanceof ContentMediaStorageProvider
        ) {
            return Response::text('Content media library is not configured.', 503, ['Cache-Control' => 'no-store']);
        }

        try {
            $configuration = $this->contentMediaStorage->uploadConfiguration();

            return $this->page('admin.media.index', [
                'title' => 'Media library | Tara Guest House',
                'pageTitle' => 'Media library',
                'activeNavigation' => 'pages',
                'media' => array_map($this->mediaPageData(...), $this->contentMedia->service()->listMedia()),
                'maximumUploadBytes' => $configuration['maximum_file_size_bytes'],
                'minimumWidth' => $configuration['minimum_width_pixels'],
                'minimumHeight' => $configuration['minimum_height_pixels'],
            ]);
        } catch (ContentMediaException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function createPageForm(Request $request, array $parameters): Response
    {
        try {
            return $this->pageForm(
                'Create page',
                '/api/v1/admin/pages',
                'POST',
                [
                    'id' => null,
                    'title' => '',
                    'slug' => '',
                    'content' => '',
                    'metaTitle' => '',
                    'metaDescription' => '',
                    'noticeStyle' => '',
                    'publishAt' => '',
                    'expiresAt' => '',
                    'featured' => false,
                    'pinned' => false,
                    'featuredMediaId' => null,
                    'socialTitle' => '',
                    'socialDescription' => '',
                    'socialMediaId' => null,
                    'seoNoindex' => false,
                    'publicationState' => CmsPage::DRAFT,
                ],
            );
        } catch (CmsException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function showPage(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->notFound();
        }

        try {
            $service = $this->services->service();
            $page = $service->getPage($id);

            return $this->page('admin.pages.show', [
                'title' => $page->title() . ' | Tara Guest House',
                'pageTitle' => 'Page detail',
                'activeNavigation' => 'pages',
                'page' => $this->pageData($page, $service->pageProfile($page->id())),
            ]);
        } catch (CmsException $exception) {
            return $exception->isNotFound() ? $this->notFound() : $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function editPageForm(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->notFound();
        }

        try {
            $service = $this->services->service();
            $page = $service->getPage($id);
            $profile = $service->pageProfile($page->id());

            return $this->pageForm(
                'Edit page',
                '/api/v1/admin/pages/' . $page->id(),
                'PUT',
                [
                    'id' => $page->id(),
                    'title' => $page->title(),
                    'slug' => $page->slug(),
                    'content' => $page->content(),
                    'metaTitle' => $page->metaTitle() ?? '',
                    'metaDescription' => $page->metaDescription() ?? '',
                    'noticeStyle' => $profile?->noticeStyle() ?? '',
                    'publishAt' => $this->dateTimeLocal($profile?->publishAt()),
                    'expiresAt' => $this->dateTimeLocal($profile?->expiresAt()),
                    'featured' => $profile?->isFeatured() ?? false,
                    'pinned' => $profile?->isPinned() ?? false,
                    'featuredMediaId' => $profile?->featuredMediaId(),
                    'socialTitle' => $profile?->socialTitle() ?? '',
                    'socialDescription' => $profile?->socialDescription() ?? '',
                    'socialMediaId' => $profile?->socialMediaId(),
                    'seoNoindex' => $profile?->seoNoindex() ?? false,
                    'publicationState' => $page->publicationState(new DateTimeImmutable('now', new DateTimeZone('UTC'))),
                ],
            );
        } catch (CmsException $exception) {
            return $exception->isNotFound() ? $this->notFound() : $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function apiSettings(Request $request, array $parameters): Response
    {
        try {
            return Response::json([
                'success' => true,
                'data' => $this->settingRows(true),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUpdateSettings(Request $request, array $parameters): Response
    {
        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        if (!$this->hasExactKeys($payload, ['settings'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $updated = $this->services->service()->updateSettings(
                CmsSettingsUpdateRequest::fromInput($payload['settings']),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Settings updated.',
                'data' => array_map($this->apiSettingData(...), $updated),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'settings');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUploadLogo(Request $request, array $parameters): Response
    {
        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        if (!$this->siteLogos instanceof SiteLogoStorage) {
            return $this->jsonError('Internal Server Error.', 500);
        }

        $newPath = null;

        try {
            $upload = SiteLogoUploadRequest::fromFile($request->file('logo'));
            $previousPath = $this->storedSettingValue('site.logo');
            $newPath = $this->siteLogos->store($upload);

            $updated = $this->services->service()->updateSettings(
                CmsSettingsUpdateRequest::fromInput([
                    'site.logo' => $newPath,
                    'site.logo.enabled' => 'true',
                ]),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            if ($previousPath !== null && $previousPath !== $newPath) {
                try {
                    $this->siteLogos->remove($previousPath);
                } catch (\Throwable) {
                    // The new logo is already committed. A stale file must not break branding.
                }
            }

            return Response::json([
                'success' => true,
                'message' => 'Brand logo updated.',
                'data' => [
                    'logo_url' => '/' . $newPath,
                    'settings' => array_map($this->apiSettingData(...), $updated),
                ],
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            if ($newPath !== null) {
                try {
                    $this->siteLogos->remove($newPath);
                } catch (\Throwable) {
                }
            }

            return $this->cmsError($exception, 'logo');
        } catch (\Throwable) {
            if ($newPath !== null) {
                try {
                    $this->siteLogos->remove($newPath);
                } catch (\Throwable) {
                }
            }

            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiMedia(Request $request, array $parameters): Response
    {
        if (!$this->contentMedia instanceof ContentMediaManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }

        try {
            return Response::json([
                'success' => true,
                'data' => array_map($this->apiMediaData(...), $this->contentMedia->service()->listMedia()),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ContentMediaException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUploadMedia(Request $request, array $parameters): Response
    {
        if (
            !$this->contentMedia instanceof ContentMediaManagementServiceProvider
            || !$this->contentMediaStorage instanceof ContentMediaStorageProvider
        ) {
            return $this->jsonError('Internal Server Error.', 500);
        }

        if (!$this->usesMultipartFormData($request)) {
            return $this->jsonError('Content-Type must be multipart/form-data.', 415);
        }

        $payload = $request->input();
        $files = $request->file();

        if (
            !is_array($payload)
            || !$this->hasExactKeys($payload, ['alt_text', 'caption', 'title'])
            || !is_array($files)
            || array_keys($files) !== ['image']
            || !$files['image'] instanceof UploadedFile
        ) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $media = $this->contentMedia->service()->upload(
                ContentMediaUploadRequest::fromInput(
                    $files['image'],
                    $payload['title'],
                    $payload['alt_text'],
                    $payload['caption'],
                    $this->contentMediaStorage->uploadConfiguration(),
                ),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Image added to the media library.',
                'data' => $this->apiMediaData($media),
            ], 201, ['Cache-Control' => 'no-store']);
        } catch (ContentMediaException $exception) {
            return $this->contentMediaError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUpdateMedia(Request $request, array $parameters): Response
    {
        if (!$this->contentMedia instanceof ContentMediaManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }

        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested media item was not found.', 404);
        }

        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }
        if (!$this->hasExactKeys($payload, ['alt_text', 'caption', 'title'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $media = $this->contentMedia->service()->updateMetadata(
                $id,
                ContentMediaMetadataRequest::fromInput(
                    $payload['title'],
                    $payload['alt_text'],
                    $payload['caption'],
                ),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Media metadata updated.',
                'data' => $this->apiMediaData($media),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ContentMediaException $exception) {
            return $this->contentMediaError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiDeleteMedia(Request $request, array $parameters): Response
    {
        if (!$this->contentMedia instanceof ContentMediaManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }

        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested media item was not found.', 404);
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $this->contentMedia->service()->delete(
                $id,
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Unused media item deleted.',
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ContentMediaException $exception) {
            return $this->contentMediaError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiNavigation(Request $request, array $parameters): Response
    {
        if (!$this->navigation instanceof NavigationManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }

        try {
            return Response::json([
                'success' => true,
                'data' => array_map($this->apiNavigationData(...), $this->navigation->service()->listItems()),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCreateNavigation(Request $request, array $parameters): Response
    {
        if (!$this->navigation instanceof NavigationManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }

        $payload = $this->navigationPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }
        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $item = $this->navigation->service()->create(
                $this->navigationRequest($payload),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Navigation item added.',
                'data' => $this->apiNavigationData($item),
            ], 201, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'navigation');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUpdateNavigation(Request $request, array $parameters): Response
    {
        if (!$this->navigation instanceof NavigationManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested navigation item was not found.', 404);
        }
        $payload = $this->navigationPayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }
        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $item = $this->navigation->service()->update(
                $id,
                $this->navigationRequest($payload),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Navigation item updated.',
                'data' => $this->apiNavigationData($item),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'navigation');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiSetNavigationActive(Request $request, array $parameters): Response
    {
        if (!$this->navigation instanceof NavigationManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested navigation item was not found.', 404);
        }
        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }
        if (!$this->hasExactKeys($payload, ['is_active']) || !is_bool($payload['is_active'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }
        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $item = $this->navigation->service()->setActive(
                $id,
                $payload['is_active'],
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => $item->isActive() ? 'Navigation item enabled.' : 'Navigation item disabled.',
                'data' => $this->apiNavigationData($item),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'navigation');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiMoveNavigation(Request $request, array $parameters): Response
    {
        if (!$this->navigation instanceof NavigationManagementServiceProvider) {
            return $this->jsonError('Internal Server Error.', 500);
        }
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested navigation item was not found.', 404);
        }
        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }
        if (!$this->hasExactKeys($payload, ['direction'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }
        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $move = NavigationMoveRequest::fromInput($payload['direction']);
            $item = $this->navigation->service()->move(
                $id,
                $move->direction(),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Navigation order updated.',
                'data' => $this->apiNavigationData($item),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'navigation');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiPages(Request $request, array $parameters): Response
    {
        try {
            return Response::json([
                'success' => true,
                'data' => array_map($this->apiPageData(...), $this->services->service()->listPages()),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCreatePage(Request $request, array $parameters): Response
    {
        $payload = $this->pagePayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $page = $this->services->service()->createPage(
                $this->contentRequest($payload),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
                $this->publishingRequest($payload),
            );

            return Response::json([
                'success' => true,
                'message' => 'Page draft created.',
                'data' => $this->apiPageData($page),
            ], 201, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'page');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiShowPage(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested page was not found.', 404);
        }

        try {
            return Response::json([
                'success' => true,
                'data' => $this->apiPageData($this->services->service()->getPage($id)),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'page');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUpdatePage(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested page was not found.', 404);
        }

        $payload = $this->pagePayload($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $page = $this->services->service()->updatePage(
                $id,
                $this->contentRequest($payload),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
                $this->publishingRequest($payload),
            );

            return Response::json([
                'success' => true,
                'message' => 'Page updated.',
                'data' => $this->apiPageData($page),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'page');
        }
    }

    /** @param array<string, string> $parameters */
    public function apiTransitionPage(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);
        if ($id === null) {
            return $this->jsonError('The requested page was not found.', 404);
        }

        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        if (
            !$this->hasExactKeys($payload, ['status'])
            && !$this->hasExactKeys($payload, ['publish_at', 'status'])
        ) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $status = CmsPageStatusRequest::fromInput(
                $payload['status'],
                $payload['publish_at'] ?? null,
                $this->timezone,
            );
            $page = $this->services->service()->transitionPageStatus(
                $id,
                $status->status(),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
                $status->publishAt(),
            );

            return Response::json([
                'success' => true,
                'message' => 'Page status updated.',
                'data' => $this->apiPageData($page),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (CmsException $exception) {
            return $this->cmsError($exception, 'page');
        }
    }

    /** @return list<array<string, mixed>> */
    private function settingRows(bool $api = false): array
    {
        $service = $this->services->service();
        $stored = [];

        foreach ($service->listSettings() as $setting) {
            $stored[$setting->key()] = $setting;
        }

        $rows = [];
        foreach ($service->settingDefinitions() as $definition) {
            $setting = $stored[$definition->key()] ?? null;
            $rows[] = $api
                ? $this->apiSettingDefinitionData($definition, $setting)
                : [
                    'key' => $definition->key(),
                    'valueType' => $definition->valueType(),
                    'public' => $definition->isPublic(),
                    'maximumLength' => $definition->maximumLength(),
                    'value' => $setting?->value() ?? '',
                    'configured' => $setting instanceof SiteSetting,
                    'updatedAt' => $setting instanceof SiteSetting ? $this->dateTime($setting->updatedAt()) : null,
                ];
        }

        return $rows;
    }

    private function storedSettingValue(string $key): ?string
    {
        foreach ($this->services->service()->listSettings() as $setting) {
            if ($setting->key() === $key) {
                return $setting->value();
            }
        }

        return null;
    }

    /** @return array<string, mixed>|Response */
    private function navigationPayload(Request $request): array|Response
    {
        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        return $this->hasExactKeys($payload, [
            'label', 'link_type', 'location', 'page_id', 'parent_id', 'route_key',
        ]) ? $payload : $this->jsonError('Request payload is invalid.', 422);
    }

    /** @param array<string, mixed> $payload */
    private function navigationRequest(array $payload): NavigationItemRequest
    {
        return NavigationItemRequest::fromInput(
            $payload['location'],
            $payload['label'],
            $payload['link_type'],
            $payload['route_key'],
            $payload['page_id'],
            $payload['parent_id'],
        );
    }

    /** @return array{type: string, status: string, search: string} */
    private function pageFilters(Request $request): array
    {
        $type = $request->query('type', '');
        $status = $request->query('status', '');
        $search = $request->query('search', '');

        $type = is_string($type) && in_array($type, PageContentProfile::TYPES, true) ? $type : '';
        $status = is_string($status) && in_array($status, CmsPage::PUBLICATION_STATES, true) ? $status : '';
        if (
            !is_string($search)
            || !mb_check_encoding($search, 'UTF-8')
            || str_contains($search, "\0")
        ) {
            $search = '';
        } else {
            $search = trim($search);
            if (mb_strlen($search, 'UTF-8') > 100) {
                $search = mb_substr($search, 0, 100, 'UTF-8');
            }
        }

        return ['type' => $type, 'status' => $status, 'search' => $search];
    }

    /** @return array<string, mixed>|Response */
    private function pagePayload(Request $request): array|Response
    {
        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        return $this->hasExactKeys($payload, [
            'content', 'expires_at', 'featured_media_id', 'is_featured', 'is_pinned',
            'meta_description', 'meta_title', 'notice_style', 'publish_at', 'seo_noindex',
            'slug', 'social_description', 'social_media_id', 'social_title', 'title',
        ])
            ? $payload
            : $this->jsonError('Request payload is invalid.', 422);
    }

    /** @param array<string, mixed> $payload */
    private function contentRequest(array $payload): CmsPageContentRequest
    {
        return CmsPageContentRequest::fromInput(
            $payload['title'],
            $payload['slug'],
            $payload['content'],
            $payload['meta_title'],
            $payload['meta_description'],
        );
    }

    /** @param array<string, mixed> $payload */
    private function publishingRequest(array $payload): CmsPagePublishingRequest
    {
        return CmsPagePublishingRequest::fromInput(
            $payload['notice_style'],
            $payload['publish_at'],
            $payload['expires_at'],
            $payload['is_featured'],
            $payload['is_pinned'],
            $payload['featured_media_id'],
            $payload['social_title'],
            $payload['social_description'],
            $payload['social_media_id'],
            $payload['seo_noindex'],
            $this->timezone,
        );
    }

    /** @return array<string, mixed>|Response */
    private function jsonObject(Request $request): array|Response
    {
        if (!$this->usesJsonContentType($request)) {
            return $this->jsonError('Content-Type must be application/json.', 415);
        }

        try {
            $decoded = json_decode($request->body(), false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->jsonError('Request body must contain valid JSON.', 400);
        }

        if (!$decoded instanceof stdClass) {
            return $this->jsonError('Request body must contain a JSON object.', 400);
        }

        $normalized = $this->normalizeJson($decoded);

        return is_array($normalized) && !array_is_list($normalized)
            ? $normalized
            : $this->jsonError('Request body must contain a JSON object.', 400);
    }

    private function normalizeJson(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $normalized = [];
            foreach (get_object_vars($value) as $key => $item) {
                $normalized[$key] = $this->normalizeJson($item);
            }
            return $normalized;
        }

        if (is_array($value)) {
            return array_map($this->normalizeJson(...), $value);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload @param list<string> $expected */
    private function hasExactKeys(array $payload, array $expected): bool
    {
        $keys = array_keys($payload);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);

        return $keys === $expected;
    }

    private function cmsError(CmsException $exception, string $field): Response
    {
        if ($exception->isValidationFailure()) {
            return Response::json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [$field => [$exception->getMessage()]],
            ], 422, ['Cache-Control' => 'no-store']);
        }

        if ($exception->isNotFound()) {
            return $this->jsonError($exception->getMessage(), 404);
        }

        if ($exception->isConflict()) {
            return $this->jsonError($exception->getMessage(), 409);
        }

        return $this->jsonError('Internal Server Error.', 500);
    }

    /** @return array<string, mixed> */
    private function apiNavigationData(NavigationItem $item): array
    {
        return [
            'id' => $item->id(),
            'location' => $item->location(),
            'label' => $item->label(),
            'link_type' => $item->linkType(),
            'route_key' => $item->routeKey(),
            'page_id' => $item->pageId(),
            'parent_id' => $item->parentId(),
            'sort_order' => $item->sortOrder(),
            'is_active' => $item->isActive(),
            'updated_by' => $item->updatedBy(),
            'updated_at' => $this->dateTime($item->updatedAt()),
        ];
    }

    /**
     * @param array<int, array{id: int, title: string, slug: string, status: string}> $pages
     * @return array<string, mixed>
     */
    private function navigationPageData(NavigationItem $item, array $pages): array
    {
        $page = $item->pageId() !== null ? ($pages[$item->pageId()] ?? null) : null;
        $routePath = $item->routeKey() !== null
            ? (new NavigationRouteCatalog())->path($item->routeKey())
            : null;

        return [
            'id' => $item->id(),
            'location' => $item->location(),
            'label' => $item->label(),
            'linkType' => $item->linkType(),
            'routeKey' => $item->routeKey(),
            'routePath' => $routePath,
            'pageId' => $item->pageId(),
            'page' => $page,
            'parentId' => $item->parentId(),
            'sortOrder' => $item->sortOrder(),
            'active' => $item->isActive(),
        ];
    }

    /** @return array<string, mixed> */
    private function apiSettingDefinitionData(SettingDefinition $definition, ?SiteSetting $setting): array
    {
        return [
            'setting_key' => $definition->key(),
            'setting_value' => $setting?->value(),
            'value_type' => $definition->valueType(),
            'is_public' => $definition->isPublic(),
            'maximum_length' => $definition->maximumLength(),
            'configured' => $setting instanceof SiteSetting,
            'updated_at' => $setting instanceof SiteSetting ? $this->dateTime($setting->updatedAt()) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function apiSettingData(SiteSetting $setting): array
    {
        return [
            'id' => $setting->id(),
            'setting_key' => $setting->key(),
            'setting_value' => $setting->value(),
            'value_type' => $setting->valueType(),
            'is_public' => $setting->isPublic(),
            'updated_by' => $setting->updatedBy(),
            'updated_at' => $this->dateTime($setting->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function apiPageData(CmsPage $page): array
    {
        $profile = $this->services->service()->pageProfile($page->id());
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [
            'id' => $page->id(),
            'title' => $page->title(),
            'slug' => $page->slug(),
            'content' => $page->content(),
            'meta_title' => $page->metaTitle(),
            'meta_description' => $page->metaDescription(),
            'content_type' => $profile?->contentType() ?? 'page',
            'summary' => $profile?->summary(),
            'notice_style' => $profile?->noticeStyle(),
            'status' => $page->publicationState($now),
            'published_at' => $page->publishedAt() instanceof DateTimeImmutable ? $this->dateTime($page->publishedAt()) : null,
            'publish_at' => $profile?->publishAt() instanceof DateTimeImmutable ? $this->dateTime($profile->publishAt()) : null,
            'expires_at' => $profile?->expiresAt() instanceof DateTimeImmutable ? $this->dateTime($profile->expiresAt()) : null,
            'is_expired' => $profile?->isExpiredAt($now) ?? false,
            'is_featured' => $profile?->isFeatured() ?? false,
            'is_pinned' => $profile?->isPinned() ?? false,
            'featured_media_id' => $profile?->featuredMediaId(),
            'social_title' => $profile?->socialTitle(),
            'social_description' => $profile?->socialDescription(),
            'social_media_id' => $profile?->socialMediaId(),
            'seo_noindex' => $profile?->seoNoindex() ?? false,
            'created_by' => $page->createdBy(),
            'updated_by' => $page->updatedBy(),
            'created_at' => $this->dateTime($page->createdAt()),
            'updated_at' => $this->dateTime($page->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function pageData(CmsPage $page, ?PageContentProfile $profile = null): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $state = $page->publicationState($now);

        return [
            'id' => $page->id(),
            'title' => $page->title(),
            'slug' => $page->slug(),
            'content' => $page->content(),
            'metaTitle' => $page->metaTitle(),
            'metaDescription' => $page->metaDescription(),
            'contentType' => $profile?->contentType() ?? 'page',
            'summary' => $profile?->summary(),
            'noticeStyle' => $profile?->noticeStyle(),
            'status' => $state,
            'publishedAt' => $page->publishedAt() instanceof DateTimeImmutable ? $this->dateTime($page->publishedAt()) : null,
            'publishAtInput' => $this->dateTimeLocal($profile?->publishAt() ?? $page->publishedAt()),
            'expiresAt' => $profile?->expiresAt() instanceof DateTimeImmutable ? $this->dateTime($profile->expiresAt()) : null,
            'expired' => $profile?->isExpiredAt($now) ?? false,
            'featured' => $profile?->isFeatured() ?? false,
            'pinned' => $profile?->isPinned() ?? false,
            'featuredMediaId' => $profile?->featuredMediaId(),
            'socialTitle' => $profile?->socialTitle(),
            'socialDescription' => $profile?->socialDescription(),
            'socialMediaId' => $profile?->socialMediaId(),
            'seoNoindex' => $profile?->seoNoindex() ?? false,
            'updatedAt' => $this->dateTime($page->updatedAt()),
            'transitions' => match ($state) {
                CmsPage::DRAFT => [CmsPage::PUBLISHED, CmsPage::SCHEDULED, CmsPage::ARCHIVED],
                CmsPage::SCHEDULED => [CmsPage::PUBLISHED, CmsPage::SCHEDULED, CmsPage::DRAFT, CmsPage::ARCHIVED],
                CmsPage::PUBLISHED => [CmsPage::SCHEDULED, CmsPage::DRAFT, CmsPage::ARCHIVED],
                CmsPage::ARCHIVED => [CmsPage::DRAFT],
                default => [],
            },
        ];
    }

    private function contentMediaError(ContentMediaException $exception): Response
    {
        if ($exception->isValidationFailure()) {
            return Response::json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['media' => [$exception->getMessage()]],
            ], 422, ['Cache-Control' => 'no-store']);
        }
        if ($exception->isNotFound()) {
            return $this->jsonError($exception->getMessage(), 404);
        }
        if ($exception->isConflict()) {
            return $this->jsonError($exception->getMessage(), 409);
        }
        return $this->jsonError('Internal Server Error.', 500);
    }

    /** @return array<string, mixed> */
    private function apiMediaData(ContentMedia $media): array
    {
        return [
            'id' => $media->id(),
            'url' => $media->publicUrl(),
            'storage_path' => $media->storagePath(),
            'original_name' => $media->originalName(),
            'mime_type' => $media->mimeType(),
            'file_size_bytes' => $media->byteSize(),
            'width' => $media->width(),
            'height' => $media->height(),
            'title' => $media->title(),
            'alt_text' => $media->altText(),
            'caption' => $media->caption(),
            'usage_count' => $media->usageCount(),
            'created_at' => $this->dateTime($media->createdAt()),
            'updated_at' => $this->dateTime($media->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function mediaPageData(ContentMedia $media): array
    {
        return [
            'id' => $media->id(),
            'url' => $media->publicUrl(),
            'originalName' => $media->originalName(),
            'mimeType' => $media->mimeType(),
            'byteSize' => $media->byteSize(),
            'width' => $media->width(),
            'height' => $media->height(),
            'title' => $media->title(),
            'altText' => $media->altText(),
            'caption' => $media->caption(),
            'usageCount' => $media->usageCount(),
            'createdAt' => $this->dateTime($media->createdAt()),
        ];
    }

    /** @param array<string, mixed> $values */
    private function pageForm(string $heading, string $action, string $method, array $values): Response
    {
        $service = $this->services->service();
        $media = [];
        if ($this->contentMedia instanceof ContentMediaManagementServiceProvider) {
            $media = array_map($this->mediaPageData(...), $this->contentMedia->service()->listMedia());
        }

        return $this->page('admin.pages.form', [
            'title' => $heading . ' | Tara Guest House',
            'pageTitle' => $heading,
            'activeNavigation' => 'pages',
            'heading' => $heading,
            'action' => $action,
            'apiMethod' => $method,
            'values' => $values,
            'contentFormat' => $service->contentFormat(),
            'contentMaximumLength' => $service->contentMaximumLength(),
            'mediaLibraryAvailable' => $this->contentMedia instanceof ContentMediaManagementServiceProvider,
            'mediaOptions' => $media,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        $html = $this->views->render($view, [
            ...$data,
            'csrfToken' => $this->csrfTokens->token(),
        ], 'layouts.admin');

        return Response::html($html, 200, ['Cache-Control' => 'no-store']);
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($id) ? $id : null;
    }

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');
        return $contentType !== null
            && strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/json';
    }

    private function usesMultipartFormData(Request $request): bool
    {
        $contentType = $request->header('Content-Type');
        return $contentType !== null
            && strtolower(trim(explode(';', $contentType, 2)[0])) === 'multipart/form-data';
    }

    private function auditUserAgent(Request $request): ?string
    {
        $value = $request->header('User-Agent');
        if ($value === null || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $normalized = preg_replace('/[\x00-\x20\x7F]+/u', ' ', trim($value));
        return is_string($normalized) && $normalized !== ''
            ? mb_strcut($normalized, 0, 255, 'UTF-8')
            : null;
    }

    private function dateTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone($this->timezone)->format(DATE_ATOM);
    }

    private function dateTimeLocal(?DateTimeImmutable $value): string
    {
        return $value instanceof DateTimeImmutable
            ? $value->setTimezone($this->timezone)->format('Y-m-d\TH:i')
            : '';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
    }

    private function failure(): Response
    {
        return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
    }

    private function jsonError(string $message, int $statusCode): Response
    {
        return Response::json(['success' => false, 'message' => $message], $statusCode, ['Cache-Control' => 'no-store']);
    }
}
