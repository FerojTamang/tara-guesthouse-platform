<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\PublicPaymentMethodException;
use App\Services\PublicPaymentMethodServiceProvider;

/**
 * Handles the public active payment-method JSON endpoint.
 */
final readonly class PublicPaymentMethodController
{
    public function __construct(
        private PublicPaymentMethodServiceProvider $services,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            return Response::json(
                [
                    'success' => true,
                    'data' => $this->services->service()->listActive(),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (PublicPaymentMethodException) {
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
