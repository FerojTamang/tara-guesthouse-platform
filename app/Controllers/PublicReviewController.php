<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CsrfTokenManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\ViewRenderer;
use App\Exceptions\ReviewException;
use App\Requests\ReviewSubmissionRequest;
use App\Services\PublicReviewServiceProvider;
use App\Services\ReviewSubmissionFlash;
use JsonException;
use Throwable;

/** Handles public approved reviews and secure invitation submissions. */
final readonly class PublicReviewController
{
    public function __construct(
        private ViewRenderer $views,
        private PublicReviewServiceProvider $services,
        private ReviewSubmissionFlash $flash,
        private CsrfTokenManager $csrfTokens,
    ) {
    }

    /** @param array<string, string> $parameters */
    public function page(Request $request, array $parameters): Response
    {
        try {
            $html = $this->views->render(
                'public.reviews.index',
                [
                    'title' => 'Guest Reviews | Tara Guest House',
                    'metaDescription' => 'Read approved verified-stay reviews from Tara Guest House guests.',
                    'reviews' => $this->services->service()->approved(),
                    'success' => $this->flash->pull(),
                ],
                'layouts.base',
            );

            return Response::html($html, 200, ['Cache-Control' => 'no-store']);
        } catch (ReviewException) {
            return $this->failure();
        } catch (Throwable) {
            return $this->failure();
        }
    }

    /** @param array<string, string> $parameters */
    public function invitation(Request $request, array $parameters): Response
    {
        $token = $parameters['token'] ?? '';

        try {
            $this->services->service()->assertInvitationAvailable($token);

            return $this->submissionForm($token, $this->emptyValues(), null);
        } catch (ReviewException $exception) {
            return $exception->isUnavailable() ? $this->notFound() : $this->failure(true);
        } catch (Throwable) {
            return $this->failure(true);
        }
    }

    /** @param array<string, string> $parameters */
    public function submit(Request $request, array $parameters): Response
    {
        $token = $parameters['token'] ?? '';
        $input = $request->input();
        $values = $this->values($input);

        try {
            $this->services->service()->assertInvitationAvailable($token);

            if (!$this->hasApprovedWebKeys($input)) {
                return $this->submissionForm(
                    $token,
                    $values,
                    'The review form contains unsupported fields.',
                    422,
                );
            }

            $this->services->service()->submit(
                $token,
                ReviewSubmissionRequest::fromInput(
                    $input['rating'] ?? null,
                    $input['title'] ?? null,
                    $input['comment'] ?? null,
                ),
                $request->remoteAddress(),
                $request->header('User-Agent'),
            );
            $this->flash->store();

            return Response::redirect('/reviews', 303, $this->tokenHeaders());
        } catch (ReviewException $exception) {
            if ($exception->isValidationFailure()) {
                $message = $exception->getMessage();

                return $this->submissionForm(
                    $token,
                    $values,
                    $message,
                    422,
                    $this->errorField($message),
                );
            }

            return $exception->isUnavailable() ? $this->notFound() : $this->failure(true);
        } catch (Throwable) {
            return $this->failure(true);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiIndex(Request $request, array $parameters): Response
    {
        try {
            return Response::json(
                ['success' => true, 'data' => $this->services->service()->approved()],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (ReviewException) {
            return $this->jsonError('Internal Server Error.', 500);
        } catch (Throwable) {
            return $this->jsonError('Internal Server Error.', 500);
        }
    }

    /** @param array<string, string> $parameters */
    public function apiCreate(Request $request, array $parameters): Response
    {
        if (!$this->usesJsonContentType($request)) {
            return $this->jsonError('Content-Type must be application/json.', 415, true);
        }

        try {
            $payload = json_decode($request->body(), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->jsonError('Request body must contain valid JSON.', 400, true);
        }

        if (!is_array($payload) || array_is_list($payload)) {
            return $this->jsonError('Request body must contain a JSON object.', 400, true);
        }

        if (!$this->hasApprovedJsonKeys($payload)) {
            return $this->jsonError('Request payload is invalid.', 422, true);
        }

        $token = $payload['review_token'];

        if (!is_string($token)) {
            return $this->jsonError('Review invitation is unavailable.', 404, true);
        }

        try {
            $this->services->service()->assertInvitationAvailable($token);
            $review = $this->services->service()->submit(
                $token,
                ReviewSubmissionRequest::fromInput(
                    $payload['rating'],
                    $payload['title'] ?? null,
                    $payload['comment'],
                ),
                $request->remoteAddress(),
                $request->header('User-Agent'),
            );

            return Response::json(
                [
                    'success' => true,
                    'message' => 'Review submitted for moderation.',
                    'data' => [
                        'review_id' => $review->id(),
                        'review_status' => $review->status(),
                    ],
                ],
                201,
                $this->tokenHeaders(),
            );
        } catch (ReviewException $exception) {
            if ($exception->isValidationFailure()) {
                return Response::json(
                    [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => ['review' => [$exception->getMessage()]],
                    ],
                    422,
                    $this->tokenHeaders(),
                );
            }

            if ($exception->isUnavailable()) {
                return $this->jsonError('Review invitation is unavailable.', 404, true);
            }

            return $this->jsonError('Internal Server Error.', 500, true);
        } catch (Throwable) {
            return $this->jsonError('Internal Server Error.', 500, true);
        }
    }

    /** @param array<string, mixed> $payload */
    private function hasApprovedJsonKeys(array $payload): bool
    {
        $required = ['review_token', 'rating', 'comment'];
        $allowed = [...$required, 'title'];

        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                return false;
            }
        }

        foreach (array_keys($payload) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $input */
    private function hasApprovedWebKeys(array $input): bool
    {
        $required = ['rating', 'comment'];
        $allowed = [...$required, 'title', '_csrf'];

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
     * @return array{rating: string, title: string, comment: string}
     */
    private function values(array $source): array
    {
        return [
            'rating' => $this->display($source['rating'] ?? ''),
            'title' => $this->display($source['title'] ?? ''),
            'comment' => $this->display($source['comment'] ?? '', false),
        ];
    }

    /** @return array{rating: string, title: string, comment: string} */
    private function emptyValues(): array
    {
        return ['rating' => '', 'title' => '', 'comment' => ''];
    }

    private function display(mixed $value, bool $trim = true): string
    {
        if ((!is_string($value) && !is_int($value)) || is_bool($value)) {
            return '';
        }

        $display = (string) $value;

        if (!mb_check_encoding($display, 'UTF-8')) {
            return '';
        }

        return $trim ? trim($display) : trim(str_replace(["\r\n", "\r"], "\n", $display));
    }

    private function errorField(string $message): ?string
    {
        return match (true) {
            str_starts_with($message, 'Rating') => 'review-rating',
            str_starts_with($message, 'Title') => 'review-title',
            str_starts_with($message, 'Comment') => 'review-comment',
            default => null,
        };
    }

    /** @param array{rating: string, title: string, comment: string} $values */
    private function submissionForm(
        string $token,
        array $values,
        ?string $error,
        int $statusCode = 200,
        ?string $errorField = null,
    ): Response {
        $html = $this->views->render(
            'public.reviews.submit',
            [
                'title' => 'Submit Your Review | Tara Guest House',
                'csrfToken' => $this->csrfTokens->token(),
                'reviewToken' => $token,
                'values' => $values,
                'error' => $error,
                'errorField' => $errorField,
                'showPublicNavigation' => false,
            ],
            'layouts.base',
        );

        return Response::html($html, $statusCode, $this->tokenHeaders());
    }

    private function usesJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        return $contentType !== null
            && strtolower(trim(explode(';', $contentType, 2)[0])) === 'application/json';
    }

    private function notFound(): Response
    {
        return Response::text('Not Found', 404, $this->tokenHeaders());
    }

    private function jsonError(string $message, int $statusCode, bool $tokenResponse = false): Response
    {
        return Response::json(
            ['success' => false, 'message' => $message],
            $statusCode,
            $tokenResponse ? $this->tokenHeaders() : ['Cache-Control' => 'no-store'],
        );
    }

    private function failure(bool $tokenResponse = false): Response
    {
        return Response::text(
            'Internal Server Error',
            500,
            $tokenResponse ? $this->tokenHeaders() : ['Cache-Control' => 'no-store'],
        );
    }

    /** @return array<string, string> */
    private function tokenHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
