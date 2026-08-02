<header class="admin-topbar border-bottom">
    <div class="container-fluid d-flex align-items-center gap-3 px-3 px-md-4">
        <button
            class="btn d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#administrator-sidebar"
            aria-controls="administrator-sidebar"
            aria-label="Open administrator navigation"
        >
            <img src="/assets/images/menu-navigation-grid-1528-svgrepo-com.svg" alt="Menu" width="30px">
        </button>

        <div class="min-w-0">
            <p class="admin-topbar-kicker mb-0">Administrator workspace</p>
            <p class="admin-topbar-title mb-0 text-truncate"><?= $e($pageTitle) ?></p>
        </div>

        <form class="ms-auto" method="post" action="/admin/logout">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
            <button class="btn topbar-btn" type="submit">Log out</button>
        </form>
    </div>
</header>
