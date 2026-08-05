<?php
$filters = is_array($filters ?? null) ? $filters : ['type' => '', 'status' => '', 'search' => ''];
$totalPageCount = is_int($totalPageCount ?? null) ? $totalPageCount : count($pages ?? []);
$typeLabels = ['page' => 'Standard page', 'news' => 'News article', 'notice' => 'Notice', 'policy' => 'Policy page'];
$statusLabels = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'archived' => 'Archived'];
$statusClasses = ['draft' => 'text-bg-secondary', 'scheduled' => 'text-bg-info', 'published' => 'text-bg-success', 'archived' => 'text-bg-dark'];
?>
<section aria-labelledby="pages-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Content management</p>
            <h1 id="pages-heading" class="h2 mb-2">Pages</h1>
            <p class="text-secondary mb-0">Create pages, news, notices and policies, then control when each item becomes public.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!empty($mediaLibraryAvailable)): ?>
                <a class="btn btn-outline-primary" href="/admin/media">Media library</a>
            <?php endif; ?>
            <a class="btn btn-outline-primary" href="/admin/navigation">Navigation</a>
            <a class="btn btn-primary" href="/admin/pages/create">Create content</a>
        </div>
    </div>

    <div id="cms-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <form class="card admin-panel mb-4" method="get" action="/admin/pages" aria-label="Filter content">
        <div class="card-body p-3 p-lg-4">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label" for="cms-page-search">Search title or URL</label>
                    <input class="form-control" id="cms-page-search" name="search" type="search" maxlength="100" value="<?= $e((string) ($filters['search'] ?? '')) ?>" placeholder="About, facilities, guest policy…">
                </div>
                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="cms-page-type-filter">Content type</label>
                    <select class="form-select" id="cms-page-type-filter" name="type">
                        <option value="">All types</option>
                        <?php foreach ($typeLabels as $value => $label): ?>
                            <option value="<?= $e($value) ?>"<?= ($filters['type'] ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="cms-page-status-filter">Status</label>
                    <select class="form-select" id="cms-page-status-filter" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= $e($value) ?>"<?= ($filters['status'] ?? '') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-2 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit">Filter</button>
                    <a class="btn btn-outline-secondary" href="/admin/pages" aria-label="Clear filters">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <p class="small text-secondary mb-0">Showing <?= $e(count($pages)) ?> of <?= $e($totalPageCount) ?> content items.</p>
    </div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($pages === []): ?>
                <div class="p-4">
                    <p class="mb-1 fw-semibold"><?= $totalPageCount === 0 ? 'No content has been created.' : 'No content matches these filters.' ?></p>
                    <p class="mb-0 text-secondary"><?= $totalPageCount === 0 ? 'Create your first draft to get started.' : 'Clear or adjust the filters to see more content.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Content</th><th>Type</th><th>Status</th><th>Publication</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td>
                                    <strong class="d-block"><?= $e($page['title']) ?></strong>
                                    <code class="small">/<?= $e($page['slug']) ?></code>
                                    <?php if (!empty($page['pinned']) || !empty($page['featured'])): ?>
                                        <span class="d-block small text-secondary mt-1"><?= !empty($page['pinned']) ? 'Pinned' : '' ?><?= !empty($page['pinned']) && !empty($page['featured']) ? ' · ' : '' ?><?= !empty($page['featured']) ? 'Featured' : '' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge text-bg-light border"><?= $e($typeLabels[$page['contentType']] ?? ucfirst((string) $page['contentType'])) ?></span></td>
                                <td><span class="badge <?= $e($statusClasses[$page['status']] ?? 'text-bg-secondary') ?>"><?= $e($statusLabels[$page['status']] ?? ucfirst((string) $page['status'])) ?></span></td>
                                <td>
                                    <?php if ($page['publishedAt'] === null): ?>—
                                    <?php elseif ($page['status'] === 'scheduled'): ?><span class="d-block">Scheduled for</span><small><?= $e($page['publishedAt']) ?></small>
                                    <?php else: ?><small><?= $e($page['publishedAt']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($page['expiresAt'] !== null): ?><span class="d-block small <?= !empty($page['expired']) ? 'text-danger' : 'text-secondary' ?> mt-1"><?= !empty($page['expired']) ? 'Expired ' : 'Expires ' ?><?= $e($page['expiresAt']) ?></span><?php endif; ?>
                                </td>
                                <td><small><?= $e($page['updatedAt']) ?></small></td>
                                <td>
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        <a class="btn btn-sm btn-outline-secondary" href="/admin/pages/<?= $e($page['id']) ?>">View</a>
                                        <a class="btn btn-sm btn-outline-primary" href="/admin/pages/<?= $e($page['id']) ?>/edit">Edit</a>
                                        <?php foreach ($page['transitions'] as $status): ?>
                                            <?php if ($status === 'scheduled'): ?>
                                                <a class="btn btn-sm btn-outline-info" href="/admin/pages/<?= $e($page['id']) ?>#publication-actions">Schedule</a>
                                            <?php else: ?>
                                                <form method="post" action="/api/v1/admin/pages/<?= $e($page['id']) ?>/status" data-cms-status-form data-status="<?= $e($status) ?>">
                                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $e($status === 'published' ? 'Publish now' : ucfirst($status)) ?></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required for publication status changes.</p></noscript>
</section>
<script src="/assets/js/admin-cms.js" defer></script>
