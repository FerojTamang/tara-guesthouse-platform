<section aria-labelledby="rooms-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Room catalogue</p>
        <h1 id="rooms-heading" class="h2 mb-2">Rooms</h1>
        <p class="text-secondary mb-0">
            Review the current physical rooms, pricing, occupancy limits, operational state and publication state.
        </p>
    </div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($rooms === []): ?>
                <p class="p-4 mb-0 text-secondary">No rooms have been created yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Configured physical rooms</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Room</th>
                                <th class="py-3" scope="col">Room type</th>
                                <th class="py-3" scope="col">Price</th>
                                <th class="py-3" scope="col">Capacity</th>
                                <th class="py-3" scope="col">Operational status</th>
                                <th class="px-4 py-3 text-end" scope="col">Publication</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row">
                                        <span class="d-block"><?= $e($room['name']) ?></span>
                                        <span class="d-block small text-secondary">
                                            <?= $e($room['roomNumber']) ?> · <code><?= $e($room['slug']) ?></code>
                                        </span>
                                    </th>
                                    <td class="py-3"><?= $e($room['roomTypeName']) ?></td>
                                    <td class="py-3">NPR <?= $e($room['pricePerNight']) ?></td>
                                    <td class="py-3">
                                        <?= $e($room['capacityAdults']) ?> adult<?= $room['capacityAdults'] === 1 ? '' : 's' ?>,
                                        <?= $e($room['capacityChildren']) ?> child<?= $room['capacityChildren'] === 1 ? '' : 'ren' ?>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge <?= $e($room['operationalStatusBadge']) ?>">
                                            <?= $e($room['operationalStatusLabel']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <span class="badge <?= $room['published'] ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                                            <?= $room['published'] ? 'Published' : 'Unpublished' ?>
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
