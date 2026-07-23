<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\RoomCatalogException;
use App\Models\RoomType;
use App\Requests\RoomTypeUpsertRequest;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogServiceProvider;

/**
 * Handles authenticated Administrator room-type forms and state-changing actions.
 */
final readonly class AdministratorRoomTypeController
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
            'Create room type',
            '/admin/room-types',
            'Create room type',
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
                'Create room type',
                '/admin/room-types',
                'Create room type',
                $values,
                $exception->getMessage(),
                422,
            );
        }

        try {
            $this->services->roomTypes()->create($validated);
        } catch (RoomCatalogException $exception) {
            if (!$exception->isConflict()) {
                throw $exception;
            }

            return $this->formResponse(
                'Create room type',
                '/admin/room-types',
                'Create room type',
                $this->validatedValues($validated),
                $exception->getMessage(),
                409,
            );
        }

        $this->flashMessages->roomTypeSuccess('Room type created successfully.');

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
            $roomType = $this->services->roomTypes()->get($id);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        return $this->formResponse(
            'Edit room type',
            '/admin/room-types/' . $roomType->id(),
            'Save room type',
            $this->storedValues($roomType),
        );
    }

    /** @param array<string, string> $parameters */
    public function update(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null || !$this->roomTypeExists($id)) {
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
                'Edit room type',
                '/admin/room-types/' . $id,
                'Save room type',
                $values,
                $exception->getMessage(),
                422,
            );
        }

        try {
            $this->services->roomTypes()->update($id, $validated);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            if (!$exception->isConflict()) {
                throw $exception;
            }

            return $this->formResponse(
                'Edit room type',
                '/admin/room-types/' . $id,
                'Save room type',
                $this->validatedValues($validated),
                $exception->getMessage(),
                409,
            );
        }

        $this->flashMessages->roomTypeSuccess('Room type updated successfully.');

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
                $this->services->roomTypes()->activate($id);
            } else {
                $this->services->roomTypes()->deactivate($id);
            }
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        $this->flashMessages->roomTypeSuccess($active
            ? 'Room type activated successfully.'
            : 'Room type deactivated successfully.');

        return $this->redirectToIndex();
    }

    private function roomTypeExists(int $id): bool
    {
        try {
            $this->services->roomTypes()->get($id);

            return true;
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return false;
            }

            throw $exception;
        }
    }

    private function validatedRequest(Request $request): RoomTypeUpsertRequest
    {
        return RoomTypeUpsertRequest::fromInput(
            $request->input('name'),
            $request->input('slug'),
            $request->input('description'),
            $request->input('base_price'),
            $request->input('capacity_adults'),
            $request->input('capacity_children'),
            $request->input('active'),
        );
    }

    /** @return array<string, string> */
    private function inputValues(Request $request): array
    {
        return [
            'name' => $this->scalarInput($request->input('name')),
            'slug' => $this->scalarInput($request->input('slug')),
            'description' => $this->scalarInput($request->input('description')),
            'basePrice' => $this->scalarInput($request->input('base_price')),
            'capacityAdults' => $this->scalarInput($request->input('capacity_adults')),
            'capacityChildren' => $this->scalarInput($request->input('capacity_children')),
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
            'slug' => '',
            'description' => '',
            'basePrice' => '',
            'capacityAdults' => '1',
            'capacityChildren' => '0',
            'active' => '1',
        ];
    }

    /** @return array<string, string> */
    private function storedValues(RoomType $roomType): array
    {
        return [
            'name' => $roomType->name(),
            'slug' => $roomType->slug(),
            'description' => $roomType->description() ?? '',
            'basePrice' => $roomType->basePrice(),
            'capacityAdults' => (string) $roomType->capacityAdults(),
            'capacityChildren' => (string) $roomType->capacityChildren(),
            'active' => $roomType->isActive() ? '1' : '0',
        ];
    }

    /** @return array<string, string> */
    private function validatedValues(RoomTypeUpsertRequest $request): array
    {
        return [
            'name' => $request->name(),
            'slug' => $request->slug(),
            'description' => $request->description() ?? '',
            'basePrice' => $request->basePrice(),
            'capacityAdults' => (string) $request->capacityAdults(),
            'capacityChildren' => (string) $request->capacityChildren(),
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
            'admin.room-types.form',
            [
                'title' => $heading . ' | Tara Guest House',
                'pageTitle' => $heading,
                'activeNavigation' => 'room-types',
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
        return Response::redirect('/admin/room-types', 303, ['Cache-Control' => 'no-store']);
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
    }
}
