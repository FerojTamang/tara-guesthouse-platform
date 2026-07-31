<!doctype html>
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
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
</head>
<body>
    <a class="visually-hidden-focusable" href="#main-content">Skip to main content</a>
    <?php if (($showPublicNavigation ?? true) === true): ?>
    <header class="border-bottom bg-body-tertiary">
        <nav class="navbar navbar-expand-md" aria-label="Primary navigation">
            <div class="container">
                <a class="navbar-brand fw-semibold" href="/"><?= $e($siteName ?? 'Tara Guest House') ?></a>
                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#public-navigation"
                    aria-controls="public-navigation"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="public-navigation">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="/rooms">Rooms</a></li>
                        <li class="nav-item"><a class="nav-link" href="/activities">Activities</a></li>
                        <li class="nav-item"><a class="nav-link" href="/gallery">Gallery</a></li>
                        <li class="nav-item"><a class="nav-link" href="/availability">Availability</a></li>
                        <li class="nav-item"><a class="nav-link" href="/contact">Contact</a></li>
                        <?php foreach (($navigationPages ?? []) as $navigationPage): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/<?= $e($navigationPage['slug']) ?>"><?= $e($navigationPage['title']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    <main id="main-content" class="container py-4" tabindex="-1">
        <?= $content ?>
    </main>
    <?php if (($showPublicNavigation ?? true) === true): ?>
    <footer class="border-top bg-body-tertiary mt-5 py-4">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-5">
                    <p class="h5 mb-2"><?= $e($siteName ?? 'Tara Guest House') ?></p>
                    <p class="text-secondary mb-0">Guest house information is published from approved site settings.</p>
                </div>
                <?php if (($footerSettings ?? []) !== []): ?>
                <div class="col-md-7">
                    <h2 class="h6">Contact and guest information</h2>
                    <dl class="row mb-0">
                        <?php foreach ($footerSettings as $setting): ?>
                            <dt class="col-sm-5"><?= $e($setting['label']) ?></dt>
                            <dd class="col-sm-7">
                                <?php if ($setting['value_type'] === 'email'): ?>
                                    <a href="mailto:<?= $e($setting['value']) ?>"><?= $e($setting['value']) ?></a>
                                <?php elseif ($setting['value_type'] === 'url'): ?>
                                    <a href="<?= $e($setting['value']) ?>" rel="noopener noreferrer"><?= $e($setting['value']) ?></a>
                                <?php else: ?>
                                    <?= $e($setting['value']) ?>
                                <?php endif; ?>
                            </dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"
    ></script>
</body>
</html>
