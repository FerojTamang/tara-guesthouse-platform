<section aria-labelledby="room-types-heading">
    <div class="admin-page-heading d-flex flex-column flex-md-row align-items-md-start justify-content-md-between gap-3 mb-4">
        <div>
            <p class="admin-eyebrow mb-2">Room catalogue</p>
            <h1 id="room-types-heading" class="h2 mb-2">Room types</h1>
            <p class="text-secondary mb-0">
                Manage the pricing and occupancy templates available for guest-house rooms.
            </p>
        </div>
        <a class="btn btn-primary" href="/admin/room-types/create">Create room type</a>
    </div>

    <?php if ($notice !== null): ?>
        <div class="alert alert-success" role="status"><?= $e($notice) ?></div>
    <?php endif; ?>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($roomTypes === []): ?>
                <p class="p-4 mb-0 text-secondary">No room types have been created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Configured room types</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Name</th>
                                <th class="py-3" scope="col">Slug</th>
                                <th class="py-3" scope="col">Base price</th>
                                <th class="py-3" scope="col">Capacity</th>
                                <th class="py-3 text-end" scope="col">Status</th>
                                <th class="px-4 py-3 text-end" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roomTypes as $roomType): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row"><?= $e($roomType['name']) ?></th>
                                    <td class="py-3"><code><?= $e($roomType['slug']) ?></code></td>
                                    <td class="py-3">NPR <?= $e($roomType['basePrice']) ?></td>
                                    <td class="py-3">
                                        <?= $e($roomType['capacityAdults']) ?> adult<?= $roomType['capacityAdults'] === 1 ? '' : 's' ?>,
                                        <?= $e($roomType['capacityChildren']) ?> child<?= $roomType['capacityChildren'] === 1 ? '' : 'ren' ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <span class="badge <?= $roomType['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $roomType['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end text-nowrap">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="/admin/room-types/<?= $e($roomType['id']) ?>/edit"
                                        >Edit</a>
                                        <form
                                            class="d-inline"
                                            method="post"
                                            action="/admin/room-types/<?= $e($roomType['id']) ?>/<?= $roomType['active'] ? 'deactivate' : 'activate' ?>"
                                        >
                                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                <?= $roomType['active'] ? 'Deactivate' : 'Activate' ?>
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
