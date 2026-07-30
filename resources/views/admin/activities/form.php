<section aria-labelledby="activity-form-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Activity catalogue</p>
            <h1 id="activity-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
            <p class="text-secondary mb-0">
                Use only the approved Version 1 content fields. Activity images, categories, duration, and booking are not included.
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="/admin/activities">Back to activities</a>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card admin-panel">
                <div class="card-body">
                    <div
                        id="activity-form-message"
                        class="alert d-none"
                        role="alert"
                        aria-live="polite"
                        tabindex="-1"
                    ></div>

                    <form
                        method="post"
                        action="<?= $e($action) ?>"
                        data-activity-editor-form
                        data-api-method="<?= $e($apiMethod) ?>"
                        <?= $creating ? 'data-creating="true"' : '' ?>
                    >
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                        <div class="mb-3">
                            <label class="form-label" for="activity-title">Title</label>
                            <input
                                class="form-control"
                                id="activity-title"
                                name="title"
                                type="text"
                                maxlength="200"
                                value="<?= $e($values['title']) ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="activity-slug">Slug</label>
                            <input
                                class="form-control"
                                id="activity-slug"
                                name="slug"
                                type="text"
                                maxlength="200"
                                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                value="<?= $e($values['slug']) ?>"
                                aria-describedby="activity-slug-help"
                                required
                            >
                            <div id="activity-slug-help" class="form-text">
                                Lowercase letters, numbers, and single hyphens only. The slug must be unique.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="activity-description">Description</label>
                            <textarea
                                class="form-control"
                                id="activity-description"
                                name="description"
                                rows="8"
                                required
                            ><?= $e($values['description']) ?></textarea>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label" for="activity-price">Price (NPR)</label>
                                <input
                                    class="form-control"
                                    id="activity-price"
                                    name="price"
                                    type="text"
                                    inputmode="decimal"
                                    pattern="(?:0|[1-9][0-9]{0,9})\.[0-9]{2}"
                                    value="<?= $e($values['price']) ?>"
                                    aria-describedby="activity-price-help"
                                    required
                                >
                                <div id="activity-price-help" class="form-text">
                                    Use exactly two decimal places, including <code>0.00</code> for a free activity.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="activity-sort-order">Sort order</label>
                                <input
                                    class="form-control"
                                    id="activity-sort-order"
                                    name="sort_order"
                                    type="number"
                                    min="0"
                                    max="4294967295"
                                    step="1"
                                    value="<?= $e($values['sortOrder']) ?>"
                                    required
                                >
                            </div>
                        </div>

                        <?php if ($creating): ?>
                            <fieldset class="mb-4">
                                <legend class="h6">Initial visibility</legend>
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        id="activity-active"
                                        name="is_active"
                                        type="checkbox"
                                        <?= $values['active'] ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="activity-active">Active</label>
                                </div>
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        id="activity-published"
                                        name="is_published"
                                        type="checkbox"
                                        <?= $values['published'] ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="activity-published">Published</label>
                                </div>
                                <p class="form-text mb-0 mt-2">
                                    Public visibility requires both controls. They can be changed independently from the activity list.
                                </p>
                            </fieldset>
                        <?php else: ?>
                            <div class="alert alert-light border" role="note">
                                Current state:
                                <strong><?= $values['active'] ? 'Active' : 'Inactive' ?></strong>
                                and
                                <strong><?= $values['published'] ? 'Published' : 'Unpublished' ?></strong>.
                                Change these controls independently from the activity list.
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">
                                <?= $creating ? 'Create activity' : 'Save activity' ?>
                            </button>
                            <a class="btn btn-outline-secondary" href="/admin/activities">Cancel</a>
                        </div>
                    </form>

                    <noscript>
                        <p class="alert alert-warning mt-3 mb-0">
                            JavaScript is required to submit this activity form safely.
                        </p>
                    </noscript>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/assets/js/admin-activities.js" defer></script>
