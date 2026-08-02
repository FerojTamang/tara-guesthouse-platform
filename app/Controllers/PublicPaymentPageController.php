<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\UploadedFile;
use App\Core\ViewRenderer;
use App\Exceptions\GuestPaymentException;
use App\Models\GuestPaymentPageContext;
use App\Requests\GuestPaymentRequest;
use App\Services\GuestPaymentContinuation;
use App\Services\GuestPaymentPageServiceProvider;
use App\Services\GuestPaymentSubmissionServiceProvider;
use App\Services\GuestPaymentSuccessFlash;
use Closure;
use DateTimeZone;
use Throwable;

/**
 * Handles the secure PHP-rendered Guest payment and success workflow.
 */
final class PublicPaymentPageController
{
    /** @var Closure(): mixed */
    private readonly Closure $uploadConfigurationProvider;

    /** @param callable(): mixed $uploadConfigurationProvider */
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly GuestPaymentPageServiceProvider $pages,
        private readonly GuestPaymentSubmissionServiceProvider $submissions,
        private readonly GuestPaymentContinuation $continuation,
        private readonly GuestPaymentSuccessFlash $successFlash,
        private readonly CsrfTokenManager $csrfTokens,
        callable $uploadConfigurationProvider,
        private readonly DateTimeZone $responseTimezone,
    ) {
        $this->uploadConfigurationProvider = Closure::fromCallable(
            $uploadConfigurationProvider,
        );
    }

    /** @param array<string, string> $parameters */
    public function form(Request $request, array $parameters): Response
    {
        try {
            $resolved = $this->resolveContext();

            if ($resolved === null) {
                return $this->bookingRedirect();
            }

            return $this->formResponse(
                $resolved['context'],
                $this->values([]),
                null,
            );
        } catch (GuestPaymentException $exception) {
            return $this->contextFailure($exception);
        } catch (Throwable) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function submit(Request $request, array $parameters): Response
    {
        $input = $request->input();
        $values = $this->values($input);
        $resolved = null;

        try {
            $resolved = $this->resolveContext();

            if ($resolved === null) {
                return $this->bookingRedirect();
            }

            if (!$this->hasApprovedInputKeys($input) || !$this->hasApprovedFileKeys($request)) {
                throw GuestPaymentException::validation(
                    'Payment request payload is invalid.',
                );
            }

            $booking = $resolved['context']->booking();
            $paymentRequest = GuestPaymentRequest::fromInput(
                $resolved['bookingReference'],
                $resolved['bookingAccessToken'],
                $input['payment_method_id'],
                $booking->totalAmount(),
                $input['transaction_reference'] ?? null,
                $input['guest_notes'] ?? null,
                $request->file('proof_upload'),
                $this->uploadConfiguration(),
            );
            $payment = $this->submissions->service()->submit($paymentRequest);
            $this->successFlash->store(
                $payment,
                $resolved['bookingReference'],
            );

            return Response::redirect(
                '/booking/payment/success',
                303,
                ['Cache-Control' => 'no-store'],
            );
        } catch (GuestPaymentException $exception) {
            if ($exception->isValidationFailure()) {
                if ($resolved === null) {
                    try {
                        $resolved = $this->resolveContext();
                    } catch (GuestPaymentException $contextFailure) {
                        return $this->contextFailure($contextFailure);
                    }
                }

                if ($resolved === null) {
                    return $this->bookingRedirect();
                }

                return $this->formResponse(
                    $resolved['context'],
                    $values,
                    $exception->getMessage(),
                    422,
                );
            }

            if ($exception->isAccessDenied()) {
                return $this->clearAndRedirect();
            }

            if ($exception->isConflict()) {
                try {
                    $fresh = $this->resolveContext();

                    if ($fresh === null) {
                        return $this->bookingRedirect();
                    }

                    return $this->formResponse(
                        $fresh['context'],
                        $values,
                        $exception->getMessage(),
                        409,
                    );
                } catch (GuestPaymentException $contextFailure) {
                    return $this->contextFailure($contextFailure);
                }
            }

            return $this->failure();
        } catch (Throwable) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function success(Request $request, array $parameters): Response
    {
        $payment = $this->successFlash->pull();

        if ($payment === null) {
            return Response::redirect(
                '/booking/payment',
                302,
                ['Cache-Control' => 'no-store'],
            );
        }

        try {
            $html = $this->views->render(
                'public.payment.success',
                [
                    'title' => 'Payment submitted | Tara Guest House',
                    'payment' => $payment,
                    'isBookingFlow' => true,
                ],
                'layouts.base',
            );

            return Response::html(
                $html,
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (Throwable) {
            return $this->failure();
        }
    }

    /**
     * @return null|array{
     *     bookingReference: string,
     *     bookingAccessToken: string,
     *     context: GuestPaymentPageContext
     * }
     */
    private function resolveContext(): ?array
    {
        $continuation = $this->continuation->current();

        if ($continuation === null) {
            return null;
        }

        return [
            'bookingReference' => $continuation['booking_reference'],
            'bookingAccessToken' => $continuation['booking_access_token'],
            'context' => $this->pages->service()->load(
                $continuation['booking_reference'],
                $continuation['booking_access_token'],
            ),
        ];
    }

    /** @param array<string, mixed> $input */
    private function hasApprovedInputKeys(array $input): bool
    {
        if (!array_key_exists('payment_method_id', $input)) {
            return false;
        }

        $allowed = [
            '_csrf',
            'payment_method_id',
            'transaction_reference',
            'guest_notes',
        ];

        foreach (array_keys($input) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
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
            if ($key !== 'proof_upload' || !$file instanceof UploadedFile) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $source
     * @return array{
     *     paymentMethodId: string,
     *     transactionReference: string,
     *     guestNotes: string
     * }
     */
    private function values(array $source): array
    {
        return [
            'paymentMethodId' => $this->display(
                $source['payment_method_id'] ?? '',
            ),
            'transactionReference' => $this->display(
                $source['transaction_reference'] ?? '',
            ),
            'guestNotes' => $this->display(
                $source['guest_notes'] ?? '',
                false,
            ),
        ];
    }

    private function display(mixed $value, bool $trim = true): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        return $trim ? trim($value) : $value;
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

    /**
     * @param array<string, string> $values
     */
    private function formResponse(
        GuestPaymentPageContext $context,
        array $values,
        ?string $error,
        int $statusCode = 200,
    ): Response {
        $booking = $context->booking();
        $paymentMethods = [];

        foreach ($context->paymentMethods() as $method) {
            $paymentMethods[] = [
                'id' => $method->id(),
                'name' => $method->name(),
                'instructions' => $method->instructions(),
                'accountReference' => $method->accountReference(),
                'qrImageUrl' => $this->qrImageUrl($method->qrImagePath()),
                'requiresProof' => $method->requiresProof(),
            ];
        }

        try {
            $html = $this->views->render(
                'public.payment.form',
                [
                    'title' => 'Submit payment | Tara Guest House',
                    'csrfToken' => $this->csrfTokens->token(),
                    'booking' => [
                        'bookingReference' => $booking->bookingReference(),
                        'currency' => $booking->currency(),
                        'totalAmount' => $booking->totalAmount(),
                        'confirmationDeadlineAt' => $booking
                            ->confirmationDeadlineAt()
                            ->setTimezone($this->responseTimezone)
                            ->format(DATE_ATOM),
                    ],
                    'paymentMethods' => $paymentMethods,
                    'values' => $values,
                    'error' => $error,
                    'fieldErrors' => $this->fieldErrors($error),
                    'isBookingFlow' => true,
                ],
                'layouts.base',
            );

            return Response::html(
                $html,
                $statusCode,
                ['Cache-Control' => 'no-store'],
            );
        } catch (Throwable) {
            return $this->failure();
        }
    }

    private function qrImageUrl(?string $path): ?string
    {
        if (
            $path === null
            || preg_match(
                '#\Apayment-methods/[a-zA-Z0-9][a-zA-Z0-9._/-]*\z#D',
                $path,
            ) !== 1
        ) {
            return null;
        }

        return '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /** @return array<string, string> */
    private function fieldErrors(?string $error): array
    {
        if ($error === null) {
            return [];
        }

        if (in_array($error, [
            'Payment method is invalid.',
            'The selected payment method is not available.',
        ], true)) {
            return ['paymentMethod' => $error];
        }

        if ($error === 'Transaction reference is invalid.') {
            return ['transactionReference' => $error];
        }

        if ($error === 'Guest notes are invalid.') {
            return ['guestNotes' => $error];
        }

        if (str_starts_with($error, 'Payment proof')) {
            return ['proof' => $error];
        }

        return [];
    }

    private function contextFailure(GuestPaymentException $exception): Response
    {
        if ($exception->isAccessDenied() || $exception->isConflict()) {
            return $this->clearAndRedirect();
        }

        return $this->failure();
    }

    private function clearAndRedirect(): Response
    {
        try {
            $this->continuation->clear();
        } catch (GuestPaymentException) {
            return $this->failure();
        }

        return $this->bookingRedirect();
    }

    private function bookingRedirect(): Response
    {
        return Response::redirect(
            '/booking',
            302,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function failure(): Response
    {
        return Response::text(
            'Internal Server Error',
            500,
            ['Cache-Control' => 'no-store'],
        );
    }
}
