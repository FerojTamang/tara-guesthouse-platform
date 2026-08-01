<aside
    id="administrator-sidebar"
    class="admin-sidebar offcanvas-lg offcanvas-start"
    tabindex="-1"
    aria-labelledby="administrator-sidebar-label"
>
    <div class="offcanvas-header border-bottom border-light border-opacity-10">
        <div>
            <p class="admin-brand-kicker mb-1">Tara Guest House</p>
            <h2 id="administrator-sidebar-label" class="admin-brand-title mb-0">Administration</h2>
        </div>
        <button
            type="button"
            class="btn-close btn-close-white d-lg-none"
            data-bs-dismiss="offcanvas"
            data-bs-target="#administrator-sidebar"
            aria-label="Close navigation"
        ></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-3 p-lg-4">
        <nav aria-label="Administrator navigation">
            <p class="admin-navigation-heading">Workspace</p>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'dashboard' ? ' active' : '' ?>"
                        href="/admin/dashboard"
                        <?= $activeNavigation === 'dashboard' ? 'aria-current="page"' : '' ?>
                    >
                        Dashboard
                    </a>
                </li>
            </ul>

            <p class="admin-navigation-heading mt-4">Management</p>
            <ul class="nav nav-pills flex-column gap-1" aria-label="Management modules">
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'rooms' ? ' active' : '' ?>"
                        href="/admin/rooms"
                        <?= $activeNavigation === 'rooms' ? 'aria-current="page"' : '' ?>
                    >
                        Rooms
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'room-types' ? ' active' : '' ?>"
                        href="/admin/room-types"
                        <?= $activeNavigation === 'room-types' ? 'aria-current="page"' : '' ?>
                    >
                        Room types
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'amenities' ? ' active' : '' ?>"
                        href="/admin/amenities"
                        <?= $activeNavigation === 'amenities' ? 'aria-current="page"' : '' ?>
                    >
                        Amenities
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'bookings' ? ' active' : '' ?>"
                        href="/admin/bookings"
                        <?= $activeNavigation === 'bookings' ? 'aria-current="page"' : '' ?>
                    >
                        Bookings
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'payment-methods' ? ' active' : '' ?>"
                        href="/admin/payment-methods"
                        <?= $activeNavigation === 'payment-methods' ? 'aria-current="page"' : '' ?>
                    >
                        Payment methods
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'payments' ? ' active' : '' ?>"
                        href="/admin/payments"
                        <?= $activeNavigation === 'payments' ? 'aria-current="page"' : '' ?>
                    >
                        Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'invoices' ? ' active' : '' ?>"
                        href="/admin/invoices"
                        <?= $activeNavigation === 'invoices' ? 'aria-current="page"' : '' ?>
                    >
                        Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'activities' ? ' active' : '' ?>"
                        href="/admin/activities"
                        <?= $activeNavigation === 'activities' ? 'aria-current="page"' : '' ?>
                    >
                        Activities
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'gallery' ? ' active' : '' ?>"
                        href="/admin/gallery"
                        <?= $activeNavigation === 'gallery' ? 'aria-current="page"' : '' ?>
                    >
                        Gallery
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'pages' ? ' active' : '' ?>"
                        href="/admin/pages"
                        <?= $activeNavigation === 'pages' ? 'aria-current="page"' : '' ?>
                    >
                        Pages
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'reviews' ? ' active' : '' ?>"
                        href="/admin/reviews"
                        <?= $activeNavigation === 'reviews' ? 'aria-current="page"' : '' ?>
                    >
                        Reviews
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'contact-messages' ? ' active' : '' ?>"
                        href="/admin/contact-messages"
                        <?= $activeNavigation === 'contact-messages' ? 'aria-current="page"' : '' ?>
                    >
                        Contact messages
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link admin-navigation-link<?= $activeNavigation === 'settings' ? ' active' : '' ?>"
                        href="/admin/settings"
                        <?= $activeNavigation === 'settings' ? 'aria-current="page"' : '' ?>
                    >
                        Settings
                    </a>
                </li>
            </ul>
        </nav>

        <div class="admin-sidebar-note mt-auto" role="note">
            Additional modules will become available as their approved tickets are completed.
        </div>
    </div>
</aside>
