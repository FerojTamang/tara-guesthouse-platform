<section aria-labelledby="cms-page-form-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Structured page</p>
            <h1 id="cms-page-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
            <p class="text-secondary mb-0">
                Content format: <?= $e($contentFormat) ?>. HTML markup is not accepted under this approved policy.
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/pages">Back to pages</a>
    </div>

    <div id="cms-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <div class="card admin-panel"><div class="card-body">
        <form method="post" action="<?= $e($action) ?>" data-cms-page-form data-api-method="<?= $e($apiMethod) ?>">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
            <div class="mb-3">
                <label class="form-label" for="cms-page-title">Title</label>
                <input class="form-control" id="cms-page-title" name="title" type="text" maxlength="200" value="<?= $e($values['title']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="cms-page-slug">Slug</label>
                <input class="form-control" id="cms-page-slug" name="slug" type="text" maxlength="200" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= $e($values['slug']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="cms-page-content">Content</label>
                <textarea class="form-control" id="cms-page-content" name="content" rows="14" maxlength="<?= $e($contentMaximumLength) ?>" required><?= $e($values['content']) ?></textarea>
                <div class="form-text">Maximum <?= $e($contentMaximumLength) ?> characters.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="cms-page-meta-title">Meta title</label>
                <input class="form-control" id="cms-page-meta-title" name="meta_title" type="text" maxlength="255" value="<?= $e($values['metaTitle']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="cms-page-meta-description">Meta description</label>
                <textarea class="form-control" id="cms-page-meta-description" name="meta_description" rows="3" maxlength="500"><?= $e($values['metaDescription']) ?></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Save page</button>
        </form>
    </div></div>
    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required to save pages.</p></noscript>
</section>
<script src="/assets/js/admin-cms.js" defer></script>
