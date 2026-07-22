<section aria-labelledby="room-types-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Room catalogue</p>
        <h1 id="room-types-heading" class="h2 mb-2">Room types</h1>
        <p class="text-secondary mb-0">
            Review the pricing and occupancy templates available for guest-house rooms.
        </p>
    </div>

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
                                <th class="px-4 py-3 text-end" scope="col">Status</th>
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
                                    <td class="px-4 py-3 text-end">
                                        <span class="badge <?= $roomType['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $roomType['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
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
