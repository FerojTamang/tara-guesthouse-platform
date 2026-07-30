<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\UploadedFile;
use App\Exceptions\GuestPaymentException;
use App\Requests\GuestPaymentRequest;
use App\Services\GuestPaymentSubmissionServiceProvider;
use Closure;
use JsonException;
use Throwable;

/**
 * Formats the public Version 1 Guest payment-submission JSON contract.
 */
final class PublicPaymentController
{
    /** @var Closure(): mixed */
    private readonly Closure $uploadConfigurationProvider;

    /** @param callable(): mixed $uploadConfigurationProvider */
    public function __construct(
        private readonly GuestPaymentSubmissionServiceProvider $services,
        callable $uploadConfigurationProvider,
    ) {
        $this->uploadConfigurationProvider = Closure::fromCallable(
            $uploadConfigurationProvider,
        );
    }

    /** @param array<string, string> $parameters */
    public function create(Request $request, array $parameters): Response
    {
        $mediaType = $this->mediaType($request);

        if (!in_array($mediaType, ['application/json', 'multipart/form-data'], true)) {
            return $this->error(
                'Content-Type must be application/json or multipart/form-data.',
                415,
            );
        }

        if ($mediaType === 'application/json') {
            if ($request->file() !== []) {
                return $this->validation('Payment request payload is invalid.');
            }

            try {
                $input = json_decode(
                    $request->body(),
                    true,
                    16,
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                return $this->error('Request body must contain valid JSON.', 400);
            }

            if (!is_array($input) || array_is_list($input)) {
                return $this->error('Request body must contain a JSON object.', 400);
            }
        } else {
            $input = $request->input();
        }

        if (!$this->hasApprovedInputKeys($input) || !$this->hasApprovedFileKeys($request)) {
            return $this->validation('Payment request payload is invalid.');
        }

        try {
            $paymentRequest = GuestPaymentRequest::fromInput(
                $input['booking_reference'],
                $input['booking_access_token'],
                $input['payment_method_id'],
                $input['amount'],
                $input['transaction_reference'] ?? null,
                $input['guest_notes'] ?? null,
                $request->file('proof_upload'),
                $this->uploadConfiguration(),
            );
            $payment = $this->services->service()->submit($paymentRequest);

            return Response::json(
                [
                    'success' => true,
                    'message' => 'Payment submitted for review.',
                    'data' => [
                        'payment_id' => $payment->id(),
                        'payment_status' => $payment->paymentStatus(),
                        'booking_reference' => $paymentRequest->bookingReference(),
                    ],
                ],
                201,
                ['Cache-Control' => 'no-store'],
            );
        } catch (GuestPaymentException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->validation($exception->getMessage());
            }

            if ($exception->isAccessDenied()) {
                return $this->error($exception->getMessage(), 403);
            }

            if ($exception->isConflict()) {
                return $this->error($exception->getMessage(), 409);
            }

            return $this->error('Internal Server Error.', 500);
        } catch (Throwable) {
            return $this->error('Internal Server Error.', 500);
        }
    }

    /** @param array<string, mixed> $input */
    private function hasApprovedInputKeys(array $input): bool
    {
        $required = [
            'booking_reference',
            'booking_access_token',
            'payment_method_id',
            'amount',
        ];
        $allowed = [
            ...$required,
            'transaction_reference',
            'guest_notes',
            '_csrf',
        ];

        foreach ($required as $key) {
            if (!array_key_exists($key, $input)) {
                return false;
            }
        }

        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    private function hasApprovedFileKeys(Request $request): bool
    {
        $files = $request->file();

        if (!is_array($files)) {
            return false;
        }

        foreach ($files as $key => $file) {
            if ($key !== 'proof_upload' || (!$file instanceof UploadedFile && !is_array($file))) {
                return false;
            }
        }

        return true;
    }

    private function mediaType(Request $request): ?string
    {
        $contentType = $request->header('Content-Type');

        if ($contentType === null) {
            return null;
        }

        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    /** @return array<string, mixed> */
    private function uploadConfiguration(): array
    {
        $configuration = ($this->uploadConfigurationProvider)();

        if (!is_array($configuration)) {
            throw GuestPaymentException::failure();
        }

        return $configuration;
    }

    private function validation(string $message): Response
    {
        return Response::json(
            [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'payment' => [$message],
                ],
            ],
            422,
            ['Cache-Control' => 'no-store'],
        );
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
