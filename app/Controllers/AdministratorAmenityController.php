<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Requests\AmenityUpsertRequest;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogServiceProvider;

/**
 * Handles authenticated Administrator amenity forms and state-changing actions.
 */
final readonly class AdministratorAmenityController
{
    public function __construct(
        private ViewRenderer $views,
        private RoomCatalogServiceProvider $services,
        private CsrfTokenManager $csrfTokens,
        private RoomCatalogFlashMessages $flashMessages,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function createForm(Request $request, array $parameters): Response
    {
        return $this->formResponse(
            'Create amenity',
            '/admin/amenities',
            'Create amenity',
            $this->emptyForm(),
        );
    }

    /** @param array<string, string> $parameters */
    public function create(Request $request, array $parameters): Response
    {
        $values = $this->inputValues($request);

        try {
            $validated = $this->validatedRequest($request);
        } catch (RoomCatalogException $exception) {
            if (!$exception->isValidationFailure()) {
                throw $exception;
            }

            return $this->formResponse(
                'Create amenity',
                '/admin/amenities',
                'Create amenity',
                $values,
                $exception->getMessage(),
                422,
            );
        }

        try {
            $this->services->amenities()->create($validated);
        } catch (RoomCatalogException $exception) {
            if (!$exception->isConflict()) {
                throw $exception;
            }

            return $this->formResponse(
                'Create amenity',
                '/admin/amenities',
                'Create amenity',
                $this->validatedValues($validated),
                $exception->getMessage(),
                409,
            );
        }

        $this->flashMessages->amenitySuccess('Amenity created successfully.');

        return $this->redirectToIndex();
    }

    /** @param array<string, string> $parameters */
    public function editForm(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            $amenity = $this->services->amenities()->get($id);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        return $this->formResponse(
            'Edit amenity',
            '/admin/amenities/' . $amenity->id(),
            'Save amenity',
            $this->storedValues($amenity),
        );
    }

    /** @param array<string, string> $parameters */
    public function update(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null || !$this->amenityExists($id)) {
            return $this->notFound();
        }

        $values = $this->inputValues($request);

        try {
            $validated = $this->validatedRequest($request);
        } catch (RoomCatalogException $exception) {
            if (!$exception->isValidationFailure()) {
                throw $exception;
            }

            return $this->formResponse(
                'Edit amenity',
                '/admin/amenities/' . $id,
                'Save amenity',
                $values,
                $exception->getMessage(),
                422,
            );
        }

        try {
            $this->services->amenities()->update($id, $validated);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            if (!$exception->isConflict()) {
                throw $exception;
            }

            return $this->formResponse(
                'Edit amenity',
                '/admin/amenities/' . $id,
                'Save amenity',
                $this->validatedValues($validated),
                $exception->getMessage(),
                409,
            );
        }

        $this->flashMessages->amenitySuccess('Amenity updated successfully.');

        return $this->redirectToIndex();
    }

    /** @param array<string, string> $parameters */
    public function activate(Request $request, array $parameters): Response
    {
        return $this->changeActiveState($parameters, true);
    }

    /** @param array<string, string> $parameters */
    public function deactivate(Request $request, array $parameters): Response
    {
        return $this->changeActiveState($parameters, false);
    }

    /** @param array<string, string> $parameters */
    private function changeActiveState(array $parameters, bool $active): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            if ($active) {
                $this->services->amenities()->activate($id);
            } else {
                $this->services->amenities()->deactivate($id);
            }
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        $this->flashMessages->amenitySuccess($active
            ? 'Amenity activated successfully.'
            : 'Amenity deactivated successfully.');

        return $this->redirectToIndex();
    }

    private function amenityExists(int $id): bool
    {
        try {
            $this->services->amenities()->get($id);

            return true;
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return false;
            }

            throw $exception;
        }
    }

    private function validatedRequest(Request $request): AmenityUpsertRequest
    {
        return AmenityUpsertRequest::fromInput(
            $request->input('name'),
            $request->input('icon'),
            $request->input('active'),
        );
    }

    /** @return array<string, string> */
    private function inputValues(Request $request): array
    {
        return [
            'name' => $this->scalarInput($request->input('name')),
            'icon' => $this->scalarInput($request->input('icon')),
            'active' => $this->scalarInput($request->input('active')),
        ];
    }

    private function scalarInput(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    /** @return array<string, string> */
    private function emptyForm(): array
    {
        return [
            'name' => '',
            'icon' => '',
            'active' => '1',
        ];
    }

    /** @return array<string, string> */
    private function storedValues(Amenity $amenity): array
    {
        return [
            'name' => $amenity->name(),
            'icon' => $amenity->icon() ?? '',
            'active' => $amenity->isActive() ? '1' : '0',
        ];
    }

    /** @return array<string, string> */
    private function validatedValues(AmenityUpsertRequest $request): array
    {
        return [
            'name' => $request->name(),
            'icon' => $request->icon() ?? '',
            'active' => $request->isActive() ? '1' : '0',
        ];
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return is_int($id) ? $id : null;
    }

    /** @param array<string, string> $values */
    private function formResponse(
        string $heading,
        string $action,
        string $submitLabel,
        array $values,
        ?string $error = null,
        int $statusCode = 200,
    ): Response {
        $html = $this->views->render(
            'admin.amenities.form',
            [
                'title' => $heading . ' | Tara Guest House',
                'pageTitle' => $heading,
                'activeNavigation' => 'amenities',
                'csrfToken' => $this->csrfTokens->token(),
                'heading' => $heading,
                'action' => $action,
                'submitLabel' => $submitLabel,
                'values' => $values,
                'error' => $error,
            ],
            'layouts.admin',
        );

        return Response::html($html, $statusCode, ['Cache-Control' => 'no-store']);
    }

    private function redirectToIndex(): Response
    {
        return Response::redirect('/admin/amenities', 303, ['Cache-Control' => 'no-store']);
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
    }
}
