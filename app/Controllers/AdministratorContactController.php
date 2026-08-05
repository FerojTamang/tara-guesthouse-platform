<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\ContactException;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Requests\AdministratorContactReplyRequest;
use App\Requests\AdministratorContactStatusRequest;
use App\Services\ContactMessageServiceProvider;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use stdClass;

/** Provides authenticated contact-message pages, replies, and workflow APIs. */
final readonly class AdministratorContactController
{
    public function __construct(
        private ViewRenderer $views,
        private ContactMessageServiceProvider $services,
        private AdministratorSession $administratorSession,
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

            return $this->page('admin.contact-messages.index', [
                'title' => 'Contact messages | Tara Guest House',
                'pageTitle' => 'Contact messages',
                'activeNavigation' => 'contact-messages',
                'contacts' => $contacts,
                'search' => $search ?? '',
                'error' => null,
            ]);
        } catch (ContactException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->page('admin.contact-messages.index', [
                    'title' => 'Contact messages | Tara Guest House',
                    'pageTitle' => 'Contact messages',
                    'activeNavigation' => 'contact-messages',
                    'contacts' => [],
                    'search' => $search ?? '',
                    'error' => $exception->getMessage(),
                ], 422);
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
            $replies = $this->services->service()->replies($id);

            return $this->page('admin.contact-messages.show', [
                'title' => 'Contact message | Tara Guest House',
                'pageTitle' => 'Contact message',
                'activeNavigation' => 'contact-messages',
                'contact' => $this->detailData($contact),
                'replies' => array_map($this->replyData(...), $replies),
            ]);
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
            return Response::json([
                'success' => true,
                'data' => array_map(
                    $this->apiData(...),
                    $this->services->service()->list($search),
                ),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ContactException $exception) {
            return $exception->isValidationFailure()
                ? $this->validationError('search', $exception->getMessage())
                : $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiShow(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The contact message was not found.', 404);
        }

        try {
            return Response::json([
                'success' => true,
                'data' => $this->apiData($this->services->service()->get($id)),
                'replies' => array_map(
                    $this->apiReplyData(...),
                    $this->services->service()->replies($id),
                ),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ContactException $exception) {
            return $this->contactError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiReply(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The contact message was not found.', 404);
        }

        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (array_keys($payload) !== ['reply_body']) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $reply = $this->services->service()->reply(
                $id,
                AdministratorContactReplyRequest::fromInput($payload['reply_body']),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => 'Reply sent to the Guest.',
                'data' => $this->apiReplyData($reply),
            ], 201, ['Cache-Control' => 'no-store']);
        } catch (ContactException $exception) {
            return $this->contactError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiTransition(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The contact message was not found.', 404);
        }

        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (array_keys($payload) !== ['status']) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $contact = $this->services->service()->transitionStatus(
                $id,
                AdministratorContactStatusRequest::fromInput($payload['status']),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => $contact->isClosed()
                    ? 'Contact message closed.'
                    : 'Contact message reopened.',
                'data' => $this->apiData($contact),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ContactException $exception) {
            return $this->contactError($exception);
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
            'created_at' => $this->dateTime($contact->createdAt()),
            'updated_at' => $this->dateTime($contact->updatedAt()),
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
            'statusLabel' => $this->statusLabel($contact->status()),
            'statusBadge' => $this->statusBadge($contact->status()),
            'createdAt' => $contact->createdAt()
                ->setTimezone($this->timezone)
                ->format('Y-m-d H:i'),
        ];
    }

    /** @return array<string, int|string|bool|null> */
    private function detailData(ContactMessage $contact): array
    {
        return [
            ...$this->apiData($contact),
            'statusLabel' => $this->statusLabel($contact->status()),
            'statusBadge' => $this->statusBadge($contact->status()),
            'canReply' => !$contact->isClosed(),
            'createdAtDisplay' => $contact->createdAt()
                ->setTimezone($this->timezone)
                ->format('Y-m-d H:i:s T'),
        ];
    }

    /** @return array<string, int|string|null> */
    private function apiReplyData(ContactMessageReply $reply): array
    {
        return [
            'contact_reply_id' => $reply->id(),
            'contact_message_id' => $reply->contactMessageId(),
            'reply_body' => $reply->replyBody(),
            'sent_by' => $reply->sentBy(),
            'delivery_status' => $reply->deliveryStatus(),
            'sent_at' => $reply->sentAt() instanceof DateTimeImmutable
                ? $this->dateTime($reply->sentAt())
                : null,
            'failure_code' => $reply->failureCode(),
            'created_at' => $this->dateTime($reply->createdAt()),
        ];
    }

    /** @return array<string, int|string|null> */
    private function replyData(ContactMessageReply $reply): array
    {
        return [
            ...$this->apiReplyData($reply),
            'deliveryLabel' => match ($reply->deliveryStatus()) {
                ContactMessageReply::STATUS_SENT => 'Sent',
                ContactMessageReply::STATUS_FAILED => 'Failed',
                default => 'Pending',
            },
            'deliveryBadge' => match ($reply->deliveryStatus()) {
                ContactMessageReply::STATUS_SENT => 'text-bg-success',
                ContactMessageReply::STATUS_FAILED => 'text-bg-danger',
                default => 'text-bg-warning',
            },
            'createdAtDisplay' => $reply->createdAt()
                ->setTimezone($this->timezone)
                ->format('Y-m-d H:i:s T'),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            ContactMessage::STATUS_NEW => 'New',
            ContactMessage::STATUS_IN_PROGRESS => 'In progress',
            ContactMessage::STATUS_REPLIED => 'Replied',
            ContactMessage::STATUS_CLOSED => 'Closed',
            default => 'Unknown',
        };
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            ContactMessage::STATUS_NEW => 'text-bg-primary',
            ContactMessage::STATUS_IN_PROGRESS => 'text-bg-warning',
            ContactMessage::STATUS_REPLIED => 'text-bg-success',
            ContactMessage::STATUS_CLOSED => 'text-bg-secondary',
            default => 'text-bg-dark',
        };
    }

    /** @return array<string, mixed>|Response */
    private function jsonObject(Request $request): array|Response
    {
        $contentType = $request->header('Content-Type');

        if (
            $contentType === null
            || strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json'
        ) {
            return $this->jsonError('Content-Type must be application/json.', 415);
        }

        try {
            $decoded = json_decode($request->body(), false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->jsonError('Request body must contain valid JSON.', 400);
        }

        if (!$decoded instanceof stdClass) {
            return $this->jsonError('Request body must contain a JSON object.', 400);
        }

        $payload = get_object_vars($decoded);

        return array_is_list($payload)
            ? $this->jsonError('Request body must contain a JSON object.', 400)
            : $payload;
    }

    private function auditUserAgent(Request $request): ?string
    {
        $value = $request->header('User-Agent');

        if ($value === null || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $normalized = preg_replace('/[\x00-\x20\x7F]+/u', ' ', trim($value));

        return is_string($normalized) && $normalized !== ''
            ? mb_strcut($normalized, 0, 255, 'UTF-8')
            : null;
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

    private function dateTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone($this->timezone)->format(DATE_ATOM);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data, int $statusCode = 200): Response
    {
        return Response::html(
            $this->views->render($view, [
                ...$data,
                'csrfToken' => $this->csrfTokens->token(),
            ], 'layouts.admin'),
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function contactError(ContactException $exception): Response
    {
        if ($exception->isValidationFailure()) {
            return $this->validationError('contact', $exception->getMessage());
        }

        if ($exception->isNotFound()) {
            return $this->jsonError($exception->getMessage(), 404);
        }

        if ($exception->isConflict()) {
            return $this->jsonError($exception->getMessage(), 409);
        }

        if ($exception->isDeliveryFailure()) {
            return $this->jsonError($exception->getMessage(), 502);
        }

        return $this->jsonError('Internal Server Error.', 500);
    }

    private function validationError(string $field, string $message): Response
    {
        return Response::json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => [$field => [$message]],
        ], 422, ['Cache-Control' => 'no-store']);
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, ['Cache-Control' => 'no-store']);
    }

    private function failure(): Response
    {
        return Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
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
