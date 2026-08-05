<?php
$mainRoots = [];
$mainChildren = [];
$footerItems = [];
foreach ($items as $item) {
    if ($item['location'] === 'footer') {
        $footerItems[] = $item;
    } elseif ($item['parentId'] === null) {
        $mainRoots[] = $item;
    } else {
        $mainChildren[$item['parentId']][] = $item;
    }
}
$routeLabels = [];
foreach ($routes as $route) {
    $routeLabels[$route['key']] = $route['label'];
}
$targetLabel = static function (array $item) use ($routeLabels): string {
    if ($item['linkType'] === 'group') {
        return 'Dropdown group';
    }
    if ($item['linkType'] === 'route') {
        return 'Built-in: ' . ($routeLabels[$item['routeKey']] ?? $item['routeKey'] ?? 'Unknown');
    }
    if (is_array($item['page'])) {
        return 'Page: ' . $item['page']['title'];
    }
    return 'Page unavailable';
};
?>
<section aria-labelledby="navigation-heading" data-navigation-manager>
    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Website structure</p>
            <h1 id="navigation-heading" class="h2 mb-2">Navigation</h1>
            <p class="text-secondary mb-0">
                Choose which published pages appear in the shared navbar or footer and control their exact order.
            </p>
        </div>
        <a class="btn btn-outline-primary" href="/admin/pages">Manage pages</a>
    </div>

    <div id="navigation-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <div class="alert alert-info" role="note">
        Publishing a page makes its URL public. Adding it here controls whether visitors can discover it from the navbar or footer.
        Draft and archived pages are automatically hidden from public navigation.
    </div>

    <div class="card admin-panel mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1">Add navigation item</h2>
            <p class="text-secondary small mb-0">Add a built-in destination, CMS page, or dropdown group.</p>
        </div>
        <div class="card-body">
            <form action="/api/v1/admin/navigation" method="post" data-navigation-form data-api-method="POST">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="navigation-label">Navigation label</label>
                        <input class="form-control" id="navigation-label" name="label" maxlength="80" required placeholder="About">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="navigation-location">Location</label>
                        <select class="form-select" id="navigation-location" name="location" required data-navigation-location>
                            <option value="main">Main navbar</option>
                            <option value="footer">Footer</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="navigation-type">Destination type</label>
                        <select class="form-select" id="navigation-type" name="link_type" required data-navigation-type>
                            <option value="page">CMS page</option>
                            <option value="route">Built-in page</option>
                            <option value="group">Dropdown group</option>
                        </select>
                    </div>
                    <div class="col-md-6" data-navigation-route-field hidden>
                        <label class="form-label" for="navigation-route">Built-in destination</label>
                        <select class="form-select" id="navigation-route" name="route_key">
                            <?php foreach ($routes as $route): ?>
                                <option value="<?= $e($route['key']) ?>"><?= $e($route['label']) ?> — <?= $e($route['path']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" data-navigation-page-field>
                        <label class="form-label" for="navigation-page">CMS page</label>
                        <select class="form-select" id="navigation-page" name="page_id">
                            <option value="">Choose a page</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?= $e($page['id']) ?>">
                                    <?= $e($page['title']) ?> (<?= $e($page['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" data-navigation-parent-field>
                        <label class="form-label" for="navigation-parent">Place inside dropdown</label>
                        <select class="form-select" id="navigation-parent" name="parent_id">
                            <option value="">No — top-level navbar item</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= $e($group['id']) ?>"><?= $e($group['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary mt-3" type="submit">Add navigation item</button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card admin-panel h-100">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-1">Main navbar</h2>
                    <p class="text-secondary small mb-0">Use Move up and Move down to choose the exact public order.</p>
                </div>
                <div class="card-body">
                    <?php if ($mainRoots === []): ?>
                        <p class="text-secondary mb-0">No main navigation items are configured.</p>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($mainRoots as $item): ?>
                                <?= $partial('components.admin-navigation-item', [
                                    'item' => $item,
                                    'children' => $mainChildren[$item['id']] ?? [],
                                    'pages' => $pages,
                                    'groups' => $groups,
                                    'routes' => $routes,
                                    'csrfToken' => $csrfToken,
                                    'targetLabel' => $targetLabel($item),
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card admin-panel h-100">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-1">Footer links</h2>
                    <p class="text-secondary small mb-0">Optional links shown under “Plan your stay”.</p>
                </div>
                <div class="card-body">
                    <?php if ($footerItems === []): ?>
                        <p class="text-secondary mb-0">No additional footer links are configured.</p>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($footerItems as $item): ?>
                                <?= $partial('components.admin-navigation-item', [
                                    'item' => $item,
                                    'children' => [],
                                    'pages' => $pages,
                                    'groups' => $groups,
                                    'routes' => $routes,
                                    'csrfToken' => $csrfToken,
                                    'targetLabel' => $targetLabel($item),
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required to update navigation.</p></noscript>
</section>
<script src="/assets/js/admin-navigation.js" defer></script>
