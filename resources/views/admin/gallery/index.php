<section aria-labelledby="gallery-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Content catalogue</p>
            <h1 id="gallery-heading" class="h2 mb-2">Gallery</h1>
            <p class="text-secondary mb-0">
                Manage image metadata, ordering, operational state, and publication independently.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="/admin/gallery/categories">Categories</a>
            <a class="btn btn-primary" href="/admin/gallery/create">Upload image</a>
        </div>
    </div>

    <div
        id="gallery-action-message"
        class="alert d-none"
        role="alert"
        aria-live="polite"
        tabindex="-1"
    ></div>

    <div
        data-gallery-reorder-panel
        data-action="/api/v1/admin/gallery/reorder"
        data-csrf="<?= $e($csrfToken) ?>"
    >
        <div class="card admin-panel">
            <div class="card-body p-0">
                <?php if ($items === []): ?>
                    <div class="p-4">
                        <p class="mb-0 text-secondary">No gallery items have been configured.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Order</th>
                                    <th scope="col">Active</th>
                                    <th scope="col">Published</th>
                                    <th scope="col">Updated</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block"><?= $e($item['title']) ?></strong>
                                            <code class="small"><?= $e($item['filePath']) ?></code>
                                            <?php if ($item['publiclyVisible']): ?>
                                                <span class="badge text-bg-success ms-1">Public</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $e($item['category']) ?></td>
                                        <td style="min-width: 7rem;">
                                            <label class="visually-hidden" for="gallery-order-<?= $e($item['id']) ?>">
                                                Sort order for <?= $e($item['title']) ?>
                                            </label>
                                            <input
                                                class="form-control form-control-sm gallery-sort-order"
                                                id="gallery-order-<?= $e($item['id']) ?>"
                                                type="number"
                                                min="0"
                                                max="4294967295"
                                                step="1"
                                                value="<?= $e($item['sortOrder']) ?>"
                                                data-gallery-item-id="<?= $e($item['id']) ?>"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <span class="badge <?= $item['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                <?= $item['active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $item['published'] ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                                                <?= $item['published'] ? 'Published' : 'Unpublished' ?>
                                            </span>
                                        </td>
                                        <td><time datetime="<?= $e($item['updatedAt']) ?>"><?= $e($item['updatedAt']) ?></time></td>
                                        <td>
                                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                                <a
                                                    class="btn btn-sm btn-outline-primary"
                                                    href="/admin/gallery/<?= $e($item['id']) ?>/edit"
                                                >Edit</a>

                                                <form
                                                    method="post"
                                                    action="/api/v1/admin/gallery/<?= $e($item['id']) ?>/active"
                                                    data-gallery-action-form
                                                    data-api-method="PATCH"
                                                    data-payload-field="is_active"
                                                    data-payload-value="<?= $item['active'] ? 'false' : 'true' ?>"
                                                >
                                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                        <?= $item['active'] ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>

                                                <form
                                                    method="post"
                                                    action="/api/v1/admin/gallery/<?= $e($item['id']) ?>/publication"
                                                    data-gallery-action-form
                                                    data-api-method="PATCH"
                                                    data-payload-field="is_published"
                                                    data-payload-value="<?= $item['published'] ? 'false' : 'true' ?>"
                                                >
                                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                        <?= $item['published'] ? 'Unpublish' : 'Publish' ?>
                                                    </button>
                                                </form>

                                                <?php if ($item['active'] || $item['published']): ?>
                                                    <form
                                                        method="post"
                                                        action="/api/v1/admin/gallery/<?= $e($item['id']) ?>"
                                                        data-gallery-action-form
                                                        data-api-method="DELETE"
                                                        data-confirm="Deactivate and unpublish this gallery item? The record and private image will be retained."
                                                    >
                                                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($items !== []): ?>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end">
                    <button class="btn btn-primary" type="button" data-gallery-reorder-button>Save ordering</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <noscript>
        <p class="alert alert-warning mt-3 mb-0">
            JavaScript is required for gallery mutations. Browsing and editing forms remain available.
        </p>
    </noscript>
</section>

<script src="/assets/js/admin-gallery.js" defer></script>
