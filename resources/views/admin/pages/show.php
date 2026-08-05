<?php
$typeLabels = ['page' => 'Standard page', 'news' => 'News article', 'notice' => 'Notice', 'policy' => 'Policy page'];
$statusLabels = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published', 'archived' => 'Archived'];
$statusClasses = ['draft' => 'text-bg-secondary', 'scheduled' => 'text-bg-info', 'published' => 'text-bg-success', 'archived' => 'text-bg-dark'];
?>
<section aria-labelledby="cms-page-detail-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2"><?= $e($typeLabels[$page['contentType']] ?? 'Structured content') ?></p>
            <h1 id="cms-page-detail-heading" class="h2 mb-2"><?= $e($page['title']) ?></h1>
            <p class="text-secondary mb-0"><code>/<?= $e($page['slug']) ?></code></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($page['status'] === 'published' && empty($page['expired'])): ?><a class="btn btn-outline-primary" href="/<?= $e($page['slug']) ?>" target="_blank" rel="noopener">View public page</a><?php endif; ?>
            <a class="btn btn-outline-secondary" href="/admin/pages">Back</a>
            <a class="btn btn-primary" href="/admin/pages/<?= $e($page['id']) ?>/edit">Edit</a>
        </div>
    </div>

    <div id="cms-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card admin-panel mb-4"><div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <h2 class="h5 mb-0">Content source</h2>
                    <span class="badge text-bg-light border"><?= $e($typeLabels[$page['contentType']] ?? ucfirst((string) $page['contentType'])) ?></span>
                </div>
                <?php if ($page['summary'] !== null): ?><p class="lead text-secondary"><?= $e($page['summary']) ?></p><?php endif; ?>
                <pre class="mb-0 text-wrap" style="white-space: pre-wrap"><?= $e($page['content']) ?></pre>
            </div></div>

            <div class="card admin-panel"><div class="card-body p-4">
                <h2 class="h5">Search and social sharing</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Meta title</dt><dd class="col-sm-8"><?= $page['metaTitle'] === null ? '—' : $e($page['metaTitle']) ?></dd>
                    <dt class="col-sm-4">Meta description</dt><dd class="col-sm-8"><?= $page['metaDescription'] === null ? '—' : $e($page['metaDescription']) ?></dd>
                    <dt class="col-sm-4">Social title</dt><dd class="col-sm-8"><?= $page['socialTitle'] === null ? 'Uses meta title' : $e($page['socialTitle']) ?></dd>
                    <dt class="col-sm-4">Social description</dt><dd class="col-sm-8"><?= $page['socialDescription'] === null ? 'Uses meta description' : $e($page['socialDescription']) ?></dd>
                    <dt class="col-sm-4">Search indexing</dt><dd class="col-sm-8"><?= !empty($page['seoNoindex']) ? 'Noindex enabled' : 'Indexing allowed' ?></dd>
                    <dt class="col-sm-4">Featured image</dt><dd class="col-sm-8"><?= $page['featuredMediaId'] === null ? '—' : 'Media #' . $e($page['featuredMediaId']) ?></dd>
                    <dt class="col-sm-4">Social image</dt><dd class="col-sm-8"><?= $page['socialMediaId'] === null ? 'Uses featured image' : 'Media #' . $e($page['socialMediaId']) ?></dd>
                </dl>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card admin-panel mb-4"><div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 mb-0">Publication</h2>
                    <span class="badge <?= $e($statusClasses[$page['status']] ?? 'text-bg-secondary') ?>"><?= $e($statusLabels[$page['status']] ?? ucfirst((string) $page['status'])) ?></span>
                </div>
                <dl class="mb-0">
                    <dt>Publication date</dt><dd><?= $page['publishedAt'] === null ? '—' : $e($page['publishedAt']) ?></dd>
                    <dt>Expiry</dt><dd><?= $page['expiresAt'] === null ? '—' : $e($page['expiresAt']) ?><?= !empty($page['expired']) ? ' (expired)' : '' ?></dd>
                    <dt>Featured</dt><dd><?= !empty($page['featured']) ? 'Yes' : 'No' ?></dd>
                    <dt>Pinned</dt><dd><?= !empty($page['pinned']) ? 'Yes' : 'No' ?></dd>
                    <?php if ($page['contentType'] === 'notice'): ?><dt>Notice style</dt><dd><?= $page['noticeStyle'] === null ? 'Default' : $e(ucfirst($page['noticeStyle'])) ?></dd><?php endif; ?>
                    <dt>Updated</dt><dd><?= $e($page['updatedAt']) ?></dd>
                </dl>
            </div></div>

            <div class="card admin-panel" id="publication-actions"><div class="card-body p-4">
                <h2 class="h5 mb-3">Publication actions</h2>
                <div class="d-grid gap-3">
                    <?php foreach ($page['transitions'] as $status): ?>
                        <form method="post" action="/api/v1/admin/pages/<?= $e($page['id']) ?>/status" data-cms-status-form data-status="<?= $e($status) ?>">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <?php if ($status === 'scheduled'): ?>
                                <label class="form-label" for="schedule-page-<?= $e($page['id']) ?>">Publish on</label>
                                <input class="form-control mb-2" id="schedule-page-<?= $e($page['id']) ?>" name="publish_at" type="datetime-local" value="<?= $e($page['publishAtInput']) ?>" required>
                            <?php endif; ?>
                            <button class="btn <?= $status === 'published' ? 'btn-success' : ($status === 'scheduled' ? 'btn-outline-info' : 'btn-outline-secondary') ?> w-100" type="submit">
                                <?= $e(match ($status) { 'published' => 'Publish now', 'scheduled' => $page['status'] === 'scheduled' ? 'Reschedule' : 'Schedule publication', 'draft' => 'Move to draft', 'archived' => 'Archive', default => ucfirst($status) }) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                    <?php if ($page['transitions'] === []): ?><p class="text-secondary mb-0">No publication action is currently available.</p><?php endif; ?>
                </div>
                <p class="small text-secondary mt-3 mb-0">Scheduled content stays private until the selected local date and time. Expired notices stop being public automatically.</p>
            </div></div>
        </div>
    </div>
    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required for publication status changes.</p></noscript>
</section>
<script src="/assets/js/admin-cms.js" defer></script>
