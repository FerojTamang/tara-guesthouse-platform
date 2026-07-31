<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\ContactException;
use App\Models\ContactMessage;
use App\Services\ContactMessageServiceProvider;
use DateTimeZone;

/** Provides authenticated read-only contact-message pages and APIs. */
final readonly class AdministratorContactController
{
    public function __construct(
        private ViewRenderer $views,
        private ContactMessageServiceProvider $services,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        $search = $this->queryString($request->query('q'));

        try {
            $contacts = array_map(
                $this->listData(...),
                $this->services->service()->list($search),
            );

            return $this->page(
                'admin.contact-messages.index',
                [
                    'title' => 'Contact messages | Tara Guest House',
                    'pageTitle' => 'Contact messages',
                    'activeNavigation' => 'contact-messages',
                    'contacts' => $contacts,
                    'search' => $search ?? '',
                    'error' => null,
                ],
            );
        } catch (ContactException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->page(
                    'admin.contact-messages.index',
                    [
                        'title' => 'Contact messages | Tara Guest House',
                        'pageTitle' => 'Contact messages',
                        'activeNavigation' => 'contact-messages',
                        'contacts' => [],
                        'search' => $search ?? '',
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
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->notFound();
        }

        try {
            $contact = $this->services->service()->get($id);

            return $this->page(
                'admin.contact-messages.show',
                [
                    'title' => 'Contact message | Tara Guest House',
                    'pageTitle' => 'Contact message',
                    'activeNavigation' => 'contact-messages',
                    'contact' => $this->detailData($contact),
                ],
            );
        } catch (ContactException $exception) {
            return $exception->isNotFound()
                ? $this->notFound()
                : $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function apiIndex(Request $request, array $parameters): Response
    {
        $search = $this->queryString($request->query('q'));

        try {
            return Response::json(
                [
                    'success' => true,
                    'data' => array_map(
                        $this->apiData(...),
                        $this->services->service()->list($search),
                    ),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ContactException $exception) {
            return $exception->isValidationFailure()
                ? $this->jsonError($exception->getMessage(), 422)
                : $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiShow(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError(
                'The contact message was not found.',
                404,
            );
        }

        try {
            return Response::json(
                [
                    'success' => true,
                    'data' => $this->apiData(
                        $this->services->service()->get($id),
                    ),
                ],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ContactException $exception) {
            return $exception->isNotFound()
                ? $this->jsonError($exception->getMessage(), 404)
                : $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @return array<string, int|string|null> */
    private function apiData(ContactMessage $contact): array
    {
        return [
            'contact_message_id' => $contact->id(),
            'name' => $contact->name(),
            'email' => $contact->email(),
            'phone' => $contact->phone(),
            'subject' => $contact->subject(),
            'message' => $contact->message(),
            'status' => $contact->status(),
            'created_at' => $contact->createdAt()
                ->setTimezone($this->timezone)
                ->format(DATE_ATOM),
            'updated_at' => $contact->updatedAt()
                ->setTimezone($this->timezone)
                ->format(DATE_ATOM),
        ];
    }

    /** @return array<string, int|string> */
    private function listData(ContactMessage $contact): array
    {
        return [
            'id' => $contact->id(),
            'name' => $contact->name(),
            'email' => $contact->email(),
            'subject' => $contact->subject(),
            'statusLabel' => $contact->status() ?? 'Policy pending',
            'createdAt' => $contact->createdAt()
                ->setTimezone($this->timezone)
                ->format('Y-m-d H:i'),
        ];
    }

    /** @return array<string, int|string|null> */
    private function detailData(ContactMessage $contact): array
    {
        return [
            ...$this->apiData($contact),
            'statusLabel' => $contact->status() ?? 'Policy pending',
            'createdAtDisplay' => $contact->createdAt()
                ->setTimezone($this->timezone)
                ->format('Y-m-d H:i:s T'),
        ];
    }

    private function queryString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($id) && $id > 0 ? $id : null;
    }

    /** @param array<string, mixed> $data */
    private function page(
        string $view,
        array $data,
        int $statusCode = 200,
    ): Response {
        return Response::html(
            $this->views->render(
                $view,
                [
                    ...$data,
                    'csrfToken' => $this->csrfTokens->token(),
                ],
                'layouts.admin',
            ),
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
            ['success' => false, 'message' => $message],
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }
}
