<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\PaymentMethodException;
use App\Models\PaymentMethod;
use App\Requests\PaymentMethodQrUploadRequest;
use App\Requests\PaymentMethodUpsertRequest;
use App\Services\PaymentMethodFlashMessages;
use App\Services\PaymentMethodManagementServiceProvider;

/**
 * Handles authenticated Administrator payment-method pages and mutations.
 */
final readonly class AdministratorPaymentMethodController
{
    public function __construct(
        private ViewRenderer $views,
        private PaymentMethodManagementServiceProvider $services,
        private CsrfTokenManager $csrfTokens,
        private PaymentMethodFlashMessages $flashMessages,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        $methods = array_map(
            $this->methodData(...),
            $this->services->service()->listAll(),
        );
        $html = $this->views->render(
            'admin.payment-methods.index',
            [
                'title' => 'Payment methods | Tara Guest House',
                'pageTitle' => 'Payment methods',
                'activeNavigation' => 'payment-methods',
                'csrfToken' => $this->csrfTokens->token(),
                'methods' => $methods,
                'notice' => $this->flashMessages->pullSuccess(),
            ],
            'layouts.admin',
        );

        return Response::html($html, 200, ['Cache-Control' => 'no-store']);
    }

    /** @param array<string, string> $parameters */
    public function createForm(Request $request, array $parameters): Response
    {
        return $this->formResponse(
            'Create payment method',
            '/admin/payment-methods',
            'Create payment method',
            $this->emptyForm(),
        );
    }

    /** @param array<string, string> $parameters */
    public function create(Request $request, array $parameters): Response
    {
        $values = $this->inputValues($request);

        try {
            $validated = $this->validatedRequest($request);
            $qrUpload = PaymentMethodQrUploadRequest::fromFile($request->file('qr_image'));
        } catch (PaymentMethodException $exception) {
            if (!$exception->isValidationFailure()) {
                throw $exception;
            }

            return $this->formResponse(
                'Create payment method',
                '/admin/payment-methods',
                'Create payment method',
                $values,
                $exception->getMessage(),
                422,
            );
        }

        $this->services->service()->create($validated, $qrUpload);
        $this->flashMessages->success('Payment method created successfully.');

        return $this->redirectToIndex();
    }

    /** @param array<string, string> $parameters */
    public function editForm(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            $method = $this->services->service()->get($id);
        } catch (PaymentMethodException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        return $this->formResponse(
            'Edit payment method',
            '/admin/payment-methods/' . $method->id(),
            'Save payment method',
            $this->storedValues($method),
            qrConfigured: $method->qrImagePath() !== null,
            qrImageUrl: $this->qrImageUrl($method->qrImagePath()),
        );
    }

    /** @param array<string, string> $parameters */
    public function update(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            $current = $this->services->service()->get($id);
        } catch (PaymentMethodException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        $values = $this->inputValues($request);

        try {
            $validated = $this->validatedRequest($request);
            $qrUpload = PaymentMethodQrUploadRequest::fromFile($request->file('qr_image'));
            $removeQr = $this->scalarInput($request->input('remove_qr_image')) === '1';
        } catch (PaymentMethodException $exception) {
            if (!$exception->isValidationFailure()) {
                throw $exception;
            }

            return $this->formResponse(
                'Edit payment method',
                '/admin/payment-methods/' . $id,
                'Save payment method',
                $values,
                $exception->getMessage(),
                422,
                $current->qrImagePath() !== null,
                $this->qrImageUrl($current->qrImagePath()),
            );
        }

        try {
            $this->services->service()->update($id, $validated, $qrUpload, $removeQr);
        } catch (PaymentMethodException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        $this->flashMessages->success('Payment method updated successfully.');

        return $this->redirectToIndex();
    }

    /** @param array<string, string> $parameters */
    public function activate(Request $request, array $parameters): Response
    {
        return $this->changeActiveState($parameters, true);
    }

    /** @param array<string, string> $parameters */
    public function deactivate(Request $request, array $parameters): Response
    {
        return $this->changeActiveState($parameters, false);
    }

    /** @param array<string, string> $parameters */
    private function changeActiveState(array $parameters, bool $active): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            if ($active) {
                $this->services->service()->activate($id);
            } else {
                $this->services->service()->deactivate($id);
            }
        } catch (PaymentMethodException $exception) {
            if ($exception->isNotFound()) {
                return $this->notFound();
            }

            throw $exception;
        }

        $this->flashMessages->success($active
            ? 'Payment method activated successfully.'
            : 'Payment method deactivated successfully.');

        return $this->redirectToIndex();
    }

    private function validatedRequest(Request $request): PaymentMethodUpsertRequest
    {
        return PaymentMethodUpsertRequest::fromInput(
            $request->input('name'),
            $request->input('instructions'),
            $request->input('account_reference'),
            $request->input('requires_proof'),
            $request->input('active'),
        );
    }

    /** @return array<string, string> */
    private function inputValues(Request $request): array
    {
        return [
            'name' => $this->scalarInput($request->input('name')),
            'instructions' => $this->scalarInput($request->input('instructions')),
            'accountReference' => $this->scalarInput($request->input('account_reference')),
            'requiresProof' => $this->scalarInput($request->input('requires_proof')),
            'active' => $this->scalarInput($request->input('active')),
        ];
    }

    private function scalarInput(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    /** @return array<string, string> */
    private function emptyForm(): array
    {
        return [
            'name' => '',
            'instructions' => '',
            'accountReference' => '',
            'requiresProof' => '1',
            'active' => '1',
        ];
    }

    /** @return array<string, string> */
    private function storedValues(PaymentMethod $method): array
    {
        return [
            'name' => $method->name(),
            'instructions' => $method->instructions() ?? '',
            'accountReference' => $method->accountReference() ?? '',
            'requiresProof' => $method->requiresProof() ? '1' : '0',
            'active' => $method->isActive() ? '1' : '0',
        ];
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return is_int($id) ? $id : null;
    }

    /**
     * @param array<string, string> $values
     */
    private function formResponse(
        string $heading,
        string $action,
        string $submitLabel,
        array $values,
        ?string $error = null,
        int $statusCode = 200,
        bool $qrConfigured = false,
        ?string $qrImageUrl = null,
    ): Response {
        $html = $this->views->render(
            'admin.payment-methods.form',
            [
                'title' => $heading . ' | Tara Guest House',
                'pageTitle' => $heading,
                'activeNavigation' => 'payment-methods',
                'csrfToken' => $this->csrfTokens->token(),
                'heading' => $heading,
                'action' => $action,
                'submitLabel' => $submitLabel,
                'values' => $values,
                'error' => $error,
                'qrConfigured' => $qrConfigured,
                'qrImageUrl' => $qrImageUrl,
            ],
            'layouts.admin',
        );

        return Response::html($html, $statusCode, ['Cache-Control' => 'no-store']);
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

    private function redirectToIndex(): Response
    {
        return Response::redirect(
            '/admin/payment-methods',
            303,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     accountReference: ?string,
     *     requiresProof: bool,
     *     qrConfigured: bool,
     *     active: bool
     * }
     */
    private function methodData(PaymentMethod $method): array
    {
        return [
            'id' => $method->id(),
            'name' => $method->name(),
            'accountReference' => $method->accountReference(),
            'requiresProof' => $method->requiresProof(),
            'qrConfigured' => $method->qrImagePath() !== null,
            'active' => $method->isActive(),
        ];
    }
}
