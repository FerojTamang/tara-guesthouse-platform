<section aria-labelledby="review-detail-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Verified-stay moderation</p>
            <h1 id="review-detail-heading" class="h2 mb-2">Review #<?= $e($record['id']) ?></h1>
            <p class="text-secondary mb-0">
                Submitted <?= $e($record['createdAt']) ?> for booking
                <a href="/admin/bookings/<?= $e($record['bookingId']) ?>"><?= $e($record['bookingReference']) ?></a>.
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/reviews">Back to reviews</a>
    </div>

    <?php if (!$record['verifiedStay']): ?>
        <div class="alert alert-danger" role="alert">
            This review no longer has a valid checked-out booking and consumed invitation context.
            Moderation is blocked.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card admin-panel mb-4">
                <div class="card-header bg-transparent d-flex flex-wrap justify-content-between gap-2 align-items-center">
                    <h2 class="h5 mb-0">Guest review</h2>
                    <span class="badge <?= $e($record['statusBadge']) ?>"><?= $e($record['statusLabel']) ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-4">
                        <dt class="col-sm-4">Guest</dt>
                        <dd class="col-sm-8"><?= $e($record['guestName']) ?></dd>

                        <dt class="col-sm-4">Rating</dt>
                        <dd class="col-sm-8" aria-label="<?= $e($record['rating']) ?> out of 5">
                            <?= $e(str_repeat('★', $record['rating'])) ?>
                            <span class="text-secondary">(<?= $e($record['rating']) ?>/5)</span>
                        </dd>

                        <dt class="col-sm-4">Title</dt>
                        <dd class="col-sm-8"><?= $e($record['title'] ?? 'Not provided') ?></dd>

                        <dt class="col-sm-4">Last updated</dt>
                        <dd class="col-sm-8 mb-0"><?= $e($record['updatedAt']) ?></dd>
                    </dl>

                    <h3 class="h6">Comment</h3>
                    <div class="border rounded bg-light p-3 text-break">
                        <?= nl2br($e($record['comment']), false) ?>
                    </div>
                </div>
            </div>

            <div class="card admin-panel">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-0">Moderation evidence</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Moderator user ID</dt>
                        <dd class="col-sm-8"><?= $e($record['moderatedBy'] ?? 'Not moderated') ?></dd>

                        <dt class="col-sm-4">Moderated at</dt>
                        <dd class="col-sm-8"><?= $e($record['moderatedAt'] ?? 'Not moderated') ?></dd>

                        <dt class="col-sm-4">Moderation note</dt>
                        <dd class="col-sm-8 mb-0">
                            <?php if ($record['moderationNote'] === null): ?>
                                Not recorded
                            <?php else: ?>
                                <span class="text-break"><?= nl2br($e($record['moderationNote']), false) ?></span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <?php if ($record['canModerate']): ?>
                <div class="card admin-panel mb-4" aria-labelledby="review-moderation-heading">
                    <div class="card-header bg-transparent">
                        <h2 id="review-moderation-heading" class="h5 mb-0">Moderate review</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!$moderationEnabled): ?>
                            <div class="alert alert-warning mb-0" role="alert">
                                Moderation is unavailable until the approved note-length policy is configured.
                            </div>
                        <?php else: ?>
                            <p class="text-secondary">
                                Approval makes this review public. Rejection permanently records the decision and requires a note.
                            </p>
                            <div id="review-moderation-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

                            <div class="d-grid gap-3">
                                <form
                                    class="border rounded p-3"
                                    action="/api/v1/admin/reviews/<?= $e($record['id']) ?>/status"
                                    method="post"
                                    data-review-moderation-form
                                    data-review-status="approved"
                                >
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <div class="mb-3">
                                        <label class="form-label" for="review-approval-note">Approval note</label>
                                        <textarea
                                            class="form-control"
                                            id="review-approval-note"
                                            name="moderation_note"
                                            rows="3"
                                            maxlength="<?= $e($maximumNoteCharacters) ?>"
                                        ></textarea>
                                        <div class="form-text">
                                            Optional. Maximum <?= $e($maximumNoteCharacters) ?> characters.
                                        </div>
                                    </div>
                                    <button class="btn btn-success" type="submit">Approve review</button>
                                </form>

                                <form
                                    class="border rounded p-3"
                                    action="/api/v1/admin/reviews/<?= $e($record['id']) ?>/status"
                                    method="post"
                                    data-review-moderation-form
                                    data-review-status="rejected"
                                >
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <div class="mb-3">
                                        <label class="form-label" for="review-rejection-note">Rejection note</label>
                                        <textarea
                                            class="form-control"
                                            id="review-rejection-note"
                                            name="moderation_note"
                                            rows="3"
                                            maxlength="<?= $e($maximumNoteCharacters) ?>"
                                            required
                                        ></textarea>
                                        <div class="form-text">
                                            Required. Maximum <?= $e($maximumNoteCharacters) ?> characters.
                                        </div>
                                    </div>
                                    <button class="btn btn-outline-danger" type="submit">Reject review</button>
                                </form>
                            </div>

                            <noscript>
                                <p class="alert alert-warning mt-3 mb-0">
                                    JavaScript is required to moderate reviews safely.
                                </p>
                            </noscript>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card admin-panel mb-4">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-0">Booking context</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Reference</dt>
                        <dd class="col-sm-7">
                            <a href="/admin/bookings/<?= $e($record['bookingId']) ?>">
                                <?= $e($record['bookingReference']) ?>
                            </a>
                        </dd>

                        <dt class="col-sm-5">Booking status</dt>
                        <dd class="col-sm-7"><?= $e($record['bookingStatus']) ?></dd>

                        <dt class="col-sm-5">Guest email</dt>
                        <dd class="col-sm-7">
                            <a href="mailto:<?= $e($record['guestEmail']) ?>"><?= $e($record['guestEmail']) ?></a>
                        </dd>

                        <dt class="col-sm-5">Stay</dt>
                        <dd class="col-sm-7 mb-0"><?= $e($record['checkIn']) ?> to <?= $e($record['checkOut']) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card admin-panel">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-0">Invitation validation</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Invitation ID</dt>
                        <dd class="col-sm-7"><?= $e($record['invitationId']) ?></dd>

                        <dt class="col-sm-5">Created</dt>
                        <dd class="col-sm-7"><?= $e($record['invitationCreatedAt']) ?></dd>

                        <dt class="col-sm-5">Expires</dt>
                        <dd class="col-sm-7"><?= $e($record['invitationExpiresAt']) ?></dd>

                        <dt class="col-sm-5">Consumed</dt>
                        <dd class="col-sm-7 mb-0"><?= $e($record['invitationUsedAt'] ?? 'Not consumed') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($record['canModerate'] && $moderationEnabled): ?>
    <script src="/assets/js/admin-reviews.js" defer></script>
<?php endif; ?>
