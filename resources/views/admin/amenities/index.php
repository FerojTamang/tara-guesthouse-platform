<link rel="stylesheet" href="/assets/css/adminAmenities.css">

<section aria-labelledby="amenities-heading">
    <div class="admin-page-heading d-flex flex-column flex-md-row align-items-md-start justify-content-md-between gap-3 mb-4">
        <div>
            <p class="admin-eyebrow mb-2">Room catalogue</p>
            <!-- <h1 id="amenities-heading" class="h2 mb-2">Amenities</h1> -->
            <p class="text-secondary mb-0">
                Manage the reusable amenities that may be assigned to rooms.
            </p>
        </div>
        <a class="btn btn-primary" href="/admin/amenities/create">Create amenity</a>
    </div>

    <?php if ($notice !== null): ?>
        <div class="alert alert-success" role="status"><?= $e($notice) ?></div>
    <?php endif; ?>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($amenities === []): ?>
                <p class="p-4 mb-0 text-secondary">No amenities have been created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Configured amenities</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Name</th>
                                <th class="py-3" scope="col">Icon identifier</th>
                                <th class="py-3 text-end" scope="col">Status</th>
                                <th class="px-4 py-3 text-end" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($amenities as $amenity): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row"><?= $e($amenity['name']) ?></th>
                                    <td class="py-3">
                                        <?php if ($amenity['icon'] === null): ?>
                                            <span class="text-secondary">Not set</span>
                                        <?php else: ?>
                                            <code><?= $e($amenity['icon']) ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <span class="badge <?= $amenity['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $amenity['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end text-nowrap">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="/admin/amenities/<?= $e($amenity['id']) ?>/edit"
                                        >Edit</a>
                                        <form
                                            class="d-inline"
                                            method="post"
                                            action="/admin/amenities/<?= $e($amenity['id']) ?>/<?= $amenity['active'] ? 'deactivate' : 'activate' ?>"
                                        >
                                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                <?= $amenity['active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
