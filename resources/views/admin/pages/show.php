<section aria-labelledby="cms-page-detail-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Structured page</p>
            <h1 id="cms-page-detail-heading" class="h2 mb-2"><?= $e($page['title']) ?></h1>
            <p class="text-secondary mb-0"><code><?= $e($page['slug']) ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="/admin/pages">Back</a>
            <a class="btn btn-primary" href="/admin/pages/<?= $e($page['id']) ?>/edit">Edit</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card admin-panel"><div class="card-body">
                <h2 class="h5">Content</h2>
                <pre class="mb-0 text-wrap" style="white-space: pre-wrap"><?= $e($page['content']) ?></pre>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card admin-panel"><div class="card-body">
                <h2 class="h5">Publication</h2>
                <dl class="mb-0">
                    <dt>Status</dt><dd><?= $e(ucfirst($page['status'])) ?></dd>
                    <dt>Published at</dt><dd><?= $page['publishedAt'] === null ? '—' : $e($page['publishedAt']) ?></dd>
                    <dt>Updated at</dt><dd><?= $e($page['updatedAt']) ?></dd>
                    <dt>Meta title</dt><dd><?= $page['metaTitle'] === null ? '—' : $e($page['metaTitle']) ?></dd>
                    <dt>Meta description</dt><dd><?= $page['metaDescription'] === null ? '—' : $e($page['metaDescription']) ?></dd>
                </dl>
            </div></div>
        </div>
    </div>
</section>
