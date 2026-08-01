<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\AdministratorSession;
use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\ReviewException;
use App\Models\AdministratorReviewRecord;
use App\Models\Review;
use App\Models\ReviewModerationPolicy;
use App\Requests\AdministratorReviewStatusRequest;
use App\Services\AdministratorReviewServiceProvider;
use App\Services\ReviewModerationPolicyProvider;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use stdClass;

/** Renders protected review moderation pages and exact Administrator review APIs. */
final readonly class AdministratorReviewController
{
    public function __construct(
        private ViewRenderer $views,
        private AdministratorReviewServiceProvider $services,
        private ReviewModerationPolicyProvider $policies,
        private AdministratorSession $administratorSession,
        private CsrfTokenManager $csrfTokens,
        private DateTimeZone $timezone,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function index(Request $request, array $parameters): Response
    {
        $status = $this->queryStatus($request->query('status'));

        try {
            $reviews = array_map(
                $this->listData(...),
                $this->services->service()->list($status),
            );

            return $this->page('admin.reviews.index', [
                'title' => 'Reviews | Tara Guest House',
                'pageTitle' => 'Reviews',
                'activeNavigation' => 'reviews',
                'reviews' => $reviews,
                'status' => $status ?? '',
                'statuses' => Review::STATUSES,
                'error' => null,
            ]);
        } catch (ReviewException $exception) {
            if ($exception->isValidationFailure()) {
                return $this->page('admin.reviews.index', [
                    'title' => 'Reviews | Tara Guest House',
                    'pageTitle' => 'Reviews',
                    'activeNavigation' => 'reviews',
                    'reviews' => [],
                    'status' => $status ?? '',
                    'statuses' => Review::STATUSES,
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
            $record = $this->services->service()->get($id);
            [$moderationEnabled, $maximumNoteCharacters] = $this->policyState();

            return $this->page('admin.reviews.show', [
                'title' => 'Review #' . $record->review()->id() . ' | Tara Guest House',
                'pageTitle' => 'Review detail',
                'activeNavigation' => 'reviews',
                'record' => $this->detailData($record),
                'moderationEnabled' => $moderationEnabled,
                'maximumNoteCharacters' => $maximumNoteCharacters,
            ]);
        } catch (ReviewException $exception) {
            return $exception->isNotFound()
                ? $this->notFound()
                : $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function apiIndex(Request $request, array $parameters): Response
    {
        $status = $this->queryStatus($request->query('status'));

        try {
            return Response::json([
                'success' => true,
                'data' => array_map(
                    $this->apiReviewData(...),
                    $this->services->service()->list($status),
                ),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ReviewException $exception) {
            return $exception->isValidationFailure()
                ? $this->validationError('status', $exception->getMessage())
                : $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiShow(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The requested review was not found.', 404);
        }

        try {
            return Response::json([
                'success' => true,
                'data' => $this->apiRecordData($this->services->service()->get($id)),
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ReviewException $exception) {
            return $this->reviewError($exception);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiTransition(Request $request, array $parameters): Response
    {
        $id = $this->routeId($parameters);

        if ($id === null) {
            return $this->jsonError('The requested review was not found.', 404);
        }

        $payload = $this->jsonObject($request);

        if ($payload instanceof Response) {
            return $payload;
        }

        if (!$this->validTransitionPayload($payload)) {
            return $this->jsonError('Request payload is invalid.', 422);
        }

        $identity = $this->administratorSession->identity();

        if ($identity === null) {
            return $this->jsonError('Authentication required.', 401);
        }

        try {
            $review = $this->services->service()->transition(
                $id,
                AdministratorReviewStatusRequest::fromInput(
                    $payload['review_status'],
                    $payload['moderation_note'] ?? null,
                    array_key_exists('moderation_note', $payload),
                    $this->policies->policy(),
                ),
                $identity->userId(),
                $request->remoteAddress(),
                $this->auditUserAgent($request),
            );

            return Response::json([
                'success' => true,
                'message' => $review->status() === Review::STATUS_APPROVED
                    ? 'Review approved.'
                    : 'Review rejected.',
                'data' => [
                    'review_id' => $review->id(),
                    'review_status' => $review->status(),
                    'moderated_by' => $review->moderatedBy(),
                    'moderated_at' => $review->moderatedAt() instanceof DateTimeImmutable
                        ? $this->dateTime($review->moderatedAt())
                        : null,
                    'moderation_note' => $review->moderationNote(),
                ],
            ], 200, ['Cache-Control' => 'no-store']);
        } catch (ReviewException $exception) {
            return $this->reviewError($exception);
        }
    }

    /** @return array<string, mixed> */
    private function listData(Review $review): array
    {
        return [
            'id' => $review->id(),
            'guestName' => $review->guestNameSnapshot(),
            'rating' => $review->rating(),
            'title' => $review->title(),
            'status' => $review->status(),
            'statusLabel' => $this->statusLabel($review->status()),
            'statusBadge' => $this->statusBadge($review->status()),
            'createdAt' => $this->displayDateTime($review->createdAt()),
            'updatedAt' => $this->displayDateTime($review->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function detailData(AdministratorReviewRecord $record): array
    {
        $review = $record->review();
        $booking = $record->booking();
        $invitation = $record->invitation();

        return [
            ...$this->listData($review),
            'comment' => $review->comment(),
            'bookingId' => $booking->id(),
            'bookingReference' => $booking->bookingReference(),
            'bookingStatus' => $booking->bookingStatus(),
            'guestEmail' => $booking->guestEmail(),
            'checkIn' => $booking->checkIn()->format('Y-m-d'),
            'checkOut' => $booking->checkOut()->format('Y-m-d'),
            'invitationId' => $invitation->id(),
            'invitationCreatedAt' => $this->displayDateTime($invitation->createdAt()),
            'invitationExpiresAt' => $this->displayDateTime($invitation->expiresAt()),
            'invitationUsedAt' => $invitation->usedAt() instanceof DateTimeImmutable
                ? $this->displayDateTime($invitation->usedAt())
                : null,
            'verifiedStay' => $record->isVerifiedStay(),
            'canModerate' => $record->canModerate(),
            'moderatedBy' => $review->moderatedBy(),
            'moderatedAt' => $review->moderatedAt() instanceof DateTimeImmutable
                ? $this->displayDateTime($review->moderatedAt())
                : null,
            'moderationNote' => $review->moderationNote(),
        ];
    }

    /** @return array<string, mixed> */
    private function apiReviewData(Review $review): array
    {
        return [
            'review_id' => $review->id(),
            'booking_id' => $review->bookingId(),
            'review_invitation_id' => $review->reviewInvitationId(),
            'guest_name' => $review->guestNameSnapshot(),
            'rating' => $review->rating(),
            'title' => $review->title(),
            'comment' => $review->comment(),
            'review_status' => $review->status(),
            'moderated_by' => $review->moderatedBy(),
            'moderated_at' => $review->moderatedAt() instanceof DateTimeImmutable
                ? $this->dateTime($review->moderatedAt())
                : null,
            'moderation_note' => $review->moderationNote(),
            'created_at' => $this->dateTime($review->createdAt()),
            'updated_at' => $this->dateTime($review->updatedAt()),
        ];
    }

    /** @return array<string, mixed> */
    private function apiRecordData(AdministratorReviewRecord $record): array
    {
        $booking = $record->booking();
        $invitation = $record->invitation();

        return [
            ...$this->apiReviewData($record->review()),
            'verified_stay' => $record->isVerifiedStay(),
            'booking' => [
                'booking_id' => $booking->id(),
                'booking_reference' => $booking->bookingReference(),
                'booking_status' => $booking->bookingStatus(),
                'guest_email' => $booking->guestEmail(),
                'check_in' => $booking->checkIn()->format('Y-m-d'),
                'check_out' => $booking->checkOut()->format('Y-m-d'),
            ],
            'invitation' => [
                'review_invitation_id' => $invitation->id(),
                'created_at' => $this->dateTime($invitation->createdAt()),
                'expires_at' => $this->dateTime($invitation->expiresAt()),
                'used_at' => $invitation->usedAt() instanceof DateTimeImmutable
                    ? $this->dateTime($invitation->usedAt())
                    : null,
            ],
        ];
    }

    /** @return array{bool, ?int} */
    private function policyState(): array
    {
        try {
            $policy = $this->policies->policy();

            return [true, $policy->maximumNoteCharacters()];
        } catch (ReviewException) {
            return [false, null];
        }
    }

    /** @return array<string, mixed>|Response */
    private function jsonObject(Request $request): array|Response
    {
        if (!$this->usesJsonContentType($request)) {
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

    /** @param array<string, mixed> $payload */
    private function validTransitionPayload(array $payload): bool
    {
        $keys = array_keys($payload);
        sort($keys, SORT_STRING);

        return in_array($keys, [
            ['review_status'],
            ['moderation_note', 'review_status'],
        ], true);
    }

    private function queryStatus(mixed $value): ?string
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

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        return $contentType !== null
            && strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/json';
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Review::STATUS_PENDING => 'Pending',
            Review::STATUS_APPROVED => 'Approved',
            Review::STATUS_REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            Review::STATUS_PENDING => 'text-bg-warning',
            Review::STATUS_APPROVED => 'text-bg-success',
            Review::STATUS_REJECTED => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }

    private function displayDateTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone($this->timezone)->format('Y-m-d H:i:s T');
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

    private function reviewError(ReviewException $exception): Response
    {
        if ($exception->isValidationFailure()) {
            return $this->validationError('review', $exception->getMessage());
        }

        if ($exception->isNotFound()) {
            return $this->jsonError($exception->getMessage(), 404);
        }

        if ($exception->isConflict()) {
            return $this->jsonError($exception->getMessage(), 409);
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
