<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\RoomCatalogException;
use App\Exceptions\RoomImageException;
use App\Requests\RoomImageMetadataRequest;
use App\Requests\RoomImageUploadRequest;
use App\Services\RoomCatalogFlashMessages;
use App\Services\RoomImageService;
use Closure;
use Throwable;

final class AdministratorRoomImageController
{
    /** @var Closure(): mixed */
    private readonly Closure $serviceProvider;

    /** @var Closure(): mixed */
    private readonly Closure $uploadConfigurationProvider;

    public function __construct(
        callable $serviceProvider,
        callable $uploadConfigurationProvider,
        private RoomCatalogFlashMessages $flashMessages,
    ) {
        $this->serviceProvider = Closure::fromCallable($serviceProvider);
        $this->uploadConfigurationProvider = Closure::fromCallable($uploadConfigurationProvider);
    }

    /** @param array<string, string> $parameters */
    public function upload(Request $request, array $parameters): Response
    {
        $roomId = $this->routeId($parameters, 'roomId');

        if ($roomId === null) {
            return $this->notFound();
        }

        try {
            $validated = RoomImageUploadRequest::fromInput(
                $roomId,
                $request->file('image'),
                $request->input('alt_text'),
                $request->input('sort_order'),
                $request->input('is_primary'),
                $this->uploadConfiguration(),
            );
            $this->service()->upload($validated);
            $this->flashMessages->roomSuccess('Room image uploaded successfully.');

            return $this->success($roomId);
        } catch (RoomCatalogException $exception) {
            return $this->validation($request, $roomId, $exception);
        } catch (RoomImageException $exception) {
            return $this->imageFailure($request, $roomId, $exception);
        } catch (Throwable) {
            return $this->systemFailure();
        }
    }

    /** @param array<string, string> $parameters */
    public function updateMetadata(Request $request, array $parameters): Response
    {
        $roomId = $this->routeId($parameters, 'roomId');
        $imageId = $this->routeId($parameters, 'imageId');

        if ($roomId === null || $imageId === null) {
            return $this->notFound();
        }

        try {
            $validated = RoomImageMetadataRequest::fromInput(
                $roomId,
                $imageId,
                $request->input('alt_text'),
                $request->input('sort_order'),
            );
        } catch (RoomCatalogException $exception) {
            return $this->validation($request, $roomId, $exception);
        }

        try {
            $this->service()->updateMetadata(
                $validated->roomId(),
                $validated->imageId(),
                $validated->alternativeText(),
                $validated->sortOrder(),
            );
            $this->flashMessages->roomSuccess('Room image metadata updated successfully.');

            return $this->success($roomId);
        } catch (RoomImageException $exception) {
            return $this->imageFailure($request, $roomId, $exception);
        } catch (Throwable) {
            return $this->systemFailure();
        }
    }

    /** @param array<string, string> $parameters */
    public function setPrimary(Request $request, array $parameters): Response
    {
        return $this->identifierAction(
            $parameters,
            fn (int $roomId, int $imageId) => $this->service()->setPrimary($roomId, $imageId),
            'Primary room image updated successfully.',
        );
    }

    /** @param array<string, string> $parameters */
    public function delete(Request $request, array $parameters): Response
    {
        return $this->identifierAction(
            $parameters,
            fn (int $roomId, int $imageId) => $this->service()->delete($roomId, $imageId),
            'Room image removed successfully.',
        );
    }

    /** @param array<string, string> $parameters */
    private function identifierAction(array $parameters, callable $action, string $message): Response
    {
        $roomId = $this->routeId($parameters, 'roomId');
        $imageId = $this->routeId($parameters, 'imageId');

        if ($roomId === null || $imageId === null) {
            return $this->notFound();
        }

        try {
            $action($roomId, $imageId);
            $this->flashMessages->roomSuccess($message);

            return $this->success($roomId);
        } catch (RoomImageException $exception) {
            return $this->imageFailure(null, $roomId, $exception);
        } catch (Throwable) {
            return $this->systemFailure();
        }
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters, string $key): ?int
    {
        $value = $parameters[$key] ?? null;

        if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            return null;
        }

        $normalized = ltrim($value, '0');

        if ($normalized === '') {
            return null;
        }

        $maximum = (string) PHP_INT_MAX;

        if (
            strlen($normalized) > strlen($maximum)
            || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) > 0)
        ) {
            return null;
        }

        return (int) $normalized;
    }

    private function imageFailure(?Request $request, int $roomId, RoomImageException $exception): Response
    {
        if ($exception->isNotFound()) {
            return $this->notFound();
        }

        if ($exception->isLimit()) {
            return $this->recoverableFailure($request, $roomId, $exception->getMessage());
        }

        return Response::text($exception->getMessage(), 500, ['Cache-Control' => 'no-store']);
    }

    private function service(): RoomImageService
    {
        $service = ($this->serviceProvider)();

        if (!$service instanceof RoomImageService) {
            throw RoomImageException::system();
        }

        return $service;
    }

    /** @return array<string, mixed> */
    private function uploadConfiguration(): array
    {
        $configuration = ($this->uploadConfigurationProvider)();

        if (!is_array($configuration)) {
            throw RoomImageException::system();
        }

        return $configuration;
    }

    private function validation(Request $request, int $roomId, RoomCatalogException $exception): Response
    {
        return $this->recoverableFailure($request, $roomId, $exception->getMessage());
    }

    private function systemFailure(): Response
    {
        return Response::text('Room image operation failed.', 500, ['Cache-Control' => 'no-store']);
    }

    private function success(int $roomId): Response
    {
        return Response::redirect($this->editLocation($roomId), 303, ['Cache-Control' => 'no-store']);
    }

    private function recoverableFailure(?Request $request, int $roomId, string $message): Response
    {
        $this->flashMessages->roomImageError($message);

        if ($request !== null) {
            $this->flashMessages->roomImageOldInput([
                'alt_text' => $this->safeScalar($request->input('alt_text'), 255),
                'sort_order' => $this->safeScalar($request->input('sort_order'), 5),
                'is_primary' => $request->input('is_primary') === '1' ? '1' : '0',
            ]);
        }

        return Response::redirect($this->editLocation($roomId), 303, ['Cache-Control' => 'no-store']);
    }

    private function safeScalar(mixed $value, int $maximumBytes): string
    {
        return is_string($value) && strlen($value) <= $maximumBytes && preg_match('//u', $value) === 1
            ? $value
            : '';
    }

    private function editLocation(int $roomId): string
    {
        return '/admin/rooms/' . $roomId . '/edit#room-images';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
    }
}
