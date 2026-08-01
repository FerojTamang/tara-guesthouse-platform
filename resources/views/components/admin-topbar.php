<?php
$initialNotificationUnreadCount = is_int($notificationUnreadCount) && $notificationUnreadCount > 0
    ? $notificationUnreadCount
    : 0;
?>
<header class="admin-topbar border-bottom">
    <div class="container-fluid d-flex align-items-center gap-3 px-3 px-md-4">
        <button
            class="btn btn-outline-secondary d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#administrator-sidebar"
            aria-controls="administrator-sidebar"
            aria-label="Open administrator navigation"
        >
            Menu
        </button>

        <div class="min-w-0">
            <p class="admin-topbar-kicker mb-0">Administrator workspace</p>
            <p class="admin-topbar-title mb-0 text-truncate"><?= $e($pageTitle) ?></p>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
            <a
                class="btn btn-outline-primary position-relative"
                href="/admin/notifications"
                data-notification-link
                aria-label="<?= $e($initialNotificationUnreadCount > 0
                    ? 'Open Administrator notifications, ' . $initialNotificationUnreadCount . ($initialNotificationUnreadCount === 1 ? ' unread notification' : ' unread notifications')
                    : 'Open Administrator notifications, no unread notifications') ?>"
            >
                <span class="d-none d-sm-inline">Notifications</span>
                <span class="d-sm-none">Alerts</span>
                <span
                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"
                    data-notification-unread-count
                    <?= $initialNotificationUnreadCount === 0 ? 'hidden' : '' ?>
                >
                    <?= $e($initialNotificationUnreadCount) ?>
                </span>
            </a>

            <form method="post" action="/admin/logout">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <button class="btn btn-outline-danger" type="submit">Log out</button>
            </form>
        </div>
    </div>
</header>
