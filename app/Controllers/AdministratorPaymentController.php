<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\AdministratorPaymentException;
use App\Models\AdministratorPaymentRecord;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\AdministratorPaymentServiceProvider;
use App\Storage\RetrievedPaymentProof;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Renders authenticated Administrator payment pages and protected proof bytes.
 */
final readonly class AdministratorPaymentController
{
    public function __construct(
        private ViewRenderer $views,
        private AdministratorPaymentServiceProvider $services,
        private AdministratorSession $administratorSession,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            $status = $this->statusFilter($request->query('status'));
            $payments = array_map(
                $this->listData(...),
                $this->services->service()->list($status),
            );

            return $this->page(
                'admin.payments.index',
                [
                    'title' => 'Payments | Tara Guest House',
                    'pageTitle' => 'Payments',
                    'activeNavigation' => 'payments',
                    'payments' => $payments,
                    'statusOptions' => $this->statusOptions(),
                    'selectedStatus' => $status ?? '',
                    'error' => null,
                ],
            );
        } catch (AdministratorPaymentException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->page(
                    'admin.payments.index',
                    [
                        'title' => 'Payments | Tara Guest House',
                        'pageTitle' => 'Payments',
                        'activeNavigation' => 'payments',
                        'payments' => [],
                        'statusOptions' => $this->statusOptions(),
                        'selectedStatus' => '',
                        'error' => $exception->getMessage(),
                    ],
                    422,
                );
            }

            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $paymentId = $this->routeId($parameters);

        if ($paymentId === null) {
            return $this->notFound();
        }

        try {
            $record = $this->services->service()->detail($paymentId);

            if (!$record instanceof AdministratorPaymentRecord) {
                return $this->notFound();
            }

            return $this->page(
                'admin.payments.show',
                [
                    'title' => 'Payment #' . $paymentId . ' | Tara Guest House',
                    'pageTitle' => 'Payment detail',
                    'activeNavigation' => 'payments',
                    'payment' => $this->detailData($record),
                ],
            );
        } catch (AdministratorPaymentException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function proof(Request $request, array $parameters): Response
    {
        $paymentId = $this->routeId($parameters);

        if ($paymentId === null) {
            return $this->notFound();
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return Response::text(
                'Authentication required.',
                401,
                ['Cache-Control' => 'no-store'],
            );
        }

        try {
            $proof = $this->services->service()->proof(
                $paymentId,
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            if (!$proof instanceof RetrievedPaymentProof) {
                return $this->notFound();
            }

            return new Response(
                $proof->bytes(),
                200,
                [
                    'Content-Type' => $proof->mediaType(),
                    'Content-Length' => (string) $proof->byteSize(),
                    'Content-Disposition' => sprintf(
                        'inline; filename="payment-proof-%d.%s"',
                        $paymentId,
                        $proof->extension(),
                    ),
                    'Cache-Control' => 'no-store',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cross-Origin-Resource-Policy' => 'same-origin',
                    'Referrer-Policy' => 'no-referrer',
                ],
            );
        } catch (AdministratorPaymentException) {
            return $this->failure();
        }
    }

    /**
     * @return array{
     *     id: int,
     *     bookingId: int,
     *     bookingReference: string,
     *     guestName: string,
     *     paymentMethod: string,
     *     amount: string,
     *     statusLabel: string,
     *     statusBadge: string,
     *     submittedAt: string,
     *     createdAt: string,
     *     hasProof: bool
     * }
     */
    private function listData(AdministratorPaymentRecord $record): array
    {
        $payment = $record->payment();
        [$statusLabel, $statusBadge] = $this->paymentStatusPresentation(
            $payment->paymentStatus(),
        );

        return [
            'id' => $payment->id(),
            'bookingId' => $payment->bookingId(),
            'bookingReference' => $record->bookingReference(),
            'guestName' => $record->guestFullName(),
            'paymentMethod' => $record->paymentMethodName(),
            'amount' => $payment->currency() . ' ' . $payment->amount(),
            'statusLabel' => $statusLabel,
            'statusBadge' => $statusBadge,
            'submittedAt' => $this->optionalDateTime($payment->submittedAt())
                ?? 'Not submitted',
            'createdAt' => $this->dateTime($payment->createdAt()),
            'hasProof' => $payment->proofPath() !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function detailData(AdministratorPaymentRecord $record): array
    {
        $payment = $record->payment();
        [$paymentStatusLabel, $paymentStatusBadge] = $this->paymentStatusPresentation(
            $payment->paymentStatus(),
        );
        [$bookingStatusLabel, $bookingStatusBadge] = $this->bookingStatusPresentation(
            $record->bookingStatus(),
        );

        return [
            'id' => $payment->id(),
            'statusLabel' => $paymentStatusLabel,
            'statusBadge' => $paymentStatusBadge,
            'amount' => $payment->currency() . ' ' . $payment->amount(),
            'paymentMethod' => $record->paymentMethodName(),
            'paymentMethodActive' => $record->paymentMethodActive(),
            'paymentMethodRequiresProof' => $record->paymentMethodRequiresProof(),
            'paymentMethodAccountReference' => $record->paymentMethodAccountReference(),
            'transactionReference' => $payment->transactionReference(),
            'guestNotes' => $payment->guestNotes(),
            'submittedAt' => $this->optionalDateTime($payment->submittedAt()),
            'verifiedAt' => $this->optionalDateTime($payment->verifiedAt()),
            'verifiedBy' => $payment->verifiedBy() === null
                ? null
                : 'Administrator #' . $payment->verifiedBy(),
            'rejectedAt' => $this->optionalDateTime($payment->rejectedAt()),
            'rejectedBy' => $payment->rejectedBy() === null
                ? null
                : 'Administrator #' . $payment->rejectedBy(),
            'verificationNote' => $payment->verificationNote(),
            'createdAt' => $this->dateTime($payment->createdAt()),
            'updatedAt' => $this->dateTime($payment->updatedAt()),
            'proofUrl' => $payment->proofPath() === null
                ? null
                : '/admin/payments/' . $payment->id() . '/proof',
            'bookingId' => $payment->bookingId(),
            'bookingReference' => $record->bookingReference(),
            'bookingStatusLabel' => $bookingStatusLabel,
            'bookingStatusBadge' => $bookingStatusBadge,
            'bookingTotalAmount' => Payment::CURRENCY_NPR . ' ' . $record->bookingTotalAmount(),
            'guestName' => $record->guestFullName(),
            'guestEmail' => $record->guestEmail(),
            'guestPhone' => $record->guestPhone(),
            'checkIn' => $record->checkIn()->format('Y-m-d'),
            'checkOut' => $record->checkOut()->format('Y-m-d'),
            'confirmationDeadlineAt' => $this->dateTime(
                $record->confirmationDeadlineAt(),
            ),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return [
            ['value' => Payment::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => Payment::STATUS_SUBMITTED, 'label' => 'Submitted'],
            ['value' => Payment::STATUS_VERIFIED, 'label' => 'Verified'],
            ['value' => Payment::STATUS_REJECTED, 'label' => 'Rejected'],
            ['value' => Payment::STATUS_REFUNDED, 'label' => 'Refunded'],
        ];
    }

    /** @return array{string, string} */
    private function paymentStatusPresentation(string $status): array
    {
        return match ($status) {
            Payment::STATUS_PENDING => ['Pending', 'text-bg-secondary'],
            Payment::STATUS_SUBMITTED => ['Submitted', 'text-bg-warning'],
            Payment::STATUS_VERIFIED => ['Verified', 'text-bg-success'],
            Payment::STATUS_REJECTED => ['Rejected', 'text-bg-danger'],
            Payment::STATUS_REFUNDED => ['Refunded', 'text-bg-info'],
        };
    }

    /** @return array{string, string} */
    private function bookingStatusPresentation(string $status): array
    {
        return match ($status) {
            Booking::STATUS_PENDING => ['Pending', 'text-bg-warning'],
            Booking::STATUS_CONFIRMED => ['Confirmed', 'text-bg-primary'],
            Booking::STATUS_CHECKED_IN => ['Checked In', 'text-bg-info'],
            Booking::STATUS_CHECKED_OUT => ['Checked Out', 'text-bg-success'],
            Booking::STATUS_CANCELLED => ['Cancelled', 'text-bg-secondary'],
            Booking::STATUS_REJECTED => ['Rejected', 'text-bg-danger'],
        };
    }

    private function statusFilter(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw AdministratorPaymentException::validation(
                'Payment status filter is invalid.',
            );
        }

        $status = trim($value);

        if ($status === '') {
            return null;
        }

        if (!Payment::isStatus($status)) {
            throw AdministratorPaymentException::validation(
                'Payment status filter is invalid.',
            );
        }

        return $status;
    }

    private function auditUserAgent(Request $request): ?string
    {
        $value = $request->header('User-Agent');

        if ($value === null || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $normalized = preg_replace(
            '/[\x00-\x20\x7F]+/u',
            ' ',
            trim($value),
        );

        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        return mb_strcut($normalized, 0, 255, 'UTF-8');
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;

        if (
            !is_string($value)
            || preg_match('/^[1-9][0-9]*$/D', $value) !== 1
        ) {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return is_int($id) ? $id : null;
    }

    private function dateTime(DateTimeImmutable $value): string
    {
        return $value
            ->setTimezone($this->timezone)
            ->format('Y-m-d H:i');
    }

    private function optionalDateTime(?DateTimeImmutable $value): ?string
    {
        return $value === null ? null : $this->dateTime($value);
    }

    /** @param array<string, mixed> $data */
    private function page(
        string $view,
        array $data,
        int $statusCode = 200,
    ): Response {
        $html = $this->views->render(
            $view,
            [...$data, 'csrfToken' => $this->csrfTokens->token()],
            'layouts.admin',
        );

        return Response::html(
            $html,
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function notFound(): Response
    {
        return Response::text(
            'Not Found',
            404,
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
