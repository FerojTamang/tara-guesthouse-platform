<article class="border rounded-3 p-3<?= $item['active'] ? '' : ' bg-light opacity-75' ?>" data-navigation-item="<?= $e($item['id']) ?>">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <strong><?= $e($item['label']) ?></strong>
                <span class="badge text-bg-light border"><?= $e($item['location'] === 'main' ? 'Navbar' : 'Footer') ?></span>
                <span class="badge <?= $item['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <?= $item['active'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>
            <span class="small text-secondary"><?= $e($targetLabel) ?></span>
            <?php if (is_array($item['page']) && $item['page']['status'] !== 'published'): ?>
                <span class="small text-warning-emphasis d-block mt-1">
                    This link stays hidden publicly until the page is published.
                </span>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-navigation-move="up" data-endpoint="/api/v1/admin/navigation/<?= $e($item['id']) ?>/move" data-csrf="<?= $e($csrfToken) ?>">Move up</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-navigation-move="down" data-endpoint="/api/v1/admin/navigation/<?= $e($item['id']) ?>/move" data-csrf="<?= $e($csrfToken) ?>">Move down</button>
            <button class="btn btn-sm btn-outline-<?= $item['active'] ? 'danger' : 'success' ?>" type="button" data-navigation-active="<?= $item['active'] ? 'false' : 'true' ?>" data-endpoint="/api/v1/admin/navigation/<?= $e($item['id']) ?>/active" data-csrf="<?= $e($csrfToken) ?>">
                <?= $item['active'] ? 'Disable' : 'Enable' ?>
            </button>
        </div>
    </div>

    <details class="mt-3">
        <summary class="small fw-semibold">Edit item</summary>
        <form class="mt-3" action="/api/v1/admin/navigation/<?= $e($item['id']) ?>" method="post" data-navigation-form data-api-method="PUT">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Navigation label</label>
                    <input class="form-control" name="label" value="<?= $e($item['label']) ?>" maxlength="80" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <select class="form-select" name="location" data-navigation-location>
                        <option value="main"<?= $item['location'] === 'main' ? ' selected' : '' ?>>Main navbar</option>
                        <option value="footer"<?= $item['location'] === 'footer' ? ' selected' : '' ?>>Footer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="link_type" data-navigation-type>
                        <option value="page"<?= $item['linkType'] === 'page' ? ' selected' : '' ?>>CMS page</option>
                        <option value="route"<?= $item['linkType'] === 'route' ? ' selected' : '' ?>>Built-in page</option>
                        <option value="group"<?= $item['linkType'] === 'group' ? ' selected' : '' ?>>Dropdown group</option>
                    </select>
                </div>
                <div class="col-md-6" data-navigation-route-field<?= $item['linkType'] === 'route' ? '' : ' hidden' ?>>
                    <label class="form-label">Built-in destination</label>
                    <select class="form-select" name="route_key">
                        <?php foreach ($routes as $route): ?>
                            <option value="<?= $e($route['key']) ?>"<?= $item['routeKey'] === $route['key'] ? ' selected' : '' ?>><?= $e($route['label']) ?> — <?= $e($route['path']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" data-navigation-page-field<?= $item['linkType'] === 'page' ? '' : ' hidden' ?>>
                    <label class="form-label">CMS page</label>
                    <select class="form-select" name="page_id">
                        <option value="">Choose a page</option>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?= $e($page['id']) ?>"<?= $item['pageId'] === $page['id'] ? ' selected' : '' ?>><?= $e($page['title']) ?> (<?= $e($page['status']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" data-navigation-parent-field<?= $item['location'] === 'main' && $item['linkType'] !== 'group' ? '' : ' hidden' ?>>
                    <label class="form-label">Dropdown group</label>
                    <select class="form-select" name="parent_id">
                        <option value="">Top-level navbar item</option>
                        <?php foreach ($groups as $group): ?>
                            <?php if ($group['id'] !== $item['id']): ?>
                                <option value="<?= $e($group['id']) ?>"<?= $item['parentId'] === $group['id'] ? ' selected' : '' ?>><?= $e($group['label']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary btn-sm mt-3" type="submit">Save changes</button>
        </form>
    </details>

    <?php if ($children !== []): ?>
        <div class="mt-3 ps-3 border-start">
            <p class="small fw-semibold text-secondary mb-2">Dropdown items</p>
            <div class="d-grid gap-2">
                <?php foreach ($children as $child): ?>
                    <?php
                    $childTarget = $child['linkType'] === 'route'
                        ? 'Built-in route'
                        : (is_array($child['page']) ? 'Page: ' . $child['page']['title'] : 'Page unavailable');
                    ?>
                    <?= $partial('components.admin-navigation-item', [
                        'item' => $child,
                        'children' => [],
                        'pages' => $pages,
                        'groups' => $groups,
                        'routes' => $routes,
                        'csrfToken' => $csrfToken,
                        'targetLabel' => $childTarget,
                    ]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</article>
