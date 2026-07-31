<section aria-labelledby="contact-message-heading">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-4">
        <div class="admin-page-heading">
            <p class="admin-eyebrow mb-2">Guest communication</p>
            <h1 id="contact-message-heading" class="h2 mb-2">Contact message</h1>
            <p class="text-secondary mb-0">Received <?= $e($contact['createdAtDisplay']) ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/contact-messages">
            Back to messages
        </a>
    </div>

    <div class="alert alert-secondary" role="note">
        Workflow status, assignment, retention actions, and SLA controls are intentionally
        unavailable until the approved contact policy is supplied.
    </div>

    <div class="card admin-panel">
        <div class="card-body p-4">
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

                <dt class="col-sm-3">Workflow</dt>
                <dd class="col-sm-9"><?= $e($contact['statusLabel']) ?></dd>

                <dt class="col-sm-3">Subject</dt>
                <dd class="col-sm-9"><?= $e($contact['subject']) ?></dd>
            </dl>

            <h2 class="h5">Message</h2>
            <div class="border rounded bg-light p-3">
                <?= nl2br($e($contact['message']), false) ?>
            </div>
        </div>
    </div>
</section>
