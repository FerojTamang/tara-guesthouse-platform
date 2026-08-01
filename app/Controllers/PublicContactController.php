<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\ContactException;
use App\Requests\ContactMessageRequest;
use App\Services\ContactMessageServiceProvider;
use App\Services\ContactSubmissionFlash;
use JsonException;
use Throwable;

/** Handles the public contact page, PRG submission, and JSON endpoint. */
final readonly class PublicContactController
{
    public function __construct(
        private ViewRenderer $views,
        private ContactMessageServiceProvider $services,
        private ContactSubmissionFlash $flash,
        private CsrfTokenManager $csrfTokens,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function page(Request $request, array $parameters): Response
    {
        return $this->formResponse(
            $this->emptyValues(),
            null,
            $this->flash->pull(),
        );
    }

    /** @param array<string, string> $parameters */
    public function submit(Request $request, array $parameters): Response
    {
        $input = $request->input();
        $values = $this->values($input);

        if (!$this->hasApprovedWebKeys($input)) {
            return $this->formResponse(
                $values,
                'The contact form contains unsupported fields.',
                false,
                422,
            );
        }

        try {
            $this->services->service()->submit(
                ContactMessageRequest::fromInput(
                    $input['name'] ?? null,
                    $input['email'] ?? null,
                    $input['phone'] ?? null,
                    $input['subject'] ?? null,
                    $input['message'] ?? null,
                ),
                $request->remoteAddress(),
                $request->header('User-Agent'),
            );
            $this->flash->store();

            return Response::redirect(
                '/contact',
                303,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ContactException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->formResponse(
                    $values,
                    $exception->getMessage(),
                    false,
                    422,
                );
            }

            return $this->failure();
        } catch (Throwable) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCreate(Request $request, array $parameters): Response
    {
        if (!$this->usesJsonContentType($request)) {
            return $this->jsonError(
                'Content-Type must be application/json.',
                415,
            );
        }

        try {
            $payload = json_decode(
                $request->body(),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return $this->jsonError(
                'Request body must contain valid JSON.',
                400,
            );
        }

        if (!is_array($payload) || array_is_list($payload)) {
            return $this->jsonError(
                'Request body must contain a JSON object.',
                400,
            );
        }

        if (!$this->hasApprovedJsonKeys($payload)) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        try {
            $contact = $this->services->service()->submit(
                ContactMessageRequest::fromInput(
                    $payload['name'],
                    $payload['email'],
                    $payload['phone'] ?? null,
                    $payload['subject'],
                    $payload['message'],
                ),
                $request->remoteAddress(),
                $request->header('User-Agent'),
            );

            return Response::json(
                [
                    'success' => true,
                    'message' => 'Message received.',
                    'data' => [
                        'contact_message_id' => $contact->id(),
                    ],
                ],
                201,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ContactException $exception) {
            if ($exception->isValidationFailure()) {
                return Response::json(
                    [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => [
                            'contact' => [$exception->getMessage()],
                        ],
                    ],
                    422,
                    ['Cache-Control' => 'no-store'],
                );
            }

            return $this->jsonError('Internal Server Error.', 500);
        } catch (Throwable) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasApprovedJsonKeys(array $payload): bool
    {
        $required = ['name', 'email', 'subject', 'message'];
        $allowed = [...$required, 'phone'];

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

    /** @param array<string, mixed> $input */
    private function hasApprovedWebKeys(array $input): bool
    {
        $required = ['name', 'email', 'subject', 'message'];
        $allowed = [...$required, 'phone', '_csrf'];

        foreach ($required as $key) {
            if (!array_key_exists($key, $input)) {
                return false;
            }
        }

        foreach (array_keys($input) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $source
     * @return array{name: string, email: string, phone: string, subject: string, message: string}
     */
    private function values(array $source): array
    {
        return [
            'name' => $this->display($source['name'] ?? ''),
            'email' => $this->display($source['email'] ?? ''),
            'phone' => $this->display($source['phone'] ?? ''),
            'subject' => $this->display($source['subject'] ?? ''),
            'message' => $this->display($source['message'] ?? '', false),
        ];
    }

    /** @return array{name: string, email: string, phone: string, subject: string, message: string} */
    private function emptyValues(): array
    {
        return [
            'name' => '',
            'email' => '',
            'phone' => '',
            'subject' => '',
            'message' => '',
        ];
    }

    private function display(mixed $value, bool $trim = true): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        return $trim ? trim($value) : trim(str_replace(["\r\n", "\r"], "\n", $value));
    }

    private function formResponse(
        array $values,
        ?string $error,
        bool $success,
        int $statusCode = 200,
    ): Response {
        $html = $this->views->render(
            'public.contact.index',
            [
                'title' => 'Contact | Tara Guest House',
                'metaDescription' => 'Send a private inquiry to Tara Guest House.',
                'csrfToken' => $this->csrfTokens->token(),
                'values' => $values,
                'error' => $error,
                'success' => $success,
            ],
            'layouts.base',
        );

        return Response::html(
            $html,
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        return $contentType !== null
            && strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/json';
    }

    private function jsonError(string $message, int $statusCode): Response
    {
        return Response::json(
            ['success' => false, 'message' => $message],
            $statusCode,
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
