<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?></title>
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
                <a class="navbar-brand fw-semibold" href="/">Tara Guest House</a>
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
                        <li class="nav-item"><a class="nav-link" href="/rooms">Rooms</a></li>
                        <li class="nav-item"><a class="nav-link" href="/activities">Activities</a></li>
                        <li class="nav-item"><a class="nav-link" href="/availability">Availability</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>
    <main id="main-content" class="container py-4" tabindex="-1">
        <?= $content ?>
    </main>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"
    ></script>
</body>
</html>
