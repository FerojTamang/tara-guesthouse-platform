<section aria-labelledby="activities-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Content catalogue</p>
            <h1 id="activities-heading" class="h2 mb-2">Activities</h1>
            <p class="text-secondary mb-0">
                Manage activity content, operational availability, publication, and public ordering independently.
            </p>
        </div>
        <a class="btn btn-primary" href="/admin/activities/create">Create activity</a>
    </div>

    <div
        id="activity-action-message"
        class="alert d-none"
        role="alert"
        aria-live="polite"
        tabindex="-1"
    ></div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($activities === []): ?>
                <div class="p-4">
                    <p class="mb-0 text-secondary">No activities have been configured.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Activity</th>
                                <th scope="col">Price</th>
                                <th scope="col">Order</th>
                                <th scope="col">Active</th>
                                <th scope="col">Published</th>
                                <th scope="col">Updated</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td>
                                        <strong class="d-block"><?= $e($activity['title']) ?></strong>
                                        <code class="small"><?= $e($activity['slug']) ?></code>
                                        <?php if ($activity['publiclyVisible']): ?>
                                            <span class="badge text-bg-success ms-1">Public</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $e($activity['price']) ?></td>
                                    <td><?= $e($activity['sortOrder']) ?></td>
                                    <td>
                                        <span class="badge <?= $activity['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $activity['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $activity['published'] ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                                            <?= $activity['published'] ? 'Published' : 'Unpublished' ?>
                                        </span>
                                    </td>
                                    <td><?= $e($activity['updatedAt']) ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a
                                                class="btn btn-sm btn-outline-primary"
                                                href="/admin/activities/<?= $e($activity['id']) ?>/edit"
                                            >Edit</a>

                                            <form
                                                method="post"
                                                action="/api/v1/admin/activities/<?= $e($activity['id']) ?>/active"
                                                data-activity-action-form
                                                data-api-method="PATCH"
                                                data-payload-field="is_active"
                                                data-payload-value="<?= $activity['active'] ? 'false' : 'true' ?>"
                                            >
                                                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                    <?= $activity['active'] ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>

                                            <form
                                                method="post"
                                                action="/api/v1/admin/activities/<?= $e($activity['id']) ?>/publication"
                                                data-activity-action-form
                                                data-api-method="PATCH"
                                                data-payload-field="is_published"
                                                data-payload-value="<?= $activity['published'] ? 'false' : 'true' ?>"
                                            >
                                                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                    <?= $activity['published'] ? 'Unpublish' : 'Publish' ?>
                                                </button>
                                            </form>

                                            <?php if ($activity['active'] || $activity['published']): ?>
                                                <form
                                                    method="post"
                                                    action="/api/v1/admin/activities/<?= $e($activity['id']) ?>"
                                                    data-activity-action-form
                                                    data-api-method="DELETE"
                                                    data-confirm="Deactivate and unpublish this activity? The record will be retained."
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
    </div>

    <noscript>
        <p class="alert alert-warning mt-3 mb-0">
            JavaScript is required for activity mutations. Browsing and editing forms remain available.
        </p>
    </noscript>
</section>

<script src="/assets/js/admin-activities.js" defer></script>
