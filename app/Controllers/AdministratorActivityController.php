<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\UploadedFile;
use App\Core\ViewRenderer;
use App\Exceptions\ActivityException;
use App\Exceptions\GalleryImageException;
use App\Models\Activity;
use App\Requests\ActivityContentRequest;
use App\Requests\ActivityStateRequest;
use App\Requests\GalleryImageUploadRequest;
use App\Services\ActivityManagementServiceProvider;
use App\Services\GalleryImageStorageProvider;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Renders protected activity pages and handles the exact Administrator JSON API.
 */
final readonly class AdministratorActivityController
{
    public function __construct(
        private ViewRenderer $views,
        private ActivityManagementServiceProvider $services,
        private AdministratorSession $administratorSession,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
        private ?GalleryImageStorageProvider $images = null,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            $activities = array_map(
                $this->activityData(...),
                $this->services->service()->listAll(),
            );

            return $this->page(
                'admin.activities.index',
                [
                    'title' => 'Activities | Tara Guest House',
                    'pageTitle' => 'Activities',
                    'activeNavigation' => 'activities',
                    'activities' => $activities,
                ],
            );
        } catch (ActivityException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function createForm(Request $request, array $parameters): Response
    {
        try {
            return $this->formPage(
            'Create activity',
            '/api/v1/admin/activities',
            'POST',
            [
                'title' => '',
                'slug' => '',
                'description' => '',
                'summary' => '',
                'price' => '',
                'priceUnit' => '',
                'durationLabel' => '',
                'location' => '',
                'sortOrder' => '0',
                'homepageFeatured' => false,
                'homepageSortOrder' => '0',
                'featuredImageUrl' => null,
                'active' => true,
                'published' => false,
            ],
            true,
            $this->images?->uploadConfiguration(),
            );
        } catch (GalleryImageException) {
            return Response::text('Activity image upload is not configured.', 503, ['Cache-Control' => 'no-store']);
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
            $activity = $this->services->service()->get($id);

            return $this->formPage(
                'Edit activity',
                '/api/v1/admin/activities/' . $activity->id(),
                'PUT',
                [
                    'title' => $activity->title(),
                    'slug' => $activity->slug(),
                    'description' => $activity->description(),
                    'summary' => $activity->summary(),
                    'price' => $activity->price() ?? '',
                    'priceUnit' => $activity->priceUnit() ?? '',
                    'durationLabel' => $activity->durationLabel() ?? '',
                    'location' => $activity->location() ?? '',
                    'sortOrder' => (string) $activity->sortOrder(),
                    'homepageFeatured' => $activity->isHomepageFeatured(),
                    'homepageSortOrder' => (string) $activity->homepageSortOrder(),
                    'featuredImageUrl' => $activity->featuredImagePath() === null
                        ? null
                        : '/activities?image=' . (string) $activity->id(),
                    'active' => $activity->isActive(),
                    'published' => $activity->isPublished(),
                ],
                false,
                $this->images?->uploadConfiguration(),
            );
        } catch (ActivityException $exception) {
            return $exception->isNotFound() ? $this->notFound() : $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function apiIndex(Request $request, array $parameters): Response
    {
        try {
            return Response::json(
                [
                    'success' => true,
                    'data' => array_map(
                        $this->apiActivityData(...),
                        $this->services->service()->listAll(),
                    ),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ActivityException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiShow(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The activity was not found.', 404);
        }

        try {
            return Response::json(
                [
                    'success' => true,
                    'data' => $this->apiActivityData(
                        $this->services->service()->get($id),
                    ),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ActivityException $exception) {
            return $exception->isNotFound()
                ? $this->jsonError($exception->getMessage(), 404)
                : $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCreate(Request $request, array $parameters): Response
    {
        $multipart = $this->usesMultipartFormData($request);
        $payload = $multipart ? $request->input() : $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!is_array($payload)) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        if (!$this->hasExactKeys($payload, [
            'description',
            'duration',
            'homepage_sort_order',
            'is_homepage_featured',
            'is_active',
            'is_published',
            'location',
            'price',
            'price_unit',
            'slug',
            'sort_order',
            'summary',
            'title',
        ])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $content = ActivityContentRequest::fromInput(
                $payload['title'],
                $payload['slug'],
                $payload['summary'],
                $payload['description'],
                $payload['price'],
                $payload['price_unit'],
                $payload['duration'],
                $payload['location'],
                $payload['sort_order'],
                $payload['is_homepage_featured'],
                $payload['homepage_sort_order'],
            );
            $active = ActivityStateRequest::fromInput(
                $multipart ? $this->formBoolean($payload['is_active'], 'Activity active state') : $payload['is_active'],
                'Activity active state',
            );
            $published = ActivityStateRequest::fromInput(
                $multipart ? $this->formBoolean($payload['is_published'], 'Activity publication state') : $payload['is_published'],
                'Activity publication state',
            );
            $activity = $this->services->service()->create(
                $content,
                $active->enabled(),
                $published->enabled(),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
                $this->activityUpload($request, $multipart),
            );

            return Response::json(
                [
                    'success' => true,
                    'message' => 'Activity created.',
                    'data' => $this->apiActivityData($activity),
                ],
                201,
                ['Cache-Control' => 'no-store'],
            );
        } catch (GalleryImageException $exception) {
            return $exception->isValidationFailure()
                ? $this->activityValidationError($exception->getMessage())
                : $this->jsonError('Internal Server Error.', 500);
        } catch (ActivityException $exception) {
            return $this->activityError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiUpdate(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The activity was not found.', 404);
        }

        $multipart = $this->usesMultipartFormData($request);
        $payload = $multipart ? $request->input() : $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!is_array($payload)) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        if (!$this->hasExactKeys($payload, [
            'description',
            'duration',
            'homepage_sort_order',
            'is_homepage_featured',
            'location',
            'price',
            'price_unit',
            'slug',
            'sort_order',
            'summary',
            'title',
        ])) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $activity = $this->services->service()->update(
                $id,
                ActivityContentRequest::fromInput(
                    $payload['title'],
                    $payload['slug'],
                    $payload['summary'],
                    $payload['description'],
                    $payload['price'],
                    $payload['price_unit'],
                    $payload['duration'],
                    $payload['location'],
                    $payload['sort_order'],
                    $payload['is_homepage_featured'],
                    $payload['homepage_sort_order'],
                ),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
                $this->activityUpload($request, $multipart),
            );

            return Response::json(
                [
                    'success' => true,
                    'message' => 'Activity updated.',
                    'data' => $this->apiActivityData($activity),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (GalleryImageException $exception) {
            return $exception->isValidationFailure()
                ? $this->activityValidationError($exception->getMessage())
                : $this->jsonError('Internal Server Error.', 500);
        } catch (ActivityException $exception) {
            return $this->activityError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiSetActive(Request $request, array $parameters): Response
    {
        return $this->stateMutation(
            $request,
            $parameters,
            'is_active',
            'Activity active state',
            true,
        );
    }

    /** @param array<string, string> $parameters */
    public function apiSetPublication(Request $request, array $parameters): Response
    {
        return $this->stateMutation(
            $request,
            $parameters,
            'is_published',
            'Activity publication state',
            false,
        );
    }

    /** @param array<string, string> $parameters */
    public function apiDelete(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The activity was not found.', 404);
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
            $activity = $this->services->service()->remove(
                $id,
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json(
                [
                    'success' => true,
                    'message' => 'Activity deactivated and unpublished.',
                    'data' => $this->apiActivityData($activity),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ActivityException $exception) {
            return $this->activityError($exception);
        }
    }

    /**
     * @param array<string, string> $parameters
     */
    private function stateMutation(
        Request $request,
        array $parameters,
        string $field,
        string $label,
        bool $activeState,
    ): Response {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The activity was not found.', 404);
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
            $state = ActivityStateRequest::fromInput($payload[$field], $label);
            $activity = $activeState
                ? $this->services->service()->setActive(
                    $id,
                    $state->enabled(),
                    $identity->userId(),
                    $request->remoteAddress(),
                    $this->auditUserAgent($request),
                )
                : $this->services->service()->setPublished(
                    $id,
                    $state->enabled(),
                    $identity->userId(),
                    $request->remoteAddress(),
                    $this->auditUserAgent($request),
                );

            return Response::json(
                [
                    'success' => true,
                    'message' => $activeState
                        ? 'Activity active state updated.'
                        : 'Activity publication state updated.',
                    'data' => $this->apiActivityData($activity),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ActivityException $exception) {
            return $this->activityError($exception);
        }
    }

    /** @return array<string, mixed>|Response */
    private function jsonObject(Request $request): array|Response
    {
        if (!$this->usesJsonContentType($request)) {
            return $this->jsonError('Content-Type must be application/json.', 415);
        }

        try {
            $decoded = json_decode(
                $request->body(),
                false,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->jsonError('Request body must contain valid JSON.', 400);
        }

        if (!$decoded instanceof \stdClass) {
            return $this->jsonError('Request body must contain a JSON object.', 400);
        }

        return get_object_vars($decoded);
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

    private function activityError(ActivityException $exception): Response
    {
        if ($exception->isValidationFailure()) {
            return Response::json(
                [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'activity' => [$exception->getMessage()],
                    ],
                ],
                422,
                ['Cache-Control' => 'no-store'],
            );
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
    private function apiActivityData(Activity $activity): array
    {
        return [
            'id' => $activity->id(),
            'title' => $activity->title(),
            'slug' => $activity->slug(),
            'description' => $activity->description(),
            'summary' => $activity->summary(),
            'image_url' => $activity->featuredImagePath() === null
                ? null
                : '/activities?image=' . (string) $activity->id(),
            'currency' => 'NPR',
            'price' => $activity->price(),
            'price_unit' => $activity->priceUnit(),
            'duration' => $activity->durationLabel(),
            'location' => $activity->location(),
            'is_active' => $activity->isActive(),
            'is_published' => $activity->isPublished(),
            'sort_order' => $activity->sortOrder(),
            'is_homepage_featured' => $activity->isHomepageFeatured(),
            'homepage_sort_order' => $activity->homepageSortOrder(),
            'created_at' => $this->dateTime($activity->createdAt()),
            'updated_at' => $this->dateTime($activity->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function activityData(Activity $activity): array
    {
        return [
            'id' => $activity->id(),
            'title' => $activity->title(),
            'slug' => $activity->slug(),
            'description' => $activity->description(),
            'summary' => $activity->summary(),
            'price' => $activity->price() === null ? 'Not published' : 'NPR ' . $activity->price(),
            'active' => $activity->isActive(),
            'published' => $activity->isPublished(),
            'publiclyVisible' => $activity->isPubliclyVisible(),
            'sortOrder' => $activity->sortOrder(),
            'homepageFeatured' => $activity->isHomepageFeatured(),
            'homepageSortOrder' => $activity->homepageSortOrder(),
            'updatedAt' => $this->dateTime($activity->updatedAt()),
        ];
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        $html = $this->views->render(
            $view,
            [
                ...$data,
                'csrfToken' => $this->csrfTokens->token(),
            ],
            'layouts.admin',
        );

        return Response::html($html, 200, ['Cache-Control' => 'no-store']);
    }

    /** @param array<string, mixed> $values */
    private function formPage(
        string $heading,
        string $action,
        string $method,
        array $values,
        bool $creating,
        ?array $uploadConfiguration,
    ): Response {
        return $this->page(
            'admin.activities.form',
            [
                'title' => $heading . ' | Tara Guest House',
                'pageTitle' => $heading,
                'activeNavigation' => 'activities',
                'heading' => $heading,
                'action' => $action,
                'apiMethod' => $method,
                'values' => $values,
                'creating' => $creating,
                'uploadConfiguration' => $uploadConfiguration,
            ],
        );
    }

    private function usesMultipartFormData(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        return $contentType !== null
            && strtolower(trim(explode(';', $contentType, 2)[0])) === 'multipart/form-data';
    }

    private function formBoolean(mixed $value, string $label): bool
    {
        return match ($value) {
            '1' => true,
            '0' => false,
            default => throw ActivityException::invalidInput($label . ' must be true or false.'),
        };
    }

    private function activityUpload(Request $request, bool $multipart): ?GalleryImageUploadRequest
    {
        if (!$multipart) {
            return null;
        }

        $files = $request->file();

        if (!is_array($files) || ($files !== [] && array_keys($files) !== ['image'])) {
            throw ActivityException::invalidInput('Activity image upload is invalid.');
        }

        if ($files === []) {
            return null;
        }

        if (!$files['image'] instanceof UploadedFile || !$this->images instanceof GalleryImageStorageProvider) {
            throw ActivityException::invalidInput('Activity image upload is invalid.');
        }

        return GalleryImageUploadRequest::fromInput(
            $files['image'],
            $this->images->uploadConfiguration(),
        );
    }

    private function activityValidationError(string $message): Response
    {
        return Response::json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => ['activity' => [$message]],
        ], 422, ['Cache-Control' => 'no-store']);
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return is_int($id) ? $id : null;
    }

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        if ($contentType === null) {
            return false;
        }

        return strtolower(trim(explode(';', $contentType, 2)[0]))
            === 'application/json';
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
        return Response::text(
            'Internal Server Error',
            500,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function jsonError(string $message, int $statusCode): Response
    {
        return Response::json(
            [
                'success' => false,
                'message' => $message,
            ],
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }
}
