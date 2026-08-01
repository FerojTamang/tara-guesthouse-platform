<section aria-labelledby="pages-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Content management</p>
            <h1 id="pages-heading" class="h2 mb-2">Pages</h1>
            <p class="text-secondary mb-0">Create drafts, edit structured content, and use the approved publication workflow.</p>
        </div>
        <a class="btn btn-primary" href="/admin/pages/create">Create page</a>
    </div>

    <div id="cms-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($pages === []): ?>
                <div class="p-4"><p class="mb-0 text-secondary">No CMS pages have been created.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Page</th><th>Status</th><th>Published</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td><strong class="d-block"><?= $e($page['title']) ?></strong><code class="small"><?= $e($page['slug']) ?></code></td>
                                <td><span class="badge <?= $page['status'] === 'published' ? 'text-bg-success' : ($page['status'] === 'archived' ? 'text-bg-dark' : 'text-bg-secondary') ?>"><?= $e(ucfirst($page['status'])) ?></span></td>
                                <td><?= $page['publishedAt'] === null ? '—' : $e($page['publishedAt']) ?></td>
                                <td><?= $e($page['updatedAt']) ?></td>
                                <td>
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        <a class="btn btn-sm btn-outline-secondary" href="/admin/pages/<?= $e($page['id']) ?>">View</a>
                                        <a class="btn btn-sm btn-outline-primary" href="/admin/pages/<?= $e($page['id']) ?>/edit">Edit</a>
                                        <?php foreach ($page['transitions'] as $status): ?>
                                            <form method="post" action="/api/v1/admin/pages/<?= $e($page['id']) ?>/status" data-cms-status-form data-status="<?= $e($status) ?>">
                                                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $e(ucfirst($status)) ?></button>
                                            </form>
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
    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required for status transitions.</p></noscript>
</section>
<script src="/assets/js/admin-cms.js" defer></script>
