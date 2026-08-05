<section aria-labelledby="contact-message-heading">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
        <div class="admin-page-heading">
            <p class="admin-eyebrow mb-2">Guest communication</p>
            <h1 id="contact-message-heading" class="h2 mb-2">Contact message</h1>
            <p class="text-secondary mb-0">Received <?= $e($contact['createdAtDisplay']) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge <?= $e($contact['statusBadge']) ?> fs-6">
                <?= $e($contact['statusLabel']) ?>
            </span>
            <a class="btn btn-outline-secondary" href="/admin/contact-messages">
                Back to messages
            </a>
        </div>
    </div>

    <div id="contact-action-message" class="d-none" tabindex="-1" aria-live="polite"></div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-7">
            <div class="card admin-panel mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Guest inquiry</h2>
                    <dl class="row mb-4">
                        <dt class="col-sm-3">Name</dt>
                        <dd class="col-sm-9"><?= $e($contact['name']) ?></dd>

                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">
                            <a href="mailto:<?= $e($contact['email']) ?>"><?= $e($contact['email']) ?></a>
                        </dd>

                        <dt class="col-sm-3">Phone</dt>
                        <dd class="col-sm-9">
                            <?= $contact['phone'] === null ? 'Not provided' : $e($contact['phone']) ?>
                        </dd>

                        <dt class="col-sm-3">Subject</dt>
                        <dd class="col-sm-9"><?= $e($contact['subject']) ?></dd>
                    </dl>

                    <div class="border rounded bg-light p-3">
                        <?= nl2br($e($contact['message']), false) ?>
                    </div>
                </div>
            </div>

            <div class="card admin-panel">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
                        <div>
                            <p class="admin-eyebrow mb-1">Retained history</p>
                            <h2 class="h5 mb-0">Email replies</h2>
                        </div>
                        <span class="text-secondary small">
                            <?= count($replies) ?> <?= count($replies) === 1 ? 'reply' : 'replies' ?>
                        </span>
                    </div>

                    <?php if ($replies === []): ?>
                        <p class="text-secondary mb-0">No reply has been sent from the Administrator panel.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php foreach ($replies as $reply): ?>
                                <article class="border rounded p-3">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                        <span class="badge <?= $e($reply['deliveryBadge']) ?>">
                                            <?= $e($reply['deliveryLabel']) ?>
                                        </span>
                                        <span class="small text-secondary"><?= $e($reply['createdAtDisplay']) ?></span>
                                    </div>
                                    <div class="small text-secondary mb-2">
                                        Administrator user #<?= $e($reply['sent_by']) ?>
                                    </div>
                                    <div><?= nl2br($e($reply['reply_body']), false) ?></div>
                                    <?php if ($reply['delivery_status'] === 'failed'): ?>
                                        <p class="small text-danger mt-2 mb-0">
                                            Gmail did not accept this delivery attempt. The inquiry remains open.
                                        </p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <aside class="col-xl-5">
            <div class="card admin-panel mb-4">
                <div class="card-body p-4">
                    <p class="admin-eyebrow mb-1">Email response</p>
                    <h2 class="h5 mb-2">Reply to <?= $e($contact['name']) ?></h2>
                    <p class="text-secondary small">
                        The recipient is locked to <?= $e($contact['email']) ?>. The email is sent through
                        the configured Tara Guest House SMTP account.
                    </p>

                    <?php if ($contact['canReply']): ?>
                        <form
                            action="/api/v1/admin/contact-messages/<?= $e($contact['contact_message_id']) ?>/reply"
                            method="post"
                            data-contact-reply-form
                        >
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <div class="mb-3">
                                <label class="form-label" for="contact-reply-body">Reply message</label>
                                <textarea
                                    class="form-control"
                                    id="contact-reply-body"
                                    name="reply_body"
                                    rows="9"
                                    maxlength="5000"
                                    required
                                    placeholder="Write a clear response to the Guest's question."
                                ></textarea>
                                <div class="form-text">Plain text, maximum 5000 characters.</div>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Send email reply</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary mb-0" role="note">
                            This inquiry is closed. Reopen it before sending another reply.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card admin-panel">
                <div class="card-body p-4">
                    <p class="admin-eyebrow mb-1">Workflow</p>
                    <h2 class="h5 mb-2">Manage inquiry</h2>
                    <p class="text-secondary small">
                        Closing removes the reply form but preserves the original inquiry and all reply history.
                    </p>

                    <form
                        action="/api/v1/admin/contact-messages/<?= $e($contact['contact_message_id']) ?>/status"
                        method="post"
                        data-contact-status-form
                        data-contact-status="<?= $contact['status'] === 'closed' ? 'in_progress' : 'closed' ?>"
                    >
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <button
                            class="btn <?= $contact['status'] === 'closed' ? 'btn-outline-primary' : 'btn-outline-secondary' ?> w-100"
                            type="submit"
                        >
                            <?= $contact['status'] === 'closed' ? 'Reopen inquiry' : 'Mark inquiry closed' ?>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
    </div>
</section>

<script src="/assets/js/admin-contact-messages.js" defer></script>
