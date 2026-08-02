<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\AvailabilityException;
use App\Requests\AvailabilityRequest;
use App\Services\PublicAvailabilityServiceProvider;

/**
 * Handles the public Version 1 calculated-availability JSON and HTML workflows.
 */
final readonly class PublicAvailabilityController
{
    public function __construct(
        private PublicAvailabilityServiceProvider $services,
        private ?ViewRenderer $views = null,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            $validated = AvailabilityRequest::fromInput(
                $request->query('check_in'),
                $request->query('check_out'),
                $request->query('adults'),
                $request->query('children'),
            );

            $roomSlug = $this->roomSlug($request->query('room'));

            return Response::json(
                [
                    'success' => true,
                    'data' => $this->services->service()->search($validated, $roomSlug),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (AvailabilityException $exception) {
            if ($exception->isValidationFailure()) {
                return Response::json(
                    [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => [
                            'availability' => [$exception->getMessage()],
                        ],
                    ],
                    422,
                    ['Cache-Control' => 'no-store'],
                );
            }

            return Response::json(
                [
                    'success' => false,
                    'message' => 'Internal Server Error.',
                ],
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }

    /** @param array<string, string> $parameters */
    public function calendar(Request $request, array $parameters): Response
    {
        try {
            return Response::json(
                $this->services->service()->calendar(
                    $parameters['slug'] ?? '',
                    $request->query('from'),
                    $request->query('to'),
                ),
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (AvailabilityException $exception) {
            if ($exception->isNotFound()) {
                return Response::json(
                    ['message' => 'Not Found.'],
                    404,
                    ['Cache-Control' => 'no-store'],
                );
            }

            if ($exception->isValidationFailure()) {
                return Response::json(
                    [
                        'message' => 'Validation failed.',
                        'errors' => ['calendar' => [$exception->getMessage()]],
                    ],
                    422,
                    ['Cache-Control' => 'no-store'],
                );
            }

            return Response::json(
                ['message' => 'Internal Server Error.'],
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }

    /** @param array<string, string> $parameters */
    public function page(Request $request, array $parameters): Response
    {
        if ($this->views === null) {
            return Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
        }

        $submitted = $request->query() !== [];
        $values = $this->pageValues($request);

        if (!$submitted) {
            return $this->pageResponse($values, [], null, false);
        }

        try {
            $validated = AvailabilityRequest::fromInput(
                $request->query('check_in'),
                $request->query('check_out'),
                $request->query('adults'),
                $request->query('children'),
            );
            $roomSlug = $this->roomSlug($request->query('room'));
            $context = $this->services->service()->searchContext($validated, $roomSlug);
            $values = [
                'room' => $roomSlug ?? '',
                'checkIn' => $validated->checkIn()->format('Y-m-d'),
                'checkOut' => $validated->checkOut()->format('Y-m-d'),
                'adults' => (string) $validated->adults(),
                'children' => (string) $validated->children(),
            ];

            return $this->pageResponse(
                $values,
                $context['rooms'],
                null,
                true,
                200,
                $context['selectedRoom'],
            );
        } catch (AvailabilityException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->pageResponse(
                    $values,
                    [],
                    $exception->getMessage(),
                    true,
                    422,
                );
            }

            return Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }

    /**
     * @return array{room: string, checkIn: string, checkOut: string, adults: string, children: string}
     */
    private function pageValues(Request $request): array
    {
        return [
            'room' => $this->displayValue($request->query('room')),
            'checkIn' => $this->displayValue($request->query('check_in')),
            'checkOut' => $this->displayValue($request->query('check_out')),
            'adults' => $this->displayValue($request->query('adults', '1')),
            'children' => $this->displayValue($request->query('children', '0')),
        ];
    }

    private function roomSlug(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw AvailabilityException::invalidInput('The selected room is unavailable.');
        }

        return $value;
    }

    private function displayValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        return is_int($value) ? (string) $value : '';
    }

    /**
     * @param array{room: string, checkIn: string, checkOut: string, adults: string, children: string} $values
     * @param list<array<string, mixed>> $rooms
     */
    private function pageResponse(
        array $values,
        array $rooms,
        ?string $error,
        bool $submitted,
        int $statusCode = 200,
        ?array $selectedRoom = null,
    ): Response {
        if ($this->views === null) {
            return Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
        }

        $html = $this->views->render(
            'public.availability.index',
            [
                'title' => 'Check availability | Tara Guest House',
                'values' => $values,
                'rooms' => $rooms,
                'error' => $error,
                'submitted' => $submitted,
                'selectedRoom' => $selectedRoom,
                'isBookingFlow' => true,
            ],
            'layouts.base',
        );

        return Response::html(
            $html,
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }
}
