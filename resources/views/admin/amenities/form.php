<link rel="stylesheet" href="/assets/css/adminAmenities.css">

<section aria-labelledby="amenity-form-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Room catalogue</p>
        <h1 id="amenity-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
        <p class="text-secondary mb-0">
            Configure a reusable amenity that may be assigned to guest-house rooms.
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
                        <label class="form-label" for="amenity-name">Name</label>
                        <input
                            id="amenity-name"
                            class="form-control"
                            type="text"
                            name="name"
                            maxlength="100"
                            required
                            value="<?= $e($values['name']) ?>"
                        >
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="amenity-icon">Icon identifier</label>
                        <input
                            id="amenity-icon"
                            class="form-control"
                            type="text"
                            name="icon"
                            maxlength="100"
                            pattern="[a-z][a-z0-9]*(?:-[a-z0-9]+)*"
                            value="<?= $e($values['icon']) ?>"
                            aria-describedby="amenity-icon-help"
                        >
                        <div id="amenity-icon-help" class="form-text">
                            Optional. Use lowercase letters, numbers and single hyphens.
                        </div>
                    </div>

                    <div class="col-12">
                        <input type="hidden" name="active" value="0">
                        <div class="form-check form-switch">
                            <input
                                id="amenity-active"
                                class="form-check-input"
                                type="checkbox"
                                name="active"
                                value="1"
                                <?= $values['active'] === '1' ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="amenity-active">Active</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary" type="submit"><?= $e($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="/admin/amenities">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
