<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AvailabilityException;
use App\Requests\AvailabilityRequest;
use App\Services\PublicAvailabilityServiceProvider;

/**
 * Handles the public Version 1 calculated-availability JSON endpoint.
 */
final readonly class PublicAvailabilityController
{
    public function __construct(private PublicAvailabilityServiceProvider $services)
    {
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
}
