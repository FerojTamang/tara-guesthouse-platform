<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\NotificationException;
use App\Models\Notification;
use App\Models\NotificationInboxPage;
use App\Services\AdministratorNotificationServiceProvider;
use DateTimeImmutable;
use DateTimeZone;

/** Provides the protected Administrator notification inbox and read actions. */
final readonly class AdministratorNotificationController
{
    public function __construct(
        private ViewRenderer $views,
        private AdministratorNotificationServiceProvider $services,
        private AdministratorSession $administratorSession,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return Response::redirect('/admin/login', 302, ['Cache-Control' => 'no-store']);
        }

        try {
            $inbox = $this->services->service()->inbox(
                $identity->userId(),
                $this->pageNumber($request),
            );

            return $this->page($inbox);
        } catch (NotificationException $exception) {
            return $exception->isValidationFailure()
                ? $this->pageError($exception->getMessage(), 422)
                : Response::text('Internal Server Error', 500, ['Cache-Control' => 'no-store']);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiIndex(Request $request, array $parameters): Response
    {
        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $inbox = $this->services->service()->inbox(
                $identity->userId(),
                $this->pageNumber($request),
            );

            return Response::json([
                'success' => true,
                'data' => array_map($this->apiData(...), $inbox->notifications()),
                'meta' => $this->metaData($inbox),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (NotificationException $exception) {
            return $exception->isValidationFailure()
                ? $this->jsonError($exception->getMessage(), 422)
                : $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiRead(Request $request, array $parameters): Response
    {
        $identity = $this->administratorSession->identity();
        $id = $this->routeId($parameters);

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        if ($id === null) {
            return $this->jsonError('The requested notification was not found.', 404);
        }

        try {
            $notification = $this->services->service()->markRead($identity->userId(), $id);

            return Response::json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'data' => $this->apiData($notification),
                'unread_count' => $this->services->service()->unreadCount($identity->userId()),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (NotificationException $exception) {
            if ($exception->isNotFound()) {
                return $this->jsonError($exception->getMessage(), 404);
            }

            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiReadAll(Request $request, array $parameters): Response
    {
        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $updated = $this->services->service()->markAllRead($identity->userId());

            return Response::json([
                'success' => true,
                'message' => 'Notifications marked as read.',
                'data' => [
                    'updated_count' => $updated,
                    'unread_count' => 0,
                ],
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (NotificationException) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    private function page(NotificationInboxPage $inbox): Response
    {
        return Response::html(
            $this->views->render(
                'admin.notifications.index',
                [
                    'title' => 'Notifications | Tara Guest House',
                    'pageTitle' => 'Notifications',
                    'activeNavigation' => 'notifications',
                    'csrfToken' => $this->csrfTokens->token(),
                    'notifications' => array_map($this->viewData(...), $inbox->notifications()),
                    'page' => $inbox->page(),
                    'totalPages' => $inbox->totalPages(),
                    'total' => $inbox->total(),
                    'unreadCount' => $inbox->unreadCount(),
                    'notificationUnreadCount' => $inbox->unreadCount(),
                    'error' => null,
                ],
                'layouts.admin',
            ),
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    private function pageError(string $message, int $statusCode): Response
    {
        return Response::html(
            $this->views->render(
                'admin.notifications.index',
                [
                    'title' => 'Notifications | Tara Guest House',
                    'pageTitle' => 'Notifications',
                    'activeNavigation' => 'notifications',
                    'csrfToken' => $this->csrfTokens->token(),
                    'notifications' => [],
                    'page' => 1,
                    'totalPages' => 1,
                    'total' => 0,
                    'unreadCount' => 0,
                    'notificationUnreadCount' => 0,
                    'error' => $message,
                ],
                'layouts.admin',
            ),
            $statusCode,
            ['Cache-Control' => 'no-store'],
        );
    }

    /** @return array<string, int|string|null|bool> */
    private function viewData(Notification $notification): array
    {
        return [
            'id' => $notification->id(),
            'type' => $notification->type(),
            'typeLabel' => ucwords(str_replace('_', ' ', $notification->type())),
            'title' => $notification->title(),
            'message' => $notification->message(),
            'relatedType' => $notification->relatedType(),
            'relatedId' => $notification->relatedId(),
            'isRead' => $notification->isRead(),
            'readAt' => $notification->readAt() instanceof DateTimeImmutable
                ? $this->displayDateTime($notification->readAt())
                : null,
            'createdAt' => $this->displayDateTime($notification->createdAt()),
        ];
    }

    /** @return array<string, int|string|null|bool> */
    private function apiData(Notification $notification): array
    {
        return [
            'notification_id' => $notification->id(),
            'type' => $notification->type(),
            'title' => $notification->title(),
            'message' => $notification->message(),
            'related_type' => $notification->relatedType(),
            'related_id' => $notification->relatedId(),
            'is_read' => $notification->isRead(),
            'read_at' => $notification->readAt() instanceof DateTimeImmutable
                ? $this->dateTime($notification->readAt())
                : null,
            'created_at' => $this->dateTime($notification->createdAt()),
        ];
    }

    /** @return array<string, int|bool> */
    private function metaData(NotificationInboxPage $inbox): array
    {
        return [
            'page' => $inbox->page(),
            'per_page' => $inbox->perPage(),
            'total' => $inbox->total(),
            'total_pages' => $inbox->totalPages(),
            'unread_count' => $inbox->unreadCount(),
            'has_previous' => $inbox->hasPrevious(),
            'has_next' => $inbox->hasNext(),
        ];
    }

    private function pageNumber(Request $request): int
    {
        $query = $request->query();

        if (!is_array($query) || array_diff(array_keys($query), ['page']) !== []) {
            throw NotificationException::invalidInput('Notification query is invalid.');
        }

        $value = $query['page'] ?? null;

        if ($value === null) {
            return 1;
        }

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw NotificationException::invalidInput('Notification page is invalid.');
        }

        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!is_int($page)) {
            throw NotificationException::invalidInput('Notification page is invalid.');
        }

        return $page;
    }

    /** @param array<string, string> $parameters */
    private function routeId(array $parameters): ?int
    {
        $value = $parameters['id'] ?? null;

        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }

    private function displayDateTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone($this->timezone)->format('Y-m-d H:i:s T');
    }

    private function dateTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone($this->timezone)->format(DATE_ATOM);
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
