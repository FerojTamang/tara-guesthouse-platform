<section aria-labelledby="room-form-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Room catalogue</p>
        <h1 id="room-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
        <p class="text-secondary mb-0">
            Configure one physical guest-house room, its pricing, occupancy limits, operational state and amenities.
        </p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <?php if ($roomTypes === []): ?>
        <div class="alert alert-warning" role="alert">
            Create a room type before saving a physical room.
        </div>
    <?php endif; ?>

    <div class="card admin-panel">
        <div class="card-body p-4">
            <form method="post" action="<?= $e($action) ?>">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="room-type">Room type</label>
                        <select id="room-type" class="form-select" name="room_type_id" required>
                            <option value="">Select a room type</option>
                            <?php foreach ($roomTypes as $roomType): ?>
                                <option
                                    value="<?= $e($roomType['id']) ?>"
                                    <?= $values['roomTypeId'] === $roomType['id'] ? 'selected' : '' ?>
                                ><?= $e($roomType['name']) ?><?= $roomType['active'] ? '' : ' (inactive)' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label" for="room-number">Room number</label>
                        <input
                            id="room-number"
                            class="form-control"
                            type="text"
                            name="room_number"
                            maxlength="30"
                            required
                            value="<?= $e($values['roomNumber']) ?>"
                        >
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="form-label" for="room-price">Price per night</label>
                        <div class="input-group">
                            <span class="input-group-text">NPR</span>
                            <input
                                id="room-price"
                                class="form-control"
                                type="text"
                                inputmode="decimal"
                                name="price_per_night"
                                maxlength="13"
                                required
                                value="<?= $e($values['pricePerNight']) ?>"
                            >
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="room-name">Name</label>
                        <input
                            id="room-name"
                            class="form-control"
                            type="text"
                            name="name"
                            maxlength="100"
                            required
                            value="<?= $e($values['name']) ?>"
                        >
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="room-slug">Slug</label>
                        <input
                            id="room-slug"
                            class="form-control"
                            type="text"
                            name="slug"
                            maxlength="120"
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                            required
                            value="<?= $e($values['slug']) ?>"
                            aria-describedby="room-slug-help"
                        >
                        <div id="room-slug-help" class="form-text">
                            Use lowercase letters, numbers and single hyphens.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="room-description">Description</label>
                        <textarea
                            id="room-description"
                            class="form-control"
                            name="description"
                            rows="5"
                            maxlength="5000"
                        ><?= $e($values['description']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="room-capacity-adults">Adult capacity</label>
                        <input
                            id="room-capacity-adults"
                            class="form-control"
                            type="number"
                            name="capacity_adults"
                            min="1"
                            step="1"
                            required
                            value="<?= $e($values['capacityAdults']) ?>"
                        >
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="room-capacity-children">Child capacity</label>
                        <input
                            id="room-capacity-children"
                            class="form-control"
                            type="number"
                            name="capacity_children"
                            min="0"
                            step="1"
                            required
                            value="<?= $e($values['capacityChildren']) ?>"
                        >
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="room-operational-status">Operational status</label>
                        <select
                            id="room-operational-status"
                            class="form-select"
                            name="operational_status"
                            required
                        >
                            <option value="active" <?= $values['operationalStatus'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="maintenance" <?= $values['operationalStatus'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="inactive" <?= $values['operationalStatus'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <input type="hidden" name="published" value="0">
                        <div class="form-check form-switch">
                            <input
                                id="room-published"
                                class="form-check-input"
                                type="checkbox"
                                name="published"
                                value="1"
                                <?= $values['published'] === '1' ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="room-published">Published on the public website</label>
                        </div>
                    </div>

                    <fieldset class="col-12">
                        <legend class="h6 mb-3">Amenities</legend>
                        <?php if ($amenities === []): ?>
                            <p class="text-secondary mb-0">No amenities have been configured.</p>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($amenities as $amenity): ?>
                                    <div class="col-12 col-md-6 col-xl-4">
                                        <div class="form-check border rounded p-3 ps-5 h-100">
                                            <input
                                                id="room-amenity-<?= $e($amenity['id']) ?>"
                                                class="form-check-input"
                                                type="checkbox"
                                                name="amenity_ids[]"
                                                value="<?= $e($amenity['id']) ?>"
                                                <?= in_array($amenity['id'], $values['amenityIds'], true) ? 'checked' : '' ?>
                                            >
                                            <label class="form-check-label" for="room-amenity-<?= $e($amenity['id']) ?>">
                                                <?= $e($amenity['name']) ?>
                                                <?php if (!$amenity['active']): ?>
                                                    <span class="badge text-bg-secondary ms-1">Inactive</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </fieldset>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary" type="submit" <?= $roomTypes === [] ? 'disabled' : '' ?>>
                        <?= $e($submitLabel) ?>
                    </button>
                    <a class="btn btn-outline-secondary" href="/admin/rooms">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if ($roomId !== null): ?>
    <?= $partial('components.admin-room-images', [
        'roomId' => $roomId,
        'csrfToken' => $csrfToken,
        'images' => $images,
        'success' => $imageSuccess,
        'error' => $imageError,
        'oldInput' => $imageOldInput,
    ]) ?>
<?php endif; ?>
