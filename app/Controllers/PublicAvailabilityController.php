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

            return Response::json(
                [
                    'success' => true,
                    'data' => $this->services->service()->search($validated),
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
            $values = [
                'checkIn' => $validated->checkIn()->format('Y-m-d'),
                'checkOut' => $validated->checkOut()->format('Y-m-d'),
                'adults' => (string) $validated->adults(),
                'children' => (string) $validated->children(),
            ];

            return $this->pageResponse(
                $values,
                $this->services->service()->search($validated),
                null,
                true,
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
     * @return array{checkIn: string, checkOut: string, adults: string, children: string}
     */
    private function pageValues(Request $request): array
    {
        return [
            'checkIn' => $this->displayValue($request->query('check_in')),
            'checkOut' => $this->displayValue($request->query('check_out')),
            'adults' => $this->displayValue($request->query('adults', '1')),
            'children' => $this->displayValue($request->query('children', '0')),
        ];
    }

    private function displayValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        return is_int($value) ? (string) $value : '';
    }

    /**
     * @param array{checkIn: string, checkOut: string, adults: string, children: string} $values
     * @param list<array<string, mixed>> $rooms
     */
    private function pageResponse(
        array $values,
        array $rooms,
        ?string $error,
        bool $submitted,
        int $statusCode = 200,
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
