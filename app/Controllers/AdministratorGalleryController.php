<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\UploadedFile;
use App\Core\ViewRenderer;
use App\Exceptions\GalleryException;
use App\Exceptions\GalleryImageException;
use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use App\Requests\GalleryCategoryRequest;
use App\Requests\GalleryImageUploadRequest;
use App\Requests\GalleryItemMetadataRequest;
use App\Requests\GalleryReorderRequest;
use App\Requests\GalleryStateRequest;
use App\Services\GalleryImageStorageProvider;
use App\Services\GalleryManagementServiceProvider;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Renders protected gallery pages and handles exact Administrator gallery APIs.
 */
final readonly class AdministratorGalleryController
{
    public function __construct(
        private ViewRenderer $views,
        private GalleryManagementServiceProvider $services,
        private GalleryImageStorageProvider $images,
        private AdministratorSession $administratorSession,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            $categories = $this->services->service()->listCategories();
            $categoryNames = [];

            foreach ($categories as $category) {
                $categoryNames[$category->id()] = $category->name();
            }

            return $this->page('admin.gallery.index', [
                'title' => 'Gallery | Tara Guest House',
                'pageTitle' => 'Gallery',
                'activeNavigation' => 'gallery',
                'items' => array_map(
                    fn (GalleryItem $item): array => $this->itemPageData($item, $categoryNames),
                    $this->services->service()->listItems(),
                ),
            ]);
        } catch (GalleryException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function categories(Request $request, array $parameters): Response
    {
        try {
            return $this->page('admin.gallery.categories', [
                'title' => 'Gallery categories | Tara Guest House',
                'pageTitle' => 'Gallery categories',
                'activeNavigation' => 'gallery',
                'categories' => array_map(
                    $this->categoryPageData(...),
                    $this->services->service()->listCategories(),
                ),
            ]);
        } catch (GalleryException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function createForm(Request $request, array $parameters): Response
    {
        try {
            $configuration = $this->images->uploadConfiguration();

            return $this->itemFormPage(
                'Upload gallery item',
                '/api/v1/admin/gallery',
                'POST',
                [
                    'categoryId' => '',
                    'title' => '',
                    'caption' => '',
                    'alternativeText' => '',
                    'sortOrder' => '0',
                    'active' => true,
                    'published' => false,
                    'filePath' => null,
                ],
                true,
                $configuration,
            );
        } catch (GalleryImageException) {
            return Response::text(
                'Gallery image upload is not configured.',
                503,
                ['Cache-Control' => 'no-store'],
            );
        } catch (GalleryException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function editForm(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            $item = $this->services->service()->getItem($id);

            return $this->itemFormPage(
                'Edit gallery item',
                '/api/v1/admin/gallery/' . $item->id(),
                'PUT',
                [
                    'categoryId' => $item->categoryId() === null ? '' : (string) $item->categoryId(),
                    'title' => $item->title(),
                    'caption' => $item->caption() ?? '',
                    'alternativeText' => $item->altText(),
                    'sortOrder' => (string) $item->sortOrder(),
                    'active' => $item->isActive(),
                    'published' => $item->isPublished(),
                    'filePath' => $item->filePath(),
                ],
                false,
                null,
            );
        } catch (GalleryException $exception) {
            return $exception->isNotFound() ? $this->notFound() : $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCategories(Request $request, array $parameters): Response
    {
        try {
            return Response::json([
                'success' => true,
                'data' => array_map(
                    $this->apiCategoryData(...),
                    $this->services->service()->listCategories(),
                ),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCreateCategory(Request $request, array $parameters): Response
    {
        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!$this->hasExactKeys($payload, ['is_active', 'name', 'slug'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $category = $this->services->service()->createCategory(
                GalleryCategoryRequest::fromInput(
                    $payload['name'],
                    $payload['slug'],
                    $payload['is_active'],
                ),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Gallery category created.',
                'data' => $this->apiCategoryData($category),
            ], 201, ['Cache-Control' => 'no-store']);
        } catch (GalleryException $exception) {
            return $this->galleryError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUpdateCategory(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The requested gallery category was not found.', 404);
        }

        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!$this->hasExactKeys($payload, ['is_active', 'name', 'slug'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $category = $this->services->service()->updateCategory(
                $id,
                GalleryCategoryRequest::fromInput(
                    $payload['name'],
                    $payload['slug'],
                    $payload['is_active'],
                ),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Gallery category updated.',
                'data' => $this->apiCategoryData($category),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException $exception) {
            return $this->galleryError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiIndex(Request $request, array $parameters): Response
    {
        try {
            return Response::json([
                'success' => true,
                'data' => array_map(
                    $this->apiItemData(...),
                    $this->services->service()->listItems(),
                ),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCreate(Request $request, array $parameters): Response
    {
        if (!$this->usesMultipartFormData($request)) {
            return $this->jsonError('Content-Type must be multipart/form-data.', 415);
        }

        $payload = $request->input();
        $files = $request->file();

        if (
            !is_array($payload)
            || !$this->hasExactKeys($payload, [
                'caption',
                'gallery_category_id',
                'is_active',
                'is_published',
                'sort_order',
                'title',
                'alt_text',
            ])
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
            $item = $this->services->service()->createItem(
                GalleryItemMetadataRequest::fromInput(
                    $payload['gallery_category_id'],
                    $payload['title'],
                    $payload['caption'],
                    $payload['alt_text'],
                    $payload['sort_order'],
                ),
                GalleryImageUploadRequest::fromInput(
                    $files['image'],
                    $this->images->uploadConfiguration(),
                ),
                $this->formBoolean($payload['is_active'], 'Gallery active state'),
                $this->formBoolean($payload['is_published'], 'Gallery publication state'),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Gallery item created.',
                'data' => $this->apiItemData($item),
            ], 201, ['Cache-Control' => 'no-store']);
        } catch (GalleryImageException $exception) {
            return $exception->isValidationFailure()
                ? $this->validationError($exception->getMessage())
                : $this->jsonError('Internal Server Error.', 500);
        } catch (GalleryException $exception) {
            return $this->galleryError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUpdate(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The requested gallery item was not found.', 404);
        }

        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!$this->hasExactKeys($payload, [
            'alt_text',
            'caption',
            'gallery_category_id',
            'sort_order',
            'title',
        ])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $item = $this->services->service()->updateItem(
                $id,
                GalleryItemMetadataRequest::fromInput(
                    $payload['gallery_category_id'],
                    $payload['title'],
                    $payload['caption'],
                    $payload['alt_text'],
                    $payload['sort_order'],
                ),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Gallery item updated.',
                'data' => $this->apiItemData($item),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException $exception) {
            return $this->galleryError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiSetActive(Request $request, array $parameters): Response
    {
        return $this->stateMutation($request, $parameters, 'is_active', true);
    }

    /** @param array<string, string> $parameters */
    public function apiSetPublication(Request $request, array $parameters): Response
    {
        return $this->stateMutation($request, $parameters, 'is_published', false);
    }

    /** @param array<string, string> $parameters */
    public function apiReorder(Request $request, array $parameters): Response
    {
        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!$this->hasExactKeys($payload, ['items'])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $items = $this->services->service()->reorder(
                GalleryReorderRequest::fromInput($payload['items']),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Gallery ordering updated.',
                'data' => array_map($this->apiItemData(...), $items),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException $exception) {
            return $this->galleryError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiDelete(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The requested gallery item was not found.', 404);
        }

        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if ($payload !== []) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $item = $this->services->service()->removeItem(
                $id,
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Gallery item deactivated and unpublished.',
                'data' => $this->apiItemData($item),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException $exception) {
            return $this->galleryError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    private function stateMutation(
        Request $request,
        array $parameters,
        string $field,
        bool $activeState,
    ): Response {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The requested gallery item was not found.', 404);
        }

        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!$this->hasExactKeys($payload, [$field])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $state = GalleryStateRequest::fromInput(
                $payload[$field],
                $activeState ? 'Gallery active state' : 'Gallery publication state',
            );
            $item = $activeState
                ? $this->services->service()->setItemActive(
                    $id,
                    $state->enabled(),
                    $identity->userId(),
                    $request->remoteAddress(),
                    $this->auditUserAgent($request),
                )
                : $this->services->service()->setItemPublished(
                    $id,
                    $state->enabled(),
                    $identity->userId(),
                    $request->remoteAddress(),
                    $this->auditUserAgent($request),
                );

            return Response::json([
                'success' => true,
                'message' => $activeState
                    ? 'Gallery active state updated.'
                    : 'Gallery publication state updated.',
                'data' => $this->apiItemData($item),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (GalleryException $exception) {
            return $this->galleryError($exception);
        }
    }

    /** @return array<string, mixed>|Response */
    private function jsonObject(Request $request): array|Response
    {
        if (!$this->usesJsonContentType($request)) {
            return $this->jsonError('Content-Type must be application/json.', 415);
        }

        try {
            $decoded = json_decode($request->body(), false, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->jsonError('Request body must contain valid JSON.', 400);
        }

        if (!$decoded instanceof \stdClass) {
            return $this->jsonError('Request body must contain a JSON object.', 400);
        }

        return $this->normalizeJsonObject($decoded);
    }

    /** @return array<string, mixed> */
    private function normalizeJsonObject(\stdClass $object): array
    {
        $normalized = [];

        foreach (get_object_vars($object) as $key => $value) {
            $normalized[$key] = $this->normalizeJsonValue($value);
        }

        return $normalized;
    }

    private function normalizeJsonValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return $this->normalizeJsonObject($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeJsonValue($item);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $expected
     */
    private function hasExactKeys(array $payload, array $expected): bool
    {
        $keys = array_keys($payload);
        sort($keys);
        sort($expected);

        return $keys === $expected;
    }

    private function formBoolean(mixed $value, string $label): bool
    {
        if ($value === '1') {
            return true;
        }

        if ($value === '0') {
            return false;
        }

        throw GalleryException::invalidInput($label . ' must be true or false.');
    }

    private function galleryError(GalleryException $exception): Response
    {
        if ($exception->isValidationFailure()) {
            return $this->validationError($exception->getMessage());
        }

        if ($exception->isNotFound()) {
            return $this->jsonError($exception->getMessage(), 404);
        }

        if ($exception->isConflict()) {
            return $this->jsonError($exception->getMessage(), 409);
        }

        return $this->jsonError('Internal Server Error.', 500);
    }

    private function validationError(string $message): Response
    {
        return Response::json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => ['gallery' => [$message]],
        ], 422, ['Cache-Control' => 'no-store']);
    }

    /** @return array<string, mixed> */
    private function apiCategoryData(GalleryCategory $category): array
    {
        return [
            'id' => $category->id(),
            'name' => $category->name(),
            'slug' => $category->slug(),
            'is_active' => $category->isActive(),
            'created_at' => $this->dateTime($category->createdAt()),
            'updated_at' => $this->dateTime($category->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function apiItemData(GalleryItem $item): array
    {
        return [
            'id' => $item->id(),
            'gallery_category_id' => $item->categoryId(),
            'title' => $item->title(),
            'caption' => $item->caption(),
            'file_path' => $item->filePath(),
            'alt_text' => $item->altText(),
            'sort_order' => $item->sortOrder(),
            'is_active' => $item->isActive(),
            'is_published' => $item->isPublished(),
            'created_at' => $this->dateTime($item->createdAt()),
            'updated_at' => $this->dateTime($item->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function categoryPageData(GalleryCategory $category): array
    {
        return [
            'id' => $category->id(),
            'name' => $category->name(),
            'slug' => $category->slug(),
            'active' => $category->isActive(),
            'updatedAt' => $this->dateTime($category->updatedAt()),
        ];
    }

    /**
     * @param array<int, string> $categoryNames
     * @return array<string, mixed>
     */
    private function itemPageData(GalleryItem $item, array $categoryNames): array
    {
        return [
            'id' => $item->id(),
            'title' => $item->title(),
            'category' => $item->categoryId() === null
                ? 'Uncategorized'
                : ($categoryNames[$item->categoryId()] ?? 'Unavailable category'),
            'filePath' => $item->filePath(),
            'sortOrder' => $item->sortOrder(),
            'active' => $item->isActive(),
            'published' => $item->isPublished(),
            'publiclyVisible' => $item->isPubliclyVisible(),
            'updatedAt' => $this->dateTime($item->updatedAt()),
        ];
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

    /**
     * @param array<string, mixed> $values
     * @param null|array<string, mixed> $uploadConfiguration
     */
    private function itemFormPage(
        string $heading,
        string $action,
        string $method,
        array $values,
        bool $creating,
        ?array $uploadConfiguration,
    ): Response {
        $categories = array_map(
            $this->categoryPageData(...),
            $this->services->service()->listCategories(),
        );

        return $this->page('admin.gallery.form', [
            'title' => $heading . ' | Tara Guest House',
            'pageTitle' => $heading,
            'activeNavigation' => 'gallery',
            'heading' => $heading,
            'action' => $action,
            'apiMethod' => $method,
            'values' => $values,
            'creating' => $creating,
            'categories' => $categories,
            'uploadConfiguration' => $uploadConfiguration,
        ]);
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

        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        return mb_strcut($normalized, 0, 255, 'UTF-8');
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
        return Response::json([
            'success' => false,
            'message' => $message,
        ], $statusCode, ['Cache-Control' => 'no-store']);
    }
}
