<section class="admin-media-library" aria-labelledby="media-library-heading">
    <div class="d-flex flex-column flex-xl-row align-items-xl-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Content studio</p>
            <h1 id="media-library-heading" class="h2 mb-2">Media library</h1>
            <p class="text-secondary mb-0">Upload once, add accessible metadata, and reuse approved images across pages, news and notices.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="/admin/pages">Back to pages</a>
            <a class="btn btn-primary" href="/admin/pages/create">Create content</a>
        </div>
    </div>

    <div id="media-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-4">
            <div class="card admin-panel admin-media-upload-card">
                <div class="card-body p-4">
                    <p class="admin-eyebrow mb-1">Add an image</p>
                    <h2 class="h4 mb-3">Upload media</h2>
                    <form action="/api/v1/admin/media" method="post" enctype="multipart/form-data" data-media-upload-form>
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="content-media-image">Image</label>
                            <input class="form-control" id="content-media-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
                            <div class="form-text">
                                JPG, PNG or WebP. Maximum <?= $e(number_format($maximumUploadBytes / 1048576, 0)) ?> MiB.
                                Minimum <?= $e($minimumWidth) ?> × <?= $e($minimumHeight) ?> pixels.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="content-media-title">Internal title <span class="text-secondary">(optional)</span></label>
                            <input class="form-control" id="content-media-title" name="title" type="text" maxlength="200" placeholder="Mountain-view room exterior">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="content-media-alt">Alternative text</label>
                            <input class="form-control" id="content-media-alt" name="alt_text" type="text" maxlength="255" required placeholder="Describe what is visible in the image">
                            <div class="form-text">Required for accessibility. Do not write “image of”.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="content-media-caption">Caption <span class="text-secondary">(optional)</span></label>
                            <textarea class="form-control" id="content-media-caption" name="caption" rows="3" maxlength="500"></textarea>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Upload to media library</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card admin-panel mb-4">
                <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <div>
                        <p class="admin-eyebrow mb-1">Reusable assets</p>
                        <h2 class="h4 mb-0"><?= $e(count($media)) ?> image<?= count($media) === 1 ? '' : 's' ?></h2>
                    </div>
                    <div class="ms-md-auto admin-media-search">
                        <label class="visually-hidden" for="media-library-search">Search media</label>
                        <input class="form-control" id="media-library-search" type="search" placeholder="Search title, filename or alt text" data-media-search>
                    </div>
                </div>
            </div>

            <?php if ($media === []): ?>
                <div class="admin-media-empty">
                    <strong>No content images yet.</strong>
                    <span>Upload the first approved image using the form.</span>
                </div>
            <?php else: ?>
                <div class="admin-media-grid" data-media-grid>
                    <?php foreach ($media as $item): ?>
                        <?php $searchText = strtolower(trim(($item['title'] ?? '') . ' ' . $item['originalName'] . ' ' . $item['altText'])); ?>
                        <article class="card admin-panel admin-media-card" data-media-card data-search-text="<?= $e($searchText) ?>">
                            <div class="admin-media-card__preview">
                                <img src="<?= $e($item['url']) ?>" alt="<?= $e($item['altText']) ?>" width="<?= $e($item['width']) ?>" height="<?= $e($item['height']) ?>" loading="lazy">
                                <span class="badge text-bg-light admin-media-card__usage">
                                    <?= $e($item['usageCount']) ?> use<?= $item['usageCount'] === 1 ? '' : 's' ?>
                                </span>
                            </div>
                            <div class="card-body p-3 p-md-4">
                                <p class="small text-secondary mb-3">
                                    <?= $e($item['width']) ?> × <?= $e($item['height']) ?> ·
                                    <?= $e(number_format($item['byteSize'] / 1024, 0)) ?> KiB ·
                                    <?= $e(strtoupper(pathinfo($item['originalName'], PATHINFO_EXTENSION))) ?>
                                </p>
                                <form action="/api/v1/admin/media/<?= $e($item['id']) ?>" method="post" data-media-metadata-form>
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <div class="mb-3">
                                        <label class="form-label" for="media-title-<?= $e($item['id']) ?>">Internal title</label>
                                        <input class="form-control" id="media-title-<?= $e($item['id']) ?>" name="title" type="text" maxlength="200" value="<?= $e($item['title'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="media-alt-<?= $e($item['id']) ?>">Alternative text</label>
                                        <input class="form-control" id="media-alt-<?= $e($item['id']) ?>" name="alt_text" type="text" maxlength="255" value="<?= $e($item['altText']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="media-caption-<?= $e($item['id']) ?>">Caption</label>
                                        <textarea class="form-control" id="media-caption-<?= $e($item['id']) ?>" name="caption" rows="2" maxlength="500"><?= $e($item['caption'] ?? '') ?></textarea>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Save metadata</button>
                                        <button
                                            class="btn btn-sm btn-outline-danger ms-auto"
                                            type="button"
                                            data-delete-media
                                            data-media-id="<?= $e($item['id']) ?>"
                                            <?= $item['usageCount'] > 0 ? 'disabled aria-disabled="true" title="Remove this image from all content before deleting it."' : '' ?>
                                        >Delete</button>
                                    </div>
                                </form>
                                <p class="small text-secondary text-break mt-3 mb-0"><?= $e($item['originalName']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="alert alert-secondary mt-4 mb-0" data-media-empty-search hidden>No media matches your search.</p>
            <?php endif; ?>
        </div>
    </div>
    <noscript><p class="alert alert-warning mt-4 mb-0">JavaScript is required to upload, edit or delete media.</p></noscript>
</section>
<script src="/assets/js/admin-media.js" defer></script>
