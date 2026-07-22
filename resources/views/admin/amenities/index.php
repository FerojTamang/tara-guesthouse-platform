<section aria-labelledby="amenities-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Room catalogue</p>
        <h1 id="amenities-heading" class="h2 mb-2">Amenities</h1>
        <p class="text-secondary mb-0">
            Review the reusable amenities that may be assigned to rooms.
        </p>
    </div>

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
                                <th class="px-4 py-3 text-end" scope="col">Status</th>
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
                                    <td class="px-4 py-3 text-end">
                                        <span class="badge <?= $amenity['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $amenity['active'] ? 'Active' : 'Inactive' ?>
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
