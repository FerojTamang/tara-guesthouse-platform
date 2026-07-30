<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\AdministratorInvoiceException;
use App\Models\AdministratorInvoiceRecord;
use App\Models\AdministratorInvoiceSummary;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Requests\AdministratorInvoiceIssueRequest;
use App\Services\AdministratorInvoiceServiceProvider;
use App\Services\InvoiceIssueServiceProvider;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Renders protected invoice pages and coordinates idempotent invoice issue.
 */
final readonly class AdministratorInvoiceController
{
    public function __construct(
        private ViewRenderer $views,
        private AdministratorInvoiceServiceProvider $services,
        private InvoiceIssueServiceProvider $issueServices,
        private AdministratorSession $administratorSession,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        try {
            return $this->page(
                'admin.invoices.index',
                [
                    'title' => 'Invoices | Tara Guest House',
                    'pageTitle' => 'Invoices',
                    'activeNavigation' => 'invoices',
                    'invoices' => array_map(
                        $this->summaryData(...),
                        $this->services->service()->list(),
                    ),
                ],
            );
        } catch (AdministratorInvoiceException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $invoiceId = $this->routeId($parameters, 'id');

        if ($invoiceId === null) {
            return $this->notFound();
        }

        try {
            $record = $this->services->service()->detail($invoiceId);

            if (!$record instanceof AdministratorInvoiceRecord) {
                return $this->notFound();
            }

            return $this->page(
                'admin.invoices.show',
                [
                    'title' => $record->invoice()->invoiceNumber()
                        . ' | Tara Guest House',
                    'pageTitle' => 'Invoice detail',
                    'activeNavigation' => 'invoices',
                    'invoice' => $this->detailData($record),
                ],
            );
        } catch (AdministratorInvoiceException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function print(Request $request, array $parameters): Response
    {
        $invoiceId = $this->routeId($parameters, 'id');

        if ($invoiceId === null) {
            return $this->notFound();
        }

        try {
            $record = $this->services->service()->detail($invoiceId);

            if (!$record instanceof AdministratorInvoiceRecord) {
                return $this->notFound();
            }

            $html = $this->views->render(
                'admin.invoices.print',
                [
                    'title' => $record->invoice()->invoiceNumber(),
                    'invoice' => $this->detailData($record),
                ],
            );

            return Response::html(
                $html,
                200,
                [
                    'Cache-Control' => 'no-store',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'Referrer-Policy' => 'no-referrer',
                ],
            );
        } catch (AdministratorInvoiceException) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function issue(Request $request, array $parameters): Response
    {
        $bookingId = $this->routeId($parameters, 'bookingId');

        if ($bookingId === null) {
            return $this->jsonError('The booking was not found.', 404);
        }

        if (!$this->usesJsonContentType($request)) {
            return $this->jsonError(
                'Content-Type must be application/json.',
                415,
            );
        }

        try {
            $payload = json_decode(
                $request->body(),
                false,
                8,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->jsonError(
                'Request body must contain valid JSON.',
                400,
            );
        }

        if (!$payload instanceof \stdClass) {
            return $this->jsonError(
                'Request body must contain a JSON object.',
                400,
            );
        }

        try {
            AdministratorInvoiceIssueRequest::fromPayload(
                get_object_vars($payload),
            );
        } catch (AdministratorInvoiceException $exception) {
            return Response::json(
                [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'invoice' => [$exception->getMessage()],
                    ],
                ],
                422,
                ['Cache-Control' => 'no-store'],
            );
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $result = $this->issueServices->service()->issue(
                $bookingId,
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );
            $invoice = $result->invoice();

            return Response::json(
                [
                    'success' => true,
                    'message' => $result->created()
                        ? 'Invoice issued.'
                        : 'The stable invoice already exists.',
                    'data' => [
                        'invoice_id' => $invoice->id(),
                        'booking_id' => $invoice->bookingId(),
                        'invoice_number' => $invoice->invoiceNumber(),
                        'created' => $result->created(),
                        'issued_at' => $invoice->issuedAt()
                            ->setTimezone($this->timezone)
                            ->format(DATE_ATOM),
                        'detail_url' => '/admin/invoices/' . $invoice->id(),
                        'print_url' => '/admin/invoices/' . $invoice->id() . '/print',
                    ],
                ],
                $result->created() ? 201 : 200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (AdministratorInvoiceException $exception) {
            if ($exception->isValidationFailure()) {
                return Response::json(
                    [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => [
                            'invoice' => [$exception->getMessage()],
                        ],
                    ],
                    422,
                    ['Cache-Control' => 'no-store'],
                );
            }

            if ($exception->isNotFound()) {
                return $this->jsonError($exception->getMessage(), 404);
            }

            if ($exception->isConflict()) {
                return $this->jsonError($exception->getMessage(), 409);
            }

            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     number: string,
     *     bookingId: int,
     *     bookingReference: string,
     *     guestName: string,
     *     total: string,
     *     issuedAt: string
     * }
     */
    private function summaryData(AdministratorInvoiceSummary $summary): array
    {
        $invoice = $summary->invoice();

        return [
            'id' => $invoice->id(),
            'number' => $invoice->invoiceNumber(),
            'bookingId' => $invoice->bookingId(),
            'bookingReference' => $summary->bookingReference(),
            'guestName' => $summary->guestFullName(),
            'total' => $invoice->currency() . ' ' . $invoice->totalAmount(),
            'issuedAt' => $this->dateTime($invoice->issuedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function detailData(AdministratorInvoiceRecord $record): array
    {
        $invoice = $record->invoice();

        return [
            'id' => $invoice->id(),
            'number' => $invoice->invoiceNumber(),
            'bookingId' => $invoice->bookingId(),
            'bookingReference' => $record->bookingReference(),
            'bookingStatus' => ucwords(
                str_replace('_', ' ', $record->bookingStatus()),
            ),
            'guestName' => $record->guestFullName(),
            'guestEmail' => $record->guestEmail(),
            'guestPhone' => $record->guestPhone(),
            'checkIn' => $record->checkIn()->format('Y-m-d'),
            'checkOut' => $record->checkOut()->format('Y-m-d'),
            'paymentId' => $record->paymentId(),
            'paymentMethod' => $record->paymentMethodName(),
            'transactionReference' => $record->transactionReference(),
            'verifiedAt' => $this->dateTime($record->verifiedAt()),
            'currency' => $invoice->currency(),
            'subtotal' => $invoice->subtotal(),
            'taxAmount' => $invoice->taxAmount(),
            'totalAmount' => $invoice->totalAmount(),
            'issuedAt' => $this->dateTime($invoice->issuedAt()),
            'createdBy' => $record->createdByName(),
            'items' => array_map(
                $this->itemData(...),
                $record->items(),
            ),
        ];
    }

    /** @return array{description: string, quantity: string, unitPrice: string, lineTotal: string} */
    private function itemData(InvoiceItem $item): array
    {
        return [
            'description' => $item->description(),
            'quantity' => $item->quantity(),
            'unitPrice' => $item->unitPrice(),
            'lineTotal' => $item->lineTotal(),
        ];
    }

    private function dateTime(DateTimeImmutable $date): string
    {
        return $date
            ->setTimezone($this->timezone)
            ->format('Y-m-d H:i');
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters, string $key): ?int
    {
        $value = $parameters[$key] ?? null;

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

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        if ($contentType === null) {
            return false;
        }

        return strtolower(trim(explode(';', $contentType, 2)[0]))
            === 'application/json';
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

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data, int $statusCode = 200): Response
    {
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

    private function jsonError(string $message, int $statusCode): Response
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
