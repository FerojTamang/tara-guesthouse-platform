<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\BookingException;
use App\Requests\GuestBookingRequest;
use App\Services\BookingSubmissionWorkflowProvider;
use DateTimeZone;
use JsonException;

/**
 * Formats the public Version 1 Guest-booking JSON contract.
 *
 * Route registration is intentionally deferred until CSRF, public rate
 * limiting, and production configuration are attached together.
 */
final readonly class PublicBookingController
{
    public function __construct(
        private BookingSubmissionWorkflowProvider $workflows,
        private DateTimeZone $responseTimezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function create(Request $request, array $parameters): Response
    {
        if (!$this->usesJsonContentType($request)) {
            return $this->error('Content-Type must be application/json.', 415);
        }

        try {
            $payload = json_decode(
                $request->body(),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->error('Request body must contain valid JSON.', 400);
        }

        if (!is_array($payload) || array_is_list($payload)) {
            return $this->error('Request body must contain a JSON object.', 400);
        }

        if (!$this->hasApprovedKeys($payload)) {
            return $this->error('Request payload is invalid.', 422);
        }

        try {
            $bookingRequest = GuestBookingRequest::fromInput(
                $payload['room_id'],
                $payload['guest_full_name'],
                $payload['guest_email'],
                $payload['guest_phone'],
                $payload['check_in'],
                $payload['check_out'],
                $payload['adults'],
                $payload['children'],
                $payload['special_request'] ?? null,
                $payload['terms_accepted'],
                $payload['policy_version'] ?? null,
            );
            $result = $this->workflows->workflow()->submit($bookingRequest);
            $booking = $result->booking();
            $deadline = $booking->confirmationDeadlineAt();

            if ($deadline === null) {
                throw BookingException::failure();
            }

            return Response::json(
                [
                    'success' => true,
                    'message' => 'Booking received.',
                    'data' => [
                        'booking_reference' => $booking->bookingReference(),
                        'booking_status' => $booking->bookingStatus(),
                        'confirmation_deadline_at' => $deadline
                            ->setTimezone($this->responseTimezone)
                            ->format(DATE_ATOM),
                        'currency' => $booking->currency(),
                        'total_amount' => $booking->totalAmount(),
                        'booking_access_token' => $result->bookingAccessToken(),
                    ],
                ],
                201,
                ['Cache-Control' => 'no-store'],
            );
        } catch (BookingException $exception) {
            if ($exception->isValidationFailure()) {
                return Response::json(
                    [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => [
                            'booking' => [$exception->getMessage()],
                        ],
                    ],
                    422,
                    ['Cache-Control' => 'no-store'],
                );
            }

            if ($exception->isNotFound()) {
                return $this->error($exception->getMessage(), 404);
            }

            if ($exception->isConflict()) {
                return $this->error($exception->getMessage(), 409);
            }

            return $this->error('Internal Server Error.', 500);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasApprovedKeys(array $payload): bool
    {
        $required = [
            'room_id',
            'guest_full_name',
            'guest_email',
            'guest_phone',
            'check_in',
            'check_out',
            'adults',
            'children',
            'terms_accepted',
        ];
        $allowed = [
            ...$required,
            'special_request',
            'policy_version',
        ];

        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                return false;
            }
        }

        foreach (array_keys($payload) as $key) {
            if (!in_array($key, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        if ($contentType === null) {
            return false;
        }

        return strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/json';
    }

    private function error(string $message, int $statusCode): Response
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
