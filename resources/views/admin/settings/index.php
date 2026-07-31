<section aria-labelledby="settings-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Content management</p>
        <h1 id="settings-heading" class="h2 mb-2">Site settings</h1>
        <p class="text-secondary mb-0">
            Only environment-approved setting keys are available. Visibility and value types are controlled by the server.
        </p>
    </div>

    <div id="cms-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <form
        method="post"
        action="/api/v1/admin/settings"
        data-cms-settings-form
        data-api-method="PUT"
    >
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

        <div class="card admin-panel">
            <div class="card-body">
                <?php if ($settings === []): ?>
                    <p class="mb-0 text-secondary">No approved setting definitions are configured.</p>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($settings as $setting): ?>
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
                                        <div>
                                            <label class="form-label fw-semibold mb-1" for="setting-<?= $e(str_replace('.', '-', $setting['key'])) ?>">
                                                <?= $e($setting['key']) ?>
                                            </label>
                                            <div class="small text-secondary">
                                                Type: <?= $e($setting['valueType']) ?> · Maximum <?= $e($setting['maximumLength']) ?> characters
                                            </div>
                                        </div>
                                        <span class="badge <?= $setting['public'] ? 'text-bg-success' : 'text-bg-secondary' ?> align-self-start">
                                            <?= $setting['public'] ? 'Public' : 'Private' ?>
                                        </span>
                                    </div>

                                    <?php if ($setting['valueType'] === 'json'): ?>
                                        <textarea
                                            class="form-control font-monospace"
                                            id="setting-<?= $e(str_replace('.', '-', $setting['key'])) ?>"
                                            data-setting-key="<?= $e($setting['key']) ?>"
                                            maxlength="<?= $e($setting['maximumLength']) ?>"
                                            rows="4"
                                            required
                                        ><?= $e($setting['value']) ?></textarea>
                                    <?php else: ?>
                                        <input
                                            class="form-control"
                                            id="setting-<?= $e(str_replace('.', '-', $setting['key'])) ?>"
                                            data-setting-key="<?= $e($setting['key']) ?>"
                                            type="<?= $setting['valueType'] === 'email' ? 'email' : ($setting['valueType'] === 'url' ? 'url' : 'text') ?>"
                                            maxlength="<?= $e($setting['maximumLength']) ?>"
                                            value="<?= $e($setting['value']) ?>"
                                            required
                                        >
                                    <?php endif; ?>

                                    <div class="form-text">
                                        <?= $setting['configured'] ? 'Last updated ' . $e($setting['updatedAt']) : 'Not configured yet.' ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary" type="submit">Save settings</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required to save settings.</p></noscript>
</section>
<script src="/assets/js/admin-cms.js" defer></script>
