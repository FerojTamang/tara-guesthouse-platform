<!doctype html>
<?php
$isHomePage = ($isHome ?? false) === true;
$isRoomsPage = ($isRooms ?? false) === true;
$isRoomDetailPage = ($isRoomDetail ?? false) === true;
$isBookingFlowPage = ($isBookingFlow ?? false) === true;
$isActivitiesPage = ($isActivities ?? false) === true;
$isGalleryPage = ($isGallery ?? false) === true;
$isPublishingPage = ($isPublishing ?? false) === true;
$requestedOpenGraphType = $openGraphType ?? 'website';
$resolvedOpenGraphType = is_string($requestedOpenGraphType) && in_array($requestedOpenGraphType, ['website', 'article'], true)
    ? $requestedOpenGraphType
    : 'website';
$isImmersivePublicPage = $isHomePage || $isRoomsPage;
$footerSettingValues = [];
foreach (($footerSettings ?? []) as $setting) {
    if (
        is_array($setting)
        && isset($setting['setting_key'])
        && is_string($setting['setting_key'])
        && isset($setting['value'])
        && is_scalar($setting['value'])
    ) {
        $footerSettingValues[$setting['setting_key']] = (string) $setting['value'];
    }
}
$serverSiteName = $footerSettingValues['site.name'] ?? ($siteName ?? 'Tara Guest House');
$serverFooterDescription = $footerSettingValues['footer.description']
    ?? 'A calm and welcoming base for discovering the natural rhythm of Sauraha.';
$serverContactEmail = $footerSettingValues['contact.email'] ?? '';
$serverContactPhone = $footerSettingValues['contact.phone'] ?? '';
$serverWhatsapp = $footerSettingValues['contact.whatsapp'] ?? '';
$serverWhatsappDigits = preg_replace('/[^0-9]+/', '', $serverWhatsapp) ?? '';
$serverAddress = $footerSettingValues['contact.address'] ?? '';
$serverMaps = $footerSettingValues['contact.maps'] ?? '';
$serverSocial = [
    'facebook' => $footerSettingValues['social.facebook'] ?? '',
    'instagram' => $footerSettingValues['social.instagram'] ?? '',
    'youtube' => $footerSettingValues['social.youtube'] ?? '',
    'tiktok' => $footerSettingValues['social.tiktok'] ?? '',
];
$resolvedMainNavigation = $navigationItems ?? null;
if (!is_array($resolvedMainNavigation) || !array_is_list($resolvedMainNavigation)) {
    $resolvedMainNavigation = [
        ['label' => 'Home', 'href' => '/', 'children' => []],
        ['label' => 'Rooms', 'href' => '/rooms', 'children' => []],
        ['label' => 'Activities', 'href' => '/activities', 'children' => []],
        ['label' => 'Gallery', 'href' => '/gallery', 'children' => []],
        ['label' => 'Reviews', 'href' => '/reviews', 'children' => []],
        ['label' => 'Availability', 'href' => '/availability', 'children' => []],
        ['label' => 'Contact', 'href' => '/contact', 'children' => []],
    ];
    foreach (($navigationPages ?? []) as $navigationPage) {
        if (is_array($navigationPage) && is_string($navigationPage['title'] ?? null) && is_string($navigationPage['slug'] ?? null)) {
            $resolvedMainNavigation[] = [
                'label' => $navigationPage['title'],
                'href' => '/' . $navigationPage['slug'],
                'children' => [],
            ];
        }
    }
}
$resolvedFooterNavigation = is_array($footerNavigationItems ?? null) && array_is_list($footerNavigationItems)
    ? $footerNavigationItems
    : [];
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
    <?php if (($robotsNoindex ?? false) === true): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <?php if (($openGraphTitle ?? null) !== null): ?>
    <meta property="og:title" content="<?= $e($openGraphTitle) ?>">
    <meta property="og:type" content="<?= $e($resolvedOpenGraphType) ?>">
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
    <?php if ($isActivitiesPage): ?>
    <link rel="stylesheet" href="/assets/css/pages/activities.css">
    <?php endif; ?>
    <?php if ($isGalleryPage): ?>
    <link rel="stylesheet" href="/assets/css/pages/gallery.css">
    <?php endif; ?>
    <?php if ($isPublishingPage): ?>
    <link rel="stylesheet" href="/assets/css/pages/publishing.css">
    <?php endif; ?>
</head>
<body class="<?= $isHomePage ? 'tg-page-home' : ($isRoomDetailPage ? 'tg-page-room-detail' : ($isRoomsPage ? 'tg-page-rooms' : ($isBookingFlowPage ? 'tg-page-booking-flow' : ($isActivitiesPage ? 'tg-page-activities' : ($isGalleryPage ? 'tg-page-gallery' : ($isPublishingPage ? 'tg-page-publishing' : 'tg-page-public')))))) ?>">
    <a class="tg-skip-link" href="#main-content">Skip to main content</a>
    <?php if (($showPublicNavigation ?? true) === true): ?>
    <header class="tg-public-header<?= $isImmersivePublicPage ? ' tg-public-header--overlay' : '' ?>" data-public-header>
        <nav class="tg-public-nav tg-container" aria-label="Primary navigation">
            <a class="tg-brand" href="/" aria-label="<?= $e($serverSiteName) ?> home" data-public-brand>
                <img class="tg-brand__logo" src="" alt="" width="42" height="42" hidden data-public-brand-logo>
                <span class="tg-brand__mark" aria-hidden="true" data-public-brand-fallback>T</span>
                <span class="tg-brand__text" data-public-site-name><?= $e($serverSiteName) ?></span>
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
                    <?php foreach ($resolvedMainNavigation as $navigationItem): ?>
                        <?php
                        $navigationLabel = is_string($navigationItem['label'] ?? null) ? $navigationItem['label'] : '';
                        $navigationHref = is_string($navigationItem['href'] ?? null) ? $navigationItem['href'] : null;
                        $navigationChildren = is_array($navigationItem['children'] ?? null) && array_is_list($navigationItem['children'])
                            ? $navigationItem['children']
                            : [];
                        ?>
                        <?php if ($navigationLabel !== '' && $navigationHref !== null): ?>
                            <li><a class="tg-nav-link" data-nav-link href="<?= $e($navigationHref) ?>"><?= $e($navigationLabel) ?></a></li>
                        <?php elseif ($navigationLabel !== '' && $navigationChildren !== []): ?>
                            <li class="tg-nav-dropdown-item">
                                <details class="tg-nav-dropdown">
                                    <summary class="tg-nav-link"><?= $e($navigationLabel) ?></summary>
                                    <ul class="tg-nav-submenu">
                                        <?php foreach ($navigationChildren as $child): ?>
                                            <?php if (is_string($child['label'] ?? null) && is_string($child['href'] ?? null)): ?>
                                                <li><a data-nav-link href="<?= $e($child['href']) ?>"><?= $e($child['label']) ?></a></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <a class="tg-button tg-button--mint tg-nav-cta" href="/availability" data-ripple>
                    Check Availability
                </a>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    <main id="main-content" class="<?= $isHomePage ? 'tg-main tg-main--home' : ($isRoomsPage ? 'tg-main tg-main--rooms' : ($isBookingFlowPage ? 'tg-main tg-main--journey' : ($isActivitiesPage ? 'tg-main tg-main--activities' : ($isGalleryPage ? 'tg-main tg-main--gallery' : 'tg-main tg-container tg-page-shell')))) ?>" tabindex="-1">
        <?= $content ?>
    </main>
    <?php if (($showPublicNavigation ?? true) === true): ?>
    <footer class="tg-public-footer">
        <div class="tg-container">
            <div class="tg-footer-grid">
                <div class="tg-footer-brand">
                    <a class="tg-brand tg-brand--footer" href="/" data-public-brand>
                        <img class="tg-brand__logo" src="" alt="" width="48" height="48" hidden data-public-brand-logo>
                        <span class="tg-brand__mark" aria-hidden="true" data-public-brand-fallback>T</span>
                        <span class="tg-brand__text" data-public-site-name><?= $e($serverSiteName) ?></span>
                    </a>
                    <p data-public-footer-description><?= $e($serverFooterDescription) ?></p>
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
                        <?php foreach ($resolvedFooterNavigation as $footerNavigationItem): ?>
                            <?php if (is_string($footerNavigationItem['label'] ?? null) && is_string($footerNavigationItem['href'] ?? null)): ?>
                                <li><a href="<?= $e($footerNavigationItem['href']) ?>"><?= $e($footerNavigationItem['label']) ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div data-public-contact-group<?= ($serverContactEmail === '' && $serverContactPhone === '' && $serverWhatsappDigits === '' && $serverAddress === '' && $serverMaps === '') ? ' hidden' : '' ?>>
                    <h2 class="tg-footer-heading">Contact</h2>
                    <ul class="tg-footer-contact-list">
                        <li data-public-contact-item="email"<?= $serverContactEmail === '' ? ' hidden' : '' ?>>
                            <a href="<?= $serverContactEmail !== '' ? 'mailto:' . $e($serverContactEmail) : '#' ?>" data-public-contact-email><?= $e($serverContactEmail) ?></a>
                        </li>
                        <li data-public-contact-item="phone"<?= $serverContactPhone === '' ? ' hidden' : '' ?>>
                            <a href="<?= $serverContactPhone !== '' ? 'tel:' . $e(preg_replace('/[^0-9+]+/', '', $serverContactPhone) ?? '') : '#' ?>" data-public-contact-phone><?= $e($serverContactPhone) ?></a>
                        </li>
                        <li data-public-contact-item="whatsapp"<?= $serverWhatsappDigits === '' ? ' hidden' : '' ?>>
                            <a href="<?= $serverWhatsappDigits !== '' ? 'https://wa.me/' . $e($serverWhatsappDigits) : '#' ?>" target="_blank" rel="noopener noreferrer" data-public-contact-whatsapp>Chat on WhatsApp</a>
                        </li>
                        <li data-public-contact-item="address"<?= $serverAddress === '' ? ' hidden' : '' ?>>
                            <span data-public-contact-address><?= $e($serverAddress) ?></span>
                        </li>
                        <li data-public-contact-item="maps"<?= $serverMaps === '' ? ' hidden' : '' ?>>
                            <a href="<?= $serverMaps !== '' ? $e($serverMaps) : '#' ?>" target="_blank" rel="noopener noreferrer" data-public-contact-maps>View on Google Maps</a>
                        </li>
                    </ul>
                    <div class="tg-social-links" aria-label="Social media" data-public-social-group<?= count(array_filter($serverSocial)) === 0 ? ' hidden' : '' ?>>
                        <a class="tg-social-link" href="<?= $e($serverSocial['facebook'] ?: '#') ?>" target="_blank" rel="noopener noreferrer" aria-label="Visit us on Facebook" data-public-social="facebook"<?= $serverSocial['facebook'] === '' ? ' hidden' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-8h2.8l.4-3h-3.2V8.1c0-.9.3-1.5 1.6-1.5H17V4a25 25 0 0 0-2.4-.1c-2.4 0-4.1 1.5-4.1 4.2V10H8v3h2.5v8h3z"/></svg></a>
                        <a class="tg-social-link" href="<?= $e($serverSocial['instagram'] ?: '#') ?>" target="_blank" rel="noopener noreferrer" aria-label="Visit us on Instagram" data-public-social="instagram"<?= $serverSocial['instagram'] === '' ? ' hidden' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9Zm9.8 1.5a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg></a>
                        <a class="tg-social-link" href="<?= $e($serverSocial['youtube'] ?: '#') ?>" target="_blank" rel="noopener noreferrer" aria-label="Visit us on YouTube" data-public-social="youtube"<?= $serverSocial['youtube'] === '' ? ' hidden' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 7.2a3 3 0 0 0-2.1-2.1C17.6 4.6 12 4.6 12 4.6s-5.6 0-7.5.5a3 3 0 0 0-2.1 2.1A31 31 0 0 0 2 12a31 31 0 0 0 .4 4.8 3 3 0 0 0 2.1 2.1c1.9.5 7.5.5 7.5.5s5.6 0 7.5-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 22 12a31 31 0 0 0-.4-4.8ZM10 15.5v-7l6 3.5-6 3.5Z"/></svg></a>
                        <a class="tg-social-link" href="<?= $e($serverSocial['tiktok'] ?: '#') ?>" target="_blank" rel="noopener noreferrer" aria-label="Visit us on TikTok" data-public-social="tiktok"<?= $serverSocial['tiktok'] === '' ? ' hidden' : '' ?>><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h3a5 5 0 0 0 4 4v3a8 8 0 0 1-4-1.1V16a6 6 0 1 1-6-6c.5 0 1 .1 1.5.2v3.1A3 3 0 1 0 15 16V3Z"/></svg></a>
                    </div>
                </div>
            </div>
            <div class="tg-footer-bottom">
                <p>&copy; <?= $e((string) date('Y')) ?> <span data-public-copyright><?= $e($footerSettingValues['footer.copyright'] ?? ($serverSiteName . '.')) ?></span></p>
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
    <script src="/assets/js/public-settings.js" defer></script>
    <?php endif; ?>
    <?php if ($isHomePage): ?>
    <script src="/assets/js/pages/home.js" defer></script>
    <?php endif; ?>
    <?php if ($isRoomDetailPage): ?>
    <script src="/assets/js/pages/room-detail.js" defer></script>
    <?php endif; ?>
</body>
</html>
