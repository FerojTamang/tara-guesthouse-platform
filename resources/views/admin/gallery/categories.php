<section aria-labelledby="gallery-categories-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Gallery catalogue</p>
            <h1 id="gallery-categories-heading" class="h2 mb-2">Gallery categories</h1>
            <p class="text-secondary mb-0">
                Inactive categories remain assignable for administration but suppress their assigned items from public results.
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/gallery">Back to gallery</a>
    </div>

    <div
        id="gallery-category-message"
        class="alert d-none"
        role="alert"
        aria-live="polite"
        tabindex="-1"
    ></div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card admin-panel">
                <div class="card-body">
                    <h2 class="h5">Create category</h2>
                    <form
                        action="/api/v1/admin/gallery-categories"
                        method="post"
                        data-gallery-category-form
                        data-api-method="POST"
                    >
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="new-gallery-category-name">Name</label>
                            <input
                                class="form-control"
                                id="new-gallery-category-name"
                                name="name"
                                maxlength="150"
                                required
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new-gallery-category-slug">Slug</label>
                            <input
                                class="form-control"
                                id="new-gallery-category-slug"
                                name="slug"
                                maxlength="200"
                                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                required
                            >
                        </div>
                        <div class="form-check mb-3">
                            <input
                                class="form-check-input"
                                id="new-gallery-category-active"
                                name="is_active"
                                type="checkbox"
                                checked
                            >
                            <label class="form-check-label" for="new-gallery-category-active">Active</label>
                        </div>
                        <button class="btn btn-primary" type="submit">Create category</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <?php if ($categories === []): ?>
                <div class="card admin-panel"><div class="card-body text-secondary">No categories have been configured.</div></div>
            <?php else: ?>
                <div class="vstack gap-3">
                    <?php foreach ($categories as $category): ?>
                        <div class="card admin-panel">
                            <div class="card-body">
                                <form
                                    action="/api/v1/admin/gallery-categories/<?= $e($category['id']) ?>"
                                    method="post"
                                    data-gallery-category-form
                                    data-api-method="PUT"
                                >
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label" for="gallery-category-name-<?= $e($category['id']) ?>">Name</label>
                                            <input
                                                class="form-control"
                                                id="gallery-category-name-<?= $e($category['id']) ?>"
                                                name="name"
                                                maxlength="150"
                                                value="<?= $e($category['name']) ?>"
                                                required
                                            >
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="gallery-category-slug-<?= $e($category['id']) ?>">Slug</label>
                                            <input
                                                class="form-control"
                                                id="gallery-category-slug-<?= $e($category['id']) ?>"
                                                name="slug"
                                                maxlength="200"
                                                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                                value="<?= $e($category['slug']) ?>"
                                                required
                                            >
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check mb-2">
                                                <input
                                                    class="form-check-input"
                                                    id="gallery-category-active-<?= $e($category['id']) ?>"
                                                    name="is_active"
                                                    type="checkbox"
                                                    <?= $category['active'] ? 'checked' : '' ?>
                                                >
                                                <label class="form-check-label" for="gallery-category-active-<?= $e($category['id']) ?>">Active</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-md-end">
                                            <button class="btn btn-outline-primary" type="submit">Save</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script src="/assets/js/admin-gallery.js" defer></script>
