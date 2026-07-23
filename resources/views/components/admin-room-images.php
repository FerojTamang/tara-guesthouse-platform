<section id="room-images" class="mt-5" aria-labelledby="room-images-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Room gallery</p>
        <h2 id="room-images-heading" class="h3 mb-2">Room images</h2>
        <p class="text-secondary mb-0">Upload and arrange images shown for this room.</p>
    </div>

    <?php if ($success !== null): ?>
        <div class="alert alert-success" role="status"><?= $e($success) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <div class="card admin-panel mb-4">
        <div class="card-body p-4">
            <h3 class="h5">Upload an image</h3>
            <form method="post" action="/admin/rooms/<?= $e($roomId) ?>/images" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="room-image-file">Image file</label>
                        <input id="room-image-file" class="form-control" type="file" name="image"
                               accept="image/jpeg,image/png,image/webp" required
                               aria-describedby="room-image-file-help">
                        <div id="room-image-file-help" class="form-text">Choose a JPEG, PNG, or WebP image. The file is not retained after a validation error.</div>
                    </div>
                    <div class="col-12 col-lg-8">
                        <label class="form-label" for="room-image-alt">Alternative text</label>
                        <input id="room-image-alt" class="form-control" type="text" name="alt_text"
                               maxlength="255" value="<?= $e($oldInput['alt_text']) ?>"
                               aria-describedby="room-image-alt-help">
                        <div id="room-image-alt-help" class="form-text">Describe meaningful visual content for guests using assistive technology.</div>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label" for="room-image-sort">Sort order</label>
                        <input id="room-image-sort" class="form-control" type="number" name="sort_order"
                               min="0" max="65535" step="1" value="<?= $e($oldInput['sort_order']) ?>">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2 d-flex align-items-end">
                        <input type="hidden" name="is_primary" value="0">
                        <div class="form-check mb-2">
                            <input id="room-image-primary" class="form-check-input" type="checkbox"
                                   name="is_primary" value="1" <?= $oldInput['is_primary'] === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="room-image-primary">Make primary</label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary mt-3" type="submit">Upload image</button>
            </form>
        </div>
    </div>

    <?php if ($images === []): ?>
        <div class="card admin-panel">
            <div class="card-body p-4 text-center">
                <img class="room-image-empty mb-3" src="/assets/images/room-placeholder.svg" alt="">
                <p class="mb-0 text-secondary">No images have been uploaded for this room.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($images as $index => $image): ?>
                <?php $headingId = 'room-image-heading-' . $image['id']; ?>
                <div class="col-12 col-lg-6">
                    <article class="card admin-panel room-image-card h-100" aria-labelledby="<?= $e($headingId) ?>">
                        <img class="card-img-top room-image-thumbnail" src="<?= $e($image['url']) ?>"
                             alt="<?= $e($image['altText']) ?>" data-room-image-thumbnail>
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3">
                                <h3 id="<?= $e($headingId) ?>" class="h5 mb-0">Image <?= $e((string) ($index + 1)) ?></h3>
                                <?php if ($image['primary']): ?>
                                    <span class="badge text-bg-primary">Primary image</span>
                                <?php endif; ?>
                            </div>
                            <p class="small text-secondary">
                                <?= $image['altText'] === '' ? 'No alt text provided' : 'Alt text provided' ?>
                            </p>

                            <form method="post" action="/admin/rooms/<?= $e($roomId) ?>/images/<?= $e($image['id']) ?>">
                                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="room-image-alt-<?= $e($image['id']) ?>">Alternative text</label>
                                    <input id="room-image-alt-<?= $e($image['id']) ?>" class="form-control" type="text"
                                           name="alt_text" maxlength="255" value="<?= $e($image['altText']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="room-image-sort-<?= $e($image['id']) ?>">Sort order</label>
                                    <input id="room-image-sort-<?= $e($image['id']) ?>" class="form-control" type="number"
                                           name="sort_order" min="0" max="65535" step="1" required
                                           value="<?= $e($image['sortOrder']) ?>">
                                </div>
                                <button class="btn btn-outline-primary" type="submit">Save image details</button>
                            </form>

                            <div class="room-image-actions d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                                <?php if (!$image['primary']): ?>
                                    <form method="post" action="/admin/rooms/<?= $e($roomId) ?>/images/<?= $e($image['id']) ?>/primary">
                                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                        <button class="btn btn-outline-secondary" type="submit">Set as primary</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/admin/rooms/<?= $e($roomId) ?>/images/<?= $e($image['id']) ?>/delete">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <span class="d-block small text-danger mb-1">Deletion cannot be undone.</span>
                                    <button class="btn btn-outline-danger" type="submit">Delete image</button>
                                </form>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script src="/assets/js/admin-room-images.js" defer></script>
