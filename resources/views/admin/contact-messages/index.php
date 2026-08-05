<section aria-labelledby="contact-messages-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Guest communication</p>
        <h1 id="contact-messages-heading" class="h2 mb-2">Contact messages</h1>
        <p class="text-secondary mb-0">
            Review Guest inquiries, send email replies, and keep a retained communication history.
        </p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <div class="card admin-panel mb-4">
        <div class="card-body">
            <form method="get" action="/admin/contact-messages" class="row g-3 align-items-end">
                <div class="col-md-7 col-lg-5">
                    <label for="contact-search" class="form-label">Search</label>
                    <input
                        id="contact-search"
                        name="q"
                        type="search"
                        maxlength="100"
                        class="form-control"
                        value="<?= $e($search) ?>"
                        placeholder="Name, email, or subject"
                    >
                </div>
                <div class="col-sm-auto">
                    <button class="btn btn-primary" type="submit">Search</button>
                    <a class="btn btn-outline-secondary" href="/admin/contact-messages">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($contacts === []): ?>
                <p class="p-4 mb-0 text-secondary">No contact messages match this view.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Retained Guest contact messages</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Guest</th>
                                <th class="py-3" scope="col">Subject</th>
                                <th class="py-3" scope="col">Received</th>
                                <th class="py-3" scope="col">Workflow</th>
                                <th class="px-4 py-3 text-end" scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row">
                                        <span class="d-block"><?= $e($contact['name']) ?></span>
                                        <span class="d-block small text-secondary">
                                            <?= $e($contact['email']) ?>
                                        </span>
                                    </th>
                                    <td class="py-3"><?= $e($contact['subject']) ?></td>
                                    <td class="py-3"><?= $e($contact['createdAt']) ?></td>
                                    <td class="py-3">
                                        <span class="badge <?= $e($contact['statusBadge']) ?>">
                                            <?= $e($contact['statusLabel']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="/admin/contact-messages/<?= $e($contact['id']) ?>"
                                        >Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
