<link rel="stylesheet" href="/assets/css/adminRoomTypes.css">

<section aria-labelledby="room-type-form-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Room catalogue</p>
        <h1 id="room-type-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
        <p class="text-secondary mb-0">
            Configure the reusable pricing and occupancy template for rooms of this type.
        </p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <div class="card admin-panel">
        <div class="card-body p-4">
            <form method="post" action="<?= $e($action) ?>">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="room-type-name">Name</label>
                        <input
                            id="room-type-name"
                            class="form-control"
                            type="text"
                            name="name"
                            maxlength="100"
                            required
                            value="<?= $e($values['name']) ?>"
                        >
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="room-type-slug">Slug</label>
                        <input
                            id="room-type-slug"
                            class="form-control"
                            type="text"
                            name="slug"
                            maxlength="120"
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                            required
                            value="<?= $e($values['slug']) ?>"
                            aria-describedby="room-type-slug-help"
                        >
                        <div id="room-type-slug-help" class="form-text">Use lowercase letters, numbers and single hyphens.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="room-type-description">Description</label>
                        <textarea
                            id="room-type-description"
                            class="form-control"
                            name="description"
                            rows="5"
                            maxlength="5000"
                        ><?= $e($values['description']) ?></textarea>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="room-type-base-price">Base price (NPR)</label>
                        <input
                            id="room-type-base-price"
                            class="form-control"
                            type="text"
                            inputmode="decimal"
                            name="base_price"
                            required
                            value="<?= $e($values['basePrice']) ?>"
                        >
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label" for="room-type-capacity-adults">Adult capacity</label>
                        <input
                            id="room-type-capacity-adults"
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
                        <label class="form-label" for="room-type-capacity-children">Child capacity</label>
                        <input
                            id="room-type-capacity-children"
                            class="form-control"
                            type="number"
                            name="capacity_children"
                            min="0"
                            step="1"
                            required
                            value="<?= $e($values['capacityChildren']) ?>"
                        >
                    </div>

                    <div class="col-12">
                        <input type="hidden" name="active" value="0">
                        <div class="form-check form-switch">
                            <input
                                id="room-type-active"
                                class="form-check-input"
                                type="checkbox"
                                name="active"
                                value="1"
                                <?= $values['active'] === '1' ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="room-type-active">Active</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary" type="submit"><?= $e($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="/admin/room-types">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
