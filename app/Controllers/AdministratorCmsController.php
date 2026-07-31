<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\CmsException;
use App\Models\CmsPage;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Requests\CmsPageContentRequest;
use App\Requests\CmsPageStatusRequest;
use App\Requests\CmsSettingsUpdateRequest;
use App\Services\CmsManagementServiceProvider;
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
    ) {
    }

    /** @param array<string, string> $parameters */
    public function settings(Request $request, array $parameters): Response
    {
        try {
            return $this->page('admin.settings.index', [
                'title' => 'Settings | Tara Guest House',
                'pageTitle' => 'Settings',
                'activeNavigation' => 'settings',
                'settings' => $this->settingRows(),
            ]);
        } catch (CmsException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function pages(Request $request, array $parameters): Response
    {
        try {
            return $this->page('admin.pages.index', [
                'title' => 'Pages | Tara Guest House',
                'pageTitle' => 'Pages',
                'activeNavigation' => 'pages',
                'pages' => array_map($this->pageData(...), $this->services->service()->listPages()),
            ]);
        } catch (CmsException) {
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
                    'title' => '',
                    'slug' => '',
                    'content' => '',
                    'metaTitle' => '',
                    'metaDescription' => '',
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
            $page = $this->services->service()->getPage($id);

            return $this->page('admin.pages.show', [
                'title' => $page->title() . ' | Tara Guest House',
                'pageTitle' => 'Page detail',
                'activeNavigation' => 'pages',
                'page' => $this->pageData($page),
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
            $page = $this->services->service()->getPage($id);

            return $this->pageForm(
                'Edit page',
                '/api/v1/admin/pages/' . $page->id(),
                'PUT',
                [
                    'title' => $page->title(),
                    'slug' => $page->slug(),
                    'content' => $page->content(),
                    'metaTitle' => $page->metaTitle() ?? '',
                    'metaDescription' => $page->metaDescription() ?? '',
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

        if (!$this->hasExactKeys($payload, ['status'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();
        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $status = CmsPageStatusRequest::fromInput($payload['status']);
            $page = $this->services->service()->transitionPageStatus(
                $id,
                $status->status(),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
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

    /** @return array<string, mixed>|Response */
    private function pagePayload(Request $request): array|Response
    {
        $payload = $this->jsonObject($request);
        if ($payload instanceof Response) {
            return $payload;
        }

        return $this->hasExactKeys($payload, ['content', 'meta_description', 'meta_title', 'slug', 'title'])
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
        return [
            'id' => $page->id(),
            'title' => $page->title(),
            'slug' => $page->slug(),
            'content' => $page->content(),
            'meta_title' => $page->metaTitle(),
            'meta_description' => $page->metaDescription(),
            'status' => $page->status(),
            'published_at' => $page->publishedAt() instanceof DateTimeImmutable ? $this->dateTime($page->publishedAt()) : null,
            'created_by' => $page->createdBy(),
            'updated_by' => $page->updatedBy(),
            'created_at' => $this->dateTime($page->createdAt()),
            'updated_at' => $this->dateTime($page->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function pageData(CmsPage $page): array
    {
        return [
            'id' => $page->id(),
            'title' => $page->title(),
            'slug' => $page->slug(),
            'content' => $page->content(),
            'metaTitle' => $page->metaTitle(),
            'metaDescription' => $page->metaDescription(),
            'status' => $page->status(),
            'publishedAt' => $page->publishedAt() instanceof DateTimeImmutable ? $this->dateTime($page->publishedAt()) : null,
            'updatedAt' => $this->dateTime($page->updatedAt()),
            'transitions' => match ($page->status()) {
                CmsPage::DRAFT => [CmsPage::PUBLISHED, CmsPage::ARCHIVED],
                CmsPage::PUBLISHED => [CmsPage::DRAFT, CmsPage::ARCHIVED],
                CmsPage::ARCHIVED => [CmsPage::DRAFT],
                default => [],
            },
        ];
    }

    /** @param array<string, string> $values */
    private function pageForm(string $heading, string $action, string $method, array $values): Response
    {
        $service = $this->services->service();

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
