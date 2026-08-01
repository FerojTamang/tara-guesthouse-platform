<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

/** Stores one safe post/redirect/get review-submission acknowledgement. */
final readonly class ReviewSubmissionFlash
{
    private const KEY = 'review_submission_success';

    public function __construct(private Session $session)
    {
    }

    public function store(): void
    {
        $this->session->set(self::KEY, true);
    }

    public function pull(): bool
    {
        return $this->session->pull(self::KEY, false) === true;
    }
}
