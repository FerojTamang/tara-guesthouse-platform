<?php
$decoded = json_decode($values['content'] ?? '', true);
$isStructured = is_array($decoded) && ($decoded['format'] ?? null) === 'tara-blocks-v1';
$contentType = $isStructured && is_string($decoded['type'] ?? null) ? $decoded['type'] : 'page';
$summary = $isStructured && is_string($decoded['summary'] ?? null) ? $decoded['summary'] : '';
$blocks = $isStructured && is_array($decoded['blocks'] ?? null) ? $decoded['blocks'] : [];
if ($blocks === [] && ($values['content'] ?? '') !== '') {
    $blocks = [['type' => 'paragraph', 'text' => (string) $values['content']]];
}
?>
<section class="admin-content-builder" aria-labelledby="cms-page-form-heading">
    <div class="d-flex flex-column flex-xl-row align-items-xl-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Professional content studio</p>
            <h1 id="cms-page-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
            <p class="text-secondary mb-1">Compose accessible pages, news, notices and policies using safe structured blocks.</p>
            <p class="small text-secondary mb-0">
                Content format: <?= $e($contentFormat) ?>. Structured blocks are serialized into the approved content field; raw HTML is not accepted.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (!empty($mediaLibraryAvailable)): ?>
                <a class="btn btn-outline-secondary" href="/admin/media">Media library</a>
            <?php endif; ?>
            <button class="btn btn-outline-primary" type="button" data-content-preview>Preview</button>
            <a class="btn btn-outline-secondary" href="/admin/pages">Back to pages</a>
        </div>
    </div>

    <div id="cms-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <form method="post" action="<?= $e($action) ?>" data-cms-page-form data-api-method="<?= $e($apiMethod) ?>">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
        <textarea class="visually-hidden" id="cms-page-content" name="content" maxlength="<?= $e($contentMaximumLength) ?>" required><?= $e($values['content']) ?></textarea>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card admin-panel mb-4"><div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="cms-page-title">Title</label>
                            <input class="form-control form-control-lg" id="cms-page-title" name="title" type="text" maxlength="200" value="<?= $e($values['title']) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="cms-page-type">Content type</label>
                            <select class="form-select form-select-lg" id="cms-page-type" data-content-type>
                                <?php foreach (['page' => 'Standard page', 'news' => 'News article', 'notice' => 'Notice', 'policy' => 'Policy page'] as $key => $label): ?>
                                    <option value="<?= $e($key) ?>"<?= $contentType === $key ? ' selected' : '' ?>><?= $e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="cms-page-summary">Summary</label>
                            <textarea class="form-control" id="cms-page-summary" rows="3" maxlength="500" data-content-summary placeholder="A concise introduction used on cards and search previews."><?= $e($summary) ?></textarea>
                        </div>
                    </div>
                </div></div>

                <div class="card admin-panel"><div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <p class="admin-eyebrow mb-1">Page body</p>
                            <h2 class="h4 mb-0">Content blocks</h2>
                        </div>
                        <select class="form-select w-auto" data-add-block aria-label="Add content block">
                            <option value="">Add block…</option>
                            <option value="heading">Heading</option>
                            <option value="paragraph">Paragraph</option>
                            <option value="image">Image</option>
                            <option value="image_text">Image + text</option>
                            <option value="callout">Notice / callout</option>
                            <option value="quote">Quote</option>
                            <option value="button">Button</option>
                            <option value="divider">Divider</option>
                        </select>
                    </div>
                    <div class="admin-block-list" data-block-list></div>
                    <div class="admin-block-empty" data-block-empty>
                        <strong>Start composing your page.</strong>
                        <span>Add a heading, paragraph, image or callout block.</span>
                    </div>
                </div></div>
            </div>

            <aside class="col-xl-4">
                <div class="card admin-panel mb-4"><div class="card-body p-4">
                    <p class="admin-eyebrow mb-1">Publishing</p>
                    <h2 class="h5 mb-3">Identity and timing</h2>
                    <div class="mb-3">
                        <label class="form-label" for="cms-page-slug">URL slug</label>
                        <div class="input-group"><span class="input-group-text">/</span><input class="form-control" id="cms-page-slug" name="slug" type="text" maxlength="200" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= $e($values['slug']) ?>" required></div>
                        <div class="form-text">Lowercase letters, numbers and hyphens only.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cms-page-publish-at">Planned publication</label>
                        <input class="form-control" id="cms-page-publish-at" name="publish_at" type="datetime-local" value="<?= $e((string) ($values['publishAt'] ?? '')) ?>">
                        <div class="form-text">Save a future time, then use Schedule from the page detail screen.</div>
                    </div>
                    <div class="mb-3" data-notice-publishing-field<?= $contentType === 'notice' ? '' : ' hidden' ?>>
                        <label class="form-label" for="cms-page-notice-style">Notice style</label>
                        <select class="form-select" id="cms-page-notice-style" name="notice_style">
                            <option value="">Default</option>
                            <?php foreach (['information' => 'Information', 'important' => 'Important', 'warning' => 'Warning', 'event' => 'Event'] as $key => $label): ?>
                                <option value="<?= $e($key) ?>"<?= ($values['noticeStyle'] ?? '') === $key ? ' selected' : '' ?>><?= $e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" data-notice-publishing-field<?= $contentType === 'notice' ? '' : ' hidden' ?>>
                        <label class="form-label" for="cms-page-expires-at">Notice expiry</label>
                        <input class="form-control" id="cms-page-expires-at" name="expires_at" type="datetime-local" value="<?= $e((string) ($values['expiresAt'] ?? '')) ?>">
                        <div class="form-text">Expired notices stop being public automatically.</div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="cms-page-featured" name="is_featured" type="checkbox"<?= !empty($values['featured']) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="cms-page-featured">Feature this content</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" id="cms-page-pinned" name="is_pinned" type="checkbox"<?= !empty($values['pinned']) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="cms-page-pinned">Pin above other content</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cms-page-featured-media">Featured image</label>
                        <select class="form-select" id="cms-page-featured-media" name="featured_media_id">
                            <option value="">No featured image</option>
                            <?php foreach (($mediaOptions ?? []) as $media): ?>
                                <option value="<?= $e($media['id']) ?>"<?= ($values['featuredMediaId'] ?? null) === $media['id'] ? ' selected' : '' ?>><?= $e($media['title'] ?: $media['originalName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Save page</button>
                    <?php if (($values['id'] ?? null) !== null): ?>
                        <a class="btn btn-outline-secondary w-100 mt-2" href="/admin/pages/<?= $e($values['id']) ?>">Publication actions</a>
                    <?php endif; ?>
                </div></div>

                <div class="card admin-panel"><div class="card-body p-4">
                    <p class="admin-eyebrow mb-1">Search and sharing</p>
                    <h2 class="h5 mb-3">SEO metadata</h2>
                    <div class="mb-3">
                        <label class="form-label" for="cms-page-meta-title">Meta title</label>
                        <input class="form-control" id="cms-page-meta-title" name="meta_title" type="text" maxlength="255" value="<?= $e($values['metaTitle']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cms-page-meta-description">Meta description</label>
                        <textarea class="form-control" id="cms-page-meta-description" name="meta_description" rows="4" maxlength="500"><?= $e($values['metaDescription']) ?></textarea>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" id="cms-page-noindex" name="seo_noindex" type="checkbox"<?= !empty($values['seoNoindex']) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="cms-page-noindex">Ask search engines not to index this page</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cms-page-social-title">Social title</label>
                        <input class="form-control" id="cms-page-social-title" name="social_title" type="text" maxlength="255" value="<?= $e((string) ($values['socialTitle'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cms-page-social-description">Social description</label>
                        <textarea class="form-control" id="cms-page-social-description" name="social_description" rows="3" maxlength="500"><?= $e((string) ($values['socialDescription'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label class="form-label" for="cms-page-social-media">Social image</label>
                        <select class="form-select" id="cms-page-social-media" name="social_media_id">
                            <option value="">Use featured image</option>
                            <?php foreach (($mediaOptions ?? []) as $media): ?>
                                <option value="<?= $e($media['id']) ?>"<?= ($values['socialMediaId'] ?? null) === $media['id'] ? ' selected' : '' ?>><?= $e($media['title'] ?: $media['originalName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div></div>
            </aside>
        </div>
    </form>

    <dialog class="admin-content-preview" data-preview-dialog aria-labelledby="content-preview-heading">
        <div class="admin-content-preview__bar">
            <h2 class="h5 mb-0" id="content-preview-heading">Page preview</h2>
            <button class="btn-close" type="button" data-close-preview aria-label="Close preview"></button>
        </div>
        <div class="admin-content-preview__body" data-preview-body></div>
    </dialog>

    <?php if (!empty($mediaLibraryAvailable)): ?>
        <dialog class="admin-content-media-picker" data-content-media-picker aria-labelledby="content-media-picker-heading">
            <div class="admin-content-preview__bar">
                <div>
                    <p class="admin-eyebrow mb-1">Reusable images</p>
                    <h2 class="h5 mb-0" id="content-media-picker-heading">Choose from media library</h2>
                </div>
                <button class="btn-close" type="button" data-close-media-picker aria-label="Close media library"></button>
            </div>
            <div class="p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row gap-2 mb-3">
                    <label class="visually-hidden" for="content-media-picker-search">Search media</label>
                    <input class="form-control" id="content-media-picker-search" type="search" placeholder="Search images" data-content-media-search>
                    <a class="btn btn-outline-secondary flex-shrink-0" href="/admin/media" target="_blank" rel="noopener">Manage library</a>
                </div>
                <div class="admin-content-media-picker__status text-secondary" data-content-media-status>Loading media…</div>
                <div class="admin-content-media-picker__grid" data-content-media-grid></div>
            </div>
        </dialog>
    <?php endif; ?>

    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required to use the structured content editor.</p></noscript>
</section>
<script type="application/json" id="cms-initial-blocks"><?= json_encode($blocks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="cms-media-library-config"><?= json_encode(['enabled' => !empty($mediaLibraryAvailable), 'endpoint' => '/api/v1/admin/media'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<script src="/assets/js/admin-cms.js" defer></script>
