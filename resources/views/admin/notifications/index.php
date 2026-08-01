<section aria-labelledby="notifications-heading" data-notification-inbox>
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Administrator inbox</p>
            <h1 id="notifications-heading" class="h2 mb-2">Notifications</h1>
            <p class="text-secondary mb-0">
                Review in-application notices assigned to your Administrator account.
            </p>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form action="/api/v1/admin/notifications/read-all" method="post" data-notification-read-all-form>
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <button class="btn btn-outline-primary" type="submit">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>

    <div id="notification-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4" aria-label="Notification summary">
        <div class="col-sm-6 col-xl-3">
            <div class="card admin-panel h-100">
                <div class="card-body">
                    <p class="admin-card-kicker mb-2">Unread</p>
                    <p class="display-6 mb-0" data-notification-page-unread-count><?= $e($unreadCount) ?></p>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card admin-panel h-100">
                <div class="card-body">
                    <p class="admin-card-kicker mb-2">Total</p>
                    <p class="display-6 mb-0"><?= $e($total) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card admin-panel">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center gap-3">
            <h2 class="h5 mb-0">Inbox</h2>
            <span class="text-secondary small">Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if ($notifications === []): ?>
                <div class="p-4">
                    <p class="mb-0 text-secondary">No notifications are available on this page.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <article
                            class="list-group-item p-4<?= $notification['isRead'] ? '' : ' bg-body-tertiary' ?>"
                            data-notification-row="<?= $e($notification['id']) ?>"
                        >
                            <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between">
                                <div class="min-w-0">
                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                        <h3 class="h6 mb-0"><?= $e($notification['title']) ?></h3>
                                        <span class="badge <?= $notification['isRead'] ? 'text-bg-secondary' : 'text-bg-primary' ?>">
                                            <?= $notification['isRead'] ? 'Read' : 'Unread' ?>
                                        </span>
                                        <span class="badge text-bg-light border"><?= $e($notification['typeLabel']) ?></span>
                                    </div>
                                    <p class="mb-2 notification-message"><?= nl2br($e($notification['message']), false) ?></p>
                                    <p class="small text-secondary mb-0">
                                        Received <?= $e($notification['createdAt']) ?>
                                        <?php if ($notification['readAt'] !== null): ?>
                                            · Read <?= $e($notification['readAt']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($notification['relatedType'] !== null || $notification['relatedId'] !== null): ?>
                                        <p class="small text-secondary mb-0 mt-1">
                                            Related record:
                                            <?= $e($notification['relatedType'] ?? 'record') ?>
                                            <?= $notification['relatedId'] !== null ? '#' . $e($notification['relatedId']) : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$notification['isRead']): ?>
                                    <form
                                        action="/api/v1/admin/notifications/<?= $e($notification['id']) ?>/read"
                                        method="post"
                                        data-notification-read-form
                                    >
                                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                        <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit">
                                            Mark as read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-transparent">
                <nav aria-label="Notification pages">
                    <ul class="pagination mb-0 justify-content-center">
                        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                            <a class="page-link" href="/admin/notifications?page=<?= $e(max(1, $page - 1)) ?>"<?= $page <= 1 ? ' tabindex="-1" aria-disabled="true"' : '' ?>>
                                Previous
                            </a>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
                        </li>
                        <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
                            <a class="page-link" href="/admin/notifications?page=<?= $e(min($totalPages, $page + 1)) ?>"<?= $page >= $totalPages ? ' tabindex="-1" aria-disabled="true"' : '' ?>>
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <noscript>
        <p class="alert alert-warning mt-4 mb-0">
            JavaScript is required to mark notifications as read. Reading the inbox remains available without JavaScript.
        </p>
    </noscript>
</section>
