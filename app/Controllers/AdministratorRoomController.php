<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\RoomCatalogException;
use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Requests\RoomUpsertRequest;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomCatalogServiceProvider;

/**
 * Handles authenticated Administrator physical-room forms and soft deletion.
 */
final readonly class AdministratorRoomController
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
            'Create room',
            '/admin/rooms',
            'Create room',
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
                'Create room',
                '/admin/rooms',
                'Create room',
                $values,
                $exception->getMessage(),
                422,
            );
        }

        try {
            $this->services->rooms()->create($validated);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->formResponse(
                    'Create room',
                    '/admin/rooms',
                    'Create room',
                    $this->validatedValues($validated),
                    $exception->getMessage(),
                    422,
                );
            }

            if (!$exception->isConflict()) {
                throw $exception;
            }

            return $this->formResponse(
                'Create room',
                '/admin/rooms',
                'Create room',
                $this->validatedValues($validated),
                $exception->getMessage(),
                409,
            );
        }

        $this->flashMessages->roomSuccess('Room created successfully.');

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
            $room = $this->services->rooms()->get($id);
            $amenityIds = $this->services->rooms()->amenityIds($id);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        return $this->formResponse(
            'Edit room',
            '/admin/rooms/' . $room->id(),
            'Save room',
            $this->storedValues($room, $amenityIds),
        );
    }

    /** @param array<string, string> $parameters */
    public function update(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null || !$this->roomExists($id)) {
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
                'Edit room',
                '/admin/rooms/' . $id,
                'Save room',
                $values,
                $exception->getMessage(),
                422,
            );
        }

        try {
            $this->services->rooms()->update($id, $validated);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            if (!$exception->isConflict()) {
                throw $exception;
            }

            return $this->formResponse(
                'Edit room',
                '/admin/rooms/' . $id,
                'Save room',
                $this->validatedValues($validated),
                $exception->getMessage(),
                409,
            );
        }

        $this->flashMessages->roomSuccess('Room updated successfully.');

        return $this->redirectToIndex();
    }

    /** @param array<string, string> $parameters */
    public function softDelete(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            $this->services->rooms()->softDelete($id);
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        $this->flashMessages->roomSuccess('Room removed successfully.');

        return $this->redirectToIndex();
    }

    private function roomExists(int $id): bool
    {
        try {
            $this->services->rooms()->get($id);

            return true;
        } catch (RoomCatalogException $exception) {
            if ($exception->isNotFound()) {
                return false;
            }

            throw $exception;
        }
    }

    private function validatedRequest(Request $request): RoomUpsertRequest
    {
        return RoomUpsertRequest::fromInput(
            $request->input('room_type_id'),
            $request->input('room_number'),
            $request->input('name'),
            $request->input('slug'),
            $request->input('description'),
            $request->input('price_per_night'),
            $request->input('capacity_adults'),
            $request->input('capacity_children'),
            $request->input('operational_status'),
            $request->input('published'),
            $request->input('amenity_ids'),
        );
    }

    /**
     * @return array{
     *     roomTypeId: string,
     *     roomNumber: string,
     *     name: string,
     *     slug: string,
     *     description: string,
     *     pricePerNight: string,
     *     capacityAdults: string,
     *     capacityChildren: string,
     *     operationalStatus: string,
     *     published: string,
     *     amenityIds: list<string>
     * }
     */
    private function inputValues(Request $request): array
    {
        return [
            'roomTypeId' => $this->scalarInput($request->input('room_type_id')),
            'roomNumber' => $this->scalarInput($request->input('room_number')),
            'name' => $this->scalarInput($request->input('name')),
            'slug' => $this->scalarInput($request->input('slug')),
            'description' => $this->scalarInput($request->input('description')),
            'pricePerNight' => $this->scalarInput($request->input('price_per_night')),
            'capacityAdults' => $this->scalarInput($request->input('capacity_adults')),
            'capacityChildren' => $this->scalarInput($request->input('capacity_children')),
            'operationalStatus' => $this->scalarInput($request->input('operational_status')),
            'published' => $this->scalarInput($request->input('published')),
            'amenityIds' => $this->listInput($request->input('amenity_ids')),
        ];
    }

    private function scalarInput(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    /** @return list<string> */
    private function listInput(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_string($item) || is_int($item)) {
                $items[] = (string) $item;
            }
        }

        return $items;
    }

    /**
     * @return array{
     *     roomTypeId: string,
     *     roomNumber: string,
     *     name: string,
     *     slug: string,
     *     description: string,
     *     pricePerNight: string,
     *     capacityAdults: string,
     *     capacityChildren: string,
     *     operationalStatus: string,
     *     published: string,
     *     amenityIds: list<string>
     * }
     */
    private function emptyForm(): array
    {
        return [
            'roomTypeId' => '',
            'roomNumber' => '',
            'name' => '',
            'slug' => '',
            'description' => '',
            'pricePerNight' => '',
            'capacityAdults' => '1',
            'capacityChildren' => '0',
            'operationalStatus' => Room::STATUS_ACTIVE,
            'published' => '0',
            'amenityIds' => [],
        ];
    }

    /**
     * @param list<int> $amenityIds
     * @return array{
     *     roomTypeId: string,
     *     roomNumber: string,
     *     name: string,
     *     slug: string,
     *     description: string,
     *     pricePerNight: string,
     *     capacityAdults: string,
     *     capacityChildren: string,
     *     operationalStatus: string,
     *     published: string,
     *     amenityIds: list<string>
     * }
     */
    private function storedValues(Room $room, array $amenityIds): array
    {
        return [
            'roomTypeId' => (string) $room->roomType()->id(),
            'roomNumber' => $room->roomNumber(),
            'name' => $room->name(),
            'slug' => $room->slug(),
            'description' => $room->description() ?? '',
            'pricePerNight' => $room->pricePerNight(),
            'capacityAdults' => (string) $room->capacityAdults(),
            'capacityChildren' => (string) $room->capacityChildren(),
            'operationalStatus' => $room->operationalStatus(),
            'published' => $room->isPublished() ? '1' : '0',
            'amenityIds' => array_map(static fn (int $id): string => (string) $id, $amenityIds),
        ];
    }

    /**
     * @return array{
     *     roomTypeId: string,
     *     roomNumber: string,
     *     name: string,
     *     slug: string,
     *     description: string,
     *     pricePerNight: string,
     *     capacityAdults: string,
     *     capacityChildren: string,
     *     operationalStatus: string,
     *     published: string,
     *     amenityIds: list<string>
     * }
     */
    private function validatedValues(RoomUpsertRequest $request): array
    {
        return [
            'roomTypeId' => (string) $request->roomTypeId(),
            'roomNumber' => $request->roomNumber(),
            'name' => $request->name(),
            'slug' => $request->slug(),
            'description' => $request->description() ?? '',
            'pricePerNight' => $request->pricePerNight(),
            'capacityAdults' => (string) $request->capacityAdults(),
            'capacityChildren' => (string) $request->capacityChildren(),
            'operationalStatus' => $request->operationalStatus(),
            'published' => $request->isPublished() ? '1' : '0',
            'amenityIds' => array_map(static fn (int $id): string => (string) $id, $request->amenityIds()),
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

    /**
     * @param array{
     *     roomTypeId: string,
     *     roomNumber: string,
     *     name: string,
     *     slug: string,
     *     description: string,
     *     pricePerNight: string,
     *     capacityAdults: string,
     *     capacityChildren: string,
     *     operationalStatus: string,
     *     published: string,
     *     amenityIds: list<string>
     * } $values
     */
    private function formResponse(
        string $heading,
        string $action,
        string $submitLabel,
        array $values,
        ?string $error = null,
        int $statusCode = 200,
    ): Response {
        $html = $this->views->render(
            'admin.rooms.form',
            [
                'title' => $heading . ' | Tara Guest House',
                'pageTitle' => $heading,
                'activeNavigation' => 'rooms',
                'csrfToken' => $this->csrfTokens->token(),
                'heading' => $heading,
                'action' => $action,
                'submitLabel' => $submitLabel,
                'values' => $values,
                'roomTypes' => array_map($this->roomTypeOption(...), $this->services->roomTypes()->listAll()),
                'amenities' => array_map($this->amenityOption(...), $this->services->amenities()->listAll()),
                'error' => $error,
            ],
            'layouts.admin',
        );

        return Response::html($html, $statusCode, ['Cache-Control' => 'no-store']);
    }

    /** @return array{id: string, name: string, active: bool} */
    private function roomTypeOption(RoomType $roomType): array
    {
        return [
            'id' => (string) $roomType->id(),
            'name' => $roomType->name(),
            'active' => $roomType->isActive(),
        ];
    }

    /** @return array{id: string, name: string, active: bool} */
    private function amenityOption(Amenity $amenity): array
    {
        return [
            'id' => (string) $amenity->id(),
            'name' => $amenity->name(),
            'active' => $amenity->isActive(),
        ];
    }

    private function redirectToIndex(): Response
    {
        return Response::redirect('/admin/rooms', 303, ['Cache-Control' => 'no-store']);
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
    }
}
