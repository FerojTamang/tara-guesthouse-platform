<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($title) ?></title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
    <a class="visually-hidden-focusable admin-skip-link" href="#admin-main-content">
        Skip to main content
    </a>

    <div class="admin-shell">
        <?= $partial('components.admin-sidebar', [
            'activeNavigation' => $activeNavigation,
        ]) ?>

        <div class="admin-workspace">
            <?= $partial('components.admin-topbar', [
                'pageTitle' => $pageTitle,
                'csrfToken' => $csrfToken,
            ]) ?>

            <main id="admin-main-content" class="admin-content" tabindex="-1">
                <?= $content ?>
            </main>
        </div>
    </div>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"
    ></script>
</body>
</html>
