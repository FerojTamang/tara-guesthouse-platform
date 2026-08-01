<?php
$acceptedMimeTypes = '';
$uploadPolicySummary = '';

if ($creating && is_array($uploadConfiguration)) {
    $allowedMimeExtensions = $uploadConfiguration['allowed_mime_extensions'] ?? [];
    $maximumBytes = $uploadConfiguration['maximum_file_size_bytes'] ?? null;
    $minimumWidth = $uploadConfiguration['minimum_width_pixels'] ?? null;
    $minimumHeight = $uploadConfiguration['minimum_height_pixels'] ?? null;
    $maximumWidth = $uploadConfiguration['maximum_width_pixels'] ?? null;
    $maximumHeight = $uploadConfiguration['maximum_height_pixels'] ?? null;

    if (
        is_array($allowedMimeExtensions)
        && $allowedMimeExtensions !== []
        && is_int($maximumBytes)
        && is_int($minimumWidth)
        && is_int($minimumHeight)
        && is_int($maximumWidth)
        && is_int($maximumHeight)
    ) {
        $acceptedMimeTypes = implode(',', array_keys($allowedMimeExtensions));
        $uploadPolicySummary = sprintf(
            'Maximum %s bytes; dimensions %sx%s to %sx%s pixels.',
            number_format($maximumBytes),
            number_format($minimumWidth),
            number_format($minimumHeight),
            number_format($maximumWidth),
            number_format($maximumHeight),
        );
    }
}
?>
<section aria-labelledby="gallery-form-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Gallery catalogue</p>
            <h1 id="gallery-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
            <p class="text-secondary mb-0">
                Alternative text is required. Publication and operational state remain independent controls.
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/gallery">Back to gallery</a>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card admin-panel">
                <div class="card-body">
                    <div
                        id="gallery-form-message"
                        class="alert d-none"
                        role="alert"
                        aria-live="polite"
                        tabindex="-1"
                    ></div>

                    <form
                        action="<?= $e($action) ?>"
                        method="post"
                        <?= $creating ? 'enctype="multipart/form-data"' : '' ?>
                        data-gallery-item-form
                        data-api-method="<?= $e($apiMethod) ?>"
                        <?= $creating ? 'data-creating="true"' : '' ?>
                    >
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                        <?php if ($creating): ?>
                            <div class="mb-3">
                                <label class="form-label" for="gallery-image">Image</label>
                                <input
                                    class="form-control"
                                    id="gallery-image"
                                    name="image"
                                    type="file"
                                    accept="<?= $e($acceptedMimeTypes) ?>"
                                    required
                                >
                                <div class="form-text">
                                    <?= $e($uploadPolicySummary) ?> The server also verifies format and full decodability before private storage.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border" role="note">
                                Stored private path: <code><?= $e($values['filePath']) ?></code>.
                                Version 1 metadata editing does not replace uploaded bytes.
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label" for="gallery-title">Title</label>
                            <input
                                class="form-control"
                                id="gallery-title"
                                name="title"
                                maxlength="200"
                                value="<?= $e($values['title']) ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="gallery-category">Category</label>
                            <select class="form-select" id="gallery-category" name="gallery_category_id">
                                <option value="">Uncategorized</option>
                                <?php foreach ($categories as $category): ?>
                                    <option
                                        value="<?= $e($category['id']) ?>"
                                        <?= (string) $category['id'] === $values['categoryId'] ? 'selected' : '' ?>
                                    >
                                        <?= $e($category['name']) ?><?= $category['active'] ? '' : ' (inactive)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="gallery-caption">Caption</label>
                            <textarea
                                class="form-control"
                                id="gallery-caption"
                                name="caption"
                                rows="4"
                            ><?= $e($values['caption']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="gallery-alt-text">Alternative text</label>
                            <textarea
                                class="form-control"
                                id="gallery-alt-text"
                                name="alt_text"
                                maxlength="500"
                                rows="3"
                                aria-describedby="gallery-alt-help"
                                required
                            ><?= $e($values['alternativeText']) ?></textarea>
                            <div id="gallery-alt-help" class="form-text">
                                Describe the image content and purpose for guests who cannot see it.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="gallery-sort-order">Sort order</label>
                            <input
                                class="form-control"
                                id="gallery-sort-order"
                                name="sort_order"
                                type="number"
                                min="0"
                                max="4294967295"
                                step="1"
                                value="<?= $e($values['sortOrder']) ?>"
                                required
                            >
                        </div>

                        <fieldset class="mb-4">
                            <legend class="h6">Homepage preview</legend>
                            <div class="form-check mb-3">
                                <input class="form-check-input" id="gallery-homepage-featured" name="is_homepage_featured" type="checkbox" <?= $values['homepageFeatured'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="gallery-homepage-featured">Show on homepage</label>
                            </div>
                            <label class="form-label" for="gallery-homepage-sort-order">Homepage display order</label>
                            <input class="form-control" id="gallery-homepage-sort-order" name="homepage_sort_order" type="number" min="0" max="4294967295" step="1" value="<?= $e($values['homepageSortOrder']) ?>" required>
                            <div class="form-text">Only active, published images appear. The homepage shows at most six selected images; lower values appear first.</div>
                        </fieldset>

                        <?php if ($creating): ?>
                            <fieldset class="mb-4">
                                <legend class="h6">Initial visibility</legend>
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        id="gallery-active"
                                        name="is_active"
                                        type="checkbox"
                                        <?= $values['active'] ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="gallery-active">Active</label>
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        id="gallery-published"
                                        name="is_published"
                                        type="checkbox"
                                        <?= $values['published'] ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="gallery-published">Published</label>
                                </div>
                            </fieldset>
                        <?php else: ?>
                            <div class="alert alert-light border" role="note">
                                Current state:
                                <strong><?= $values['active'] ? 'Active' : 'Inactive' ?></strong>
                                and
                                <strong><?= $values['published'] ? 'Published' : 'Unpublished' ?></strong>.
                                Change these controls independently from the gallery list.
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">
                                <?= $creating ? 'Upload gallery item' : 'Save gallery item' ?>
                            </button>
                            <a class="btn btn-outline-secondary" href="/admin/gallery">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/assets/js/admin-gallery.js" defer></script>
