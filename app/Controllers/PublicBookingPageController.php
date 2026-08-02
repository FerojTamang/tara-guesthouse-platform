<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\BookingException;
use App\Requests\GuestBookingRequest;
use App\Services\BookingSubmissionWorkflowProvider;
use App\Services\BookingSuccessFlash;
use App\Services\GuestPaymentContinuation;
use App\Services\PublicRoomCatalogServiceProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles the server-rendered Guest booking and success workflow.
 */
final readonly class PublicBookingPageController
{
    public function __construct(
        private ViewRenderer $views,
        private BookingSubmissionWorkflowProvider $workflows,
        private BookingSuccessFlash $successFlash,
        private GuestPaymentContinuation $paymentContinuation,
        private CsrfTokenManager $csrfTokens,
        private ?PublicRoomCatalogServiceProvider $roomCatalog = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function form(Request $request, array $parameters): Response
    {
        return $this->formResponse(
            $this->values($request->query()),
            null,
        );
    }

    /** @param array<string, string> $parameters */
    public function submit(Request $request, array $parameters): Response
    {
        $input = $request->input();
        $values = $this->values($input);

        try {
            $bookingRequest = GuestBookingRequest::fromInput(
                $input['room_id'] ?? null,
                $input['guest_full_name'] ?? null,
                $input['guest_email'] ?? null,
                $input['guest_phone'] ?? null,
                $input['check_in'] ?? null,
                $input['check_out'] ?? null,
                $input['adults'] ?? null,
                $input['children'] ?? null,
                $input['special_request'] ?? null,
                $this->accepted($input['terms_accepted'] ?? null),
                null,
            );
            $result = $this->workflows->workflow()->submit($bookingRequest);
            $this->successFlash->store($result);
            $this->paymentContinuation->store($result);

            return Response::redirect(
                '/booking/success',
                303,
                ['Cache-Control' => 'no-store'],
            );
        } catch (BookingException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->formResponse(
                    $values,
                    $exception->getMessage(),
                    422,
                    $this->errorField($exception->getMessage()),
                );
            }

            if ($exception->isNotFound()) {
                return $this->formResponse(
                    $values,
                    $exception->getMessage(),
                    404,
                );
            }

            if ($exception->isConflict()) {
                return $this->formResponse(
                    $values,
                    $exception->getMessage(),
                    409,
                );
            }

            $this->logUnexpectedFailure($exception);

            return Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
        } catch (Throwable $failure) {
            $this->logUnexpectedFailure($failure);

            return Response::text(
                'Internal Server Error',
                500,
                ['Cache-Control' => 'no-store'],
            );
        }
    }

    /** @param array<string, string> $parameters */
    public function success(Request $request, array $parameters): Response
    {
        $booking = $this->successFlash->pull();

        if ($booking === null) {
            return Response::redirect(
                '/booking',
                302,
                ['Cache-Control' => 'no-store'],
            );
        }

        $html = $this->views->render(
            'public.booking.success',
            [
                'title' => 'Booking received | Tara Guest House',
                'booking' => $booking,
                'isBookingFlow' => true,
            ],
            'layouts.base',
        );

        return Response::html(
            $html,
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * @param array<string, mixed> $source
     * @return array{
     *     roomId: string,
     *     guestFullName: string,
     *     guestEmail: string,
     *     guestPhone: string,
     *     checkIn: string,
     *     checkOut: string,
     *     adults: string,
     *     children: string,
     *     specialRequest: string,
     *     termsAccepted: bool
     * }
     */
    private function values(array $source): array
    {
        return [
            'roomId' => $this->display($source['room_id'] ?? ''),
            'guestFullName' => $this->display($source['guest_full_name'] ?? ''),
            'guestEmail' => $this->display($source['guest_email'] ?? ''),
            'guestPhone' => $this->display($source['guest_phone'] ?? ''),
            'checkIn' => $this->display($source['check_in'] ?? ''),
            'checkOut' => $this->display($source['check_out'] ?? ''),
            'adults' => $this->display($source['adults'] ?? '1'),
            'children' => $this->display($source['children'] ?? '0'),
            'specialRequest' => $this->display(
                $source['special_request'] ?? '',
            ),
            'termsAccepted' => $this->accepted(
                $source['terms_accepted'] ?? null,
            ),
        ];
    }

    private function display(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        return is_int($value) ? (string) $value : '';
    }

    private function accepted(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || (
                is_string($value)
                && in_array(
                    strtolower(trim($value)),
                    ['1', 'on', 'true', 'yes'],
                    true,
                )
            );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function formResponse(
        array $values,
        ?string $error,
        int $statusCode = 200,
        ?string $errorField = null,
    ): Response {
        $room = $this->roomSummary($values['roomId'] ?? '');
        $html = $this->views->render(
            'public.booking.form',
            [
                'title' => 'Request a booking | Tara Guest House',
                'csrfToken' => $this->csrfTokens->token(),
                'values' => $values,
                'error' => $error,
                'errorField' => $errorField,
                'room' => $room,
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

    private function errorField(string $message): string
    {
        return match (true) {
            str_contains($message, 'full name') => 'guest_full_name',
            str_contains($message, 'email') => 'guest_email',
            str_contains($message, 'phone') => 'guest_phone',
            str_contains($message, 'Check-in') => 'check_in',
            str_contains($message, 'Check-out') => 'check_out',
            str_contains($message, 'Adult') => 'adults',
            str_contains($message, 'Children') => 'children',
            str_contains($message, 'Special request') => 'special_request',
            str_contains($message, 'Terms') => 'terms_accepted',
            default => 'check_in',
        };
    }

    private function logUnexpectedFailure(Throwable $failure): void
    {
        if ($this->logger === null) {
            error_log('Public booking submission failed.');

            return;
        }

        try {
            $this->logger->error(
                'Public booking submission failed.',
                ['exception' => $failure],
            );
        } catch (Throwable) {
            error_log('Public booking submission failure logging failed.');
        }
    }

    /** @return ?array<string, mixed> */
    private function roomSummary(string $roomId): ?array
    {
        if (
            $this->roomCatalog === null
            || preg_match('/\A[1-9][0-9]*\z/D', $roomId) !== 1
            || strlen($roomId) > 10
        ) {
            return null;
        }

        try {
            return $this->roomCatalog->service()->getById((int) $roomId);
        } catch (\App\Exceptions\PublicRoomCatalogException) {
            return null;
        }
    }
}
