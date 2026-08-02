<!doctype html>
<?php
$isHomePage = ($isHome ?? false) === true;
$isRoomsPage = ($isRooms ?? false) === true;
$isRoomDetailPage = ($isRoomDetail ?? false) === true;
$isBookingFlowPage = ($isBookingFlow ?? false) === true;
$isImmersivePublicPage = $isHomePage || $isRoomsPage;
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?></title>
    <?php if (($metaDescription ?? null) !== null): ?>
    <meta name="description" content="<?= $e($metaDescription) ?>">
    <?php endif; ?>
    <?php if (($canonicalUrl ?? null) !== null): ?>
    <link rel="canonical" href="<?= $e($canonicalUrl) ?>">
    <?php endif; ?>
    <?php if (($openGraphTitle ?? null) !== null): ?>
    <meta property="og:title" content="<?= $e($openGraphTitle) ?>">
    <meta property="og:type" content="website">
    <?php endif; ?>
    <?php if (($openGraphDescription ?? null) !== null): ?>
    <meta property="og:description" content="<?= $e($openGraphDescription) ?>">
    <?php endif; ?>
    <?php if (($canonicalUrl ?? null) !== null): ?>
    <meta property="og:url" content="<?= $e($canonicalUrl) ?>">
    <?php endif; ?>
    <?php if (($openGraphImage ?? null) !== null): ?>
    <meta property="og:image" content="<?= $e($openGraphImage) ?>">
    <?php endif; ?>
    <?php if ($isHomePage): ?>
    <link rel="preload" as="image" href="/assets/images/home/home-hero-sauraha-jungle-lodge.webp" type="image/webp">
    <?php endif; ?>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/public.css">
    <?php if ($isHomePage): ?>
    <link rel="stylesheet" href="/assets/css/pages/home.css">
    <?php endif; ?>
    <?php if ($isRoomsPage): ?>
    <link rel="stylesheet" href="/assets/css/pages/rooms.css">
    <?php endif; ?>
    <?php if ($isRoomDetailPage): ?>
    <link rel="stylesheet" href="/assets/css/pages/room-detail.css">
    <?php endif; ?>
    <?php if ($isBookingFlowPage): ?>
    <link rel="stylesheet" href="/assets/css/pages/booking-flow.css">
    <?php endif; ?>
</head>
<body class="<?= $isHomePage ? 'tg-page-home' : ($isRoomDetailPage ? 'tg-page-room-detail' : ($isRoomsPage ? 'tg-page-rooms' : ($isBookingFlowPage ? 'tg-page-booking-flow' : 'tg-page-public'))) ?>">
    <a class="tg-skip-link" href="#main-content">Skip to main content</a>
    <?php if (($showPublicNavigation ?? true) === true): ?>
    <header class="tg-public-header<?= $isImmersivePublicPage ? ' tg-public-header--overlay' : '' ?>" data-public-header>
        <nav class="tg-public-nav tg-container" aria-label="Primary navigation">
            <a class="tg-brand" href="/" aria-label="<?= $e($siteName ?? 'Tara Guest House') ?> home">
                <span class="tg-brand__mark" aria-hidden="true">T</span>
                <span class="tg-brand__text"><?= $e($siteName ?? 'Tara Guest House') ?></span>
            </a>
            <button
                class="tg-nav-toggle"
                type="button"
                aria-controls="public-navigation"
                aria-expanded="false"
                aria-label="Open navigation"
                data-nav-toggle
            >
                <span class="tg-nav-toggle__open"><?= $partial('components.public-icon', ['name' => 'menu']) ?></span>
                <span class="tg-nav-toggle__close"><?= $partial('components.public-icon', ['name' => 'close']) ?></span>
            </button>
            <div class="tg-nav-panel" id="public-navigation" data-nav-panel>
                <ul class="tg-nav-list">
                    <li><a class="tg-nav-link" data-nav-link href="/">Home</a></li>
                    <li><a class="tg-nav-link" data-nav-link href="/rooms">Rooms</a></li>
                    <li><a class="tg-nav-link" data-nav-link href="/activities">Activities</a></li>
                    <li><a class="tg-nav-link" data-nav-link href="/gallery">Gallery</a></li>
                    <li><a class="tg-nav-link" data-nav-link href="/reviews">Reviews</a></li>
                    <li><a class="tg-nav-link" data-nav-link href="/availability">Availability</a></li>
                    <li><a class="tg-nav-link" data-nav-link href="/contact">Contact</a></li>
                    <?php foreach (($navigationPages ?? []) as $navigationPage): ?>
                        <li><a class="tg-nav-link" data-nav-link href="/<?= $e($navigationPage['slug']) ?>"><?= $e($navigationPage['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <a class="tg-button tg-button--mint tg-nav-cta" href="/availability" data-ripple>
                    Check Availability
                </a>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    <main id="main-content" class="<?= $isHomePage ? 'tg-main tg-main--home' : ($isRoomsPage ? 'tg-main tg-main--rooms' : ($isBookingFlowPage ? 'tg-main tg-main--journey' : 'tg-main tg-container tg-page-shell')) ?>" tabindex="-1">
        <?= $content ?>
    </main>
    <?php if (($showPublicNavigation ?? true) === true): ?>
    <footer class="tg-public-footer">
        <div class="tg-container">
            <div class="tg-footer-grid">
                <div class="tg-footer-brand">
                    <a class="tg-brand tg-brand--footer" href="/">
                        <span class="tg-brand__mark" aria-hidden="true">T</span>
                        <span class="tg-brand__text"><?= $e($siteName ?? 'Tara Guest House') ?></span>
                    </a>
                    <p>A calm and welcoming base for discovering the natural rhythm of Sauraha.</p>
                </div>
                <div>
                    <h2 class="tg-footer-heading">Discover</h2>
                    <ul class="tg-footer-links">
                        <li><a href="/rooms">Rooms</a></li>
                        <li><a href="/activities">Activities</a></li>
                        <li><a href="/gallery">Gallery</a></li>
                        <li><a href="/reviews">Guest Reviews</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="tg-footer-heading">Plan your stay</h2>
                    <ul class="tg-footer-links">
                        <li><a href="/availability">Check Availability</a></li>
                        <li><a href="/contact">Contact</a></li>
                        <?php foreach (($navigationPages ?? []) as $navigationPage): ?>
                            <li><a href="/<?= $e($navigationPage['slug']) ?>"><?= $e($navigationPage['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (($footerSettings ?? []) !== []): ?>
                <div>
                    <h2 class="tg-footer-heading">Guest information</h2>
                    <dl class="tg-footer-details">
                        <?php foreach ($footerSettings as $setting): ?>
                            <div>
                                <dt><?= $e($setting['label']) ?></dt>
                                <dd>
                                    <?php if ($setting['value_type'] === 'email'): ?>
                                        <a href="mailto:<?= $e($setting['value']) ?>"><?= $e($setting['value']) ?></a>
                                    <?php elseif ($setting['value_type'] === 'url'): ?>
                                        <a href="<?= $e($setting['value']) ?>" rel="noopener noreferrer"><?= $e($setting['value']) ?></a>
                                    <?php else: ?>
                                        <?= $e($setting['value']) ?>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <?php endif; ?>
            </div>
            <div class="tg-footer-bottom">
                <p>&copy; <?= $e((string) date('Y')) ?> <?= $e($siteName ?? 'Tara Guest House') ?>.</p>
                <p>Sauraha, Nepal</p>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"
    ></script>
    <?php if (($showPublicNavigation ?? true) === true): ?>
    <script src="/assets/js/public.js" defer></script>
    <?php endif; ?>
    <?php if ($isHomePage): ?>
    <script src="/assets/js/pages/home.js" defer></script>
    <?php endif; ?>
    <?php if ($isRoomDetailPage): ?>
    <script src="/assets/js/pages/room-detail.js" defer></script>
    <?php endif; ?>
</body>
</html>
