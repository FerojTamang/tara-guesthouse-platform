<?php
$settingGroups = is_array($settingGroups ?? null) ? $settingGroups : [];
$settings = is_array($settings ?? null) ? $settings : [];
$configuredCount = count(array_filter(
    $settings,
    static fn (array $setting): bool => ($setting['configured'] ?? false) === true,
));
$publicCount = count(array_filter(
    $settings,
    static fn (array $setting): bool => ($setting['public'] ?? false) === true,
));
$privateCount = max(0, count($settings) - $publicCount);
$settingsByKey = [];
foreach ($settings as $setting) {
    if (is_array($setting) && isset($setting['key']) && is_string($setting['key'])) {
        $settingsByKey[$setting['key']] = $setting;
    }
}
$logoPath = (string) ($settingsByKey['site.logo']['value'] ?? '');
$logoEnabled = ($settingsByKey['site.logo.enabled']['value'] ?? 'false') === 'true';
$logoConfigured = preg_match('/^uploads\/branding\/[a-f0-9]{64}\.(?:jpg|png|webp)$/D', $logoPath) === 1;
$logoUrl = $logoConfigured ? '/' . $logoPath : null;
$logoUploadAvailable = isset($settingsByKey['site.logo'], $settingsByKey['site.logo.enabled']);
?>
<section class="admin-settings-page" aria-labelledby="settings-heading">
    <div class="admin-settings-hero mb-4">
        <div class="admin-settings-hero__copy">
            <p class="admin-eyebrow mb-2">Website configuration</p>
            <h1 id="settings-heading" class="display-6 mb-2">Professional site settings</h1>
            <p class="text-secondary mb-0">
                Manage public business information, guest guidance, social links, SEO defaults,
                and private notification recipients from one controlled workspace.
            </p>
        </div>

        <div class="admin-settings-summary" aria-label="Settings summary">
            <div>
                <strong><?= $e((string) $configuredCount) ?></strong>
                <span>Configured</span>
            </div>
            <div>
                <strong><?= $e((string) $publicCount) ?></strong>
                <span>Public</span>
            </div>
            <div>
                <strong><?= $e((string) $privateCount) ?></strong>
                <span>Private</span>
            </div>
        </div>
    </div>

    <div id="cms-action-message" class="alert d-none" role="alert" aria-live="polite" tabindex="-1"></div>

    <?php if ($logoUploadAvailable): ?>
        <section class="card admin-panel admin-settings-branding mb-4" aria-labelledby="brand-identity-heading">
            <div class="card-header admin-settings-branding__header">
                <div>
                    <p class="admin-card-kicker mb-1">Brand identity</p>
                    <h2 id="brand-identity-heading" class="h4 mb-1">Logo and public name</h2>
                    <p class="text-secondary mb-0">
                        Upload a compact brand logo for the public header and footer. The guest-house name remains visible for clarity and accessibility.
                    </p>
                </div>
                <span class="badge text-bg-success">Public</span>
            </div>

            <div class="card-body p-4">
                <div class="admin-settings-branding__grid">
                    <div class="admin-settings-branding__preview" data-brand-preview>
                        <div class="admin-settings-branding__logo-frame">
                            <?php if ($logoEnabled && $logoUrl !== null): ?>
                                <img
                                    src="<?= $e($logoUrl) ?>"
                                    alt="Current guest-house logo"
                                    width="112"
                                    height="112"
                                    data-current-brand-logo
                                >
                                <span class="admin-settings-branding__fallback" hidden data-brand-fallback aria-hidden="true">T</span>
                            <?php else: ?>
                                <img
                                    src=""
                                    alt="Selected logo preview"
                                    width="112"
                                    height="112"
                                    hidden
                                    data-current-brand-logo
                                >
                                <span class="admin-settings-branding__fallback" data-brand-fallback aria-hidden="true">T</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong><?= $e((string) ($settingsByKey['site.name']['value'] ?? 'Tara Guest House')) ?></strong>
                            <p class="small text-secondary mb-0">
                                <?= $logoEnabled && $logoUrl !== null ? 'Custom logo active' : 'Default T emblem active' ?>
                            </p>
                        </div>
                    </div>

                    <form
                        method="post"
                        action="/api/v1/admin/settings/logo"
                        enctype="multipart/form-data"
                        data-cms-logo-form
                    >
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <label class="form-label fw-semibold" for="site-logo-upload">Upload or replace logo</label>
                        <input
                            class="form-control"
                            id="site-logo-upload"
                            name="logo"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            required
                            data-brand-logo-input
                        >
                        <p class="small text-secondary mt-2 mb-3">
                            PNG, JPG, or WebP. Maximum 2 MiB. Transparent PNG works best. Minimum 64 × 64 pixels.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Upload logo</button>
                            <?php if ($logoEnabled && $logoUrl !== null): ?>
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    data-use-default-logo
                                    data-csrf-token="<?= $e($csrfToken) ?>"
                                >
                                    Use default T emblem
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($settingGroups === []): ?>
        <div class="card admin-panel">
            <div class="card-body p-4 p-lg-5 text-center">
                <h2 class="h4">No approved settings are available</h2>
                <p class="text-secondary mb-0">
                    Add approved definitions to the CMS setting policy before using this workspace.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 align-items-start">
            <aside class="col-12 col-xl-3">
                <div class="admin-settings-sidecard">
                    <p class="admin-card-kicker mb-2">Jump to section</p>
                    <nav class="admin-settings-nav" aria-label="Settings sections">
                        <?php foreach ($settingGroups as $group): ?>
                            <a href="#settings-group-<?= $e($group['key']) ?>">
                                <span><?= $e($group['title']) ?></span>
                                <span aria-hidden="true"><?= $e((string) count($group['fields'])) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <div class="admin-settings-safety-note">
                        <strong>Secrets stay private</strong>
                        <p class="mb-0">
                            SMTP passwords, database credentials, access tokens, and encryption keys remain in
                            <code>.env</code>. They never belong in this form.
                        </p>
                    </div>
                </div>
            </aside>

            <div class="col-12 col-xl-9">
                <form
                    method="post"
                    action="/api/v1/admin/settings"
                    data-cms-settings-form
                    data-api-method="PUT"
                >
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="d-grid gap-4">
                        <?php foreach ($settingGroups as $group): ?>
                            <section
                                id="settings-group-<?= $e($group['key']) ?>"
                                class="card admin-panel admin-settings-group"
                                aria-labelledby="settings-group-<?= $e($group['key']) ?>-heading"
                            >
                                <div class="card-header admin-settings-group__header">
                                    <div>
                                        <p class="admin-card-kicker mb-1">Settings group</p>
                                        <h2 id="settings-group-<?= $e($group['key']) ?>-heading" class="h4 mb-1">
                                            <?= $e($group['title']) ?>
                                        </h2>
                                        <p class="text-secondary mb-0"><?= $e($group['description']) ?></p>
                                    </div>
                                    <span class="admin-settings-group__count">
                                        <?= $e((string) count($group['fields'])) ?> fields
                                    </span>
                                </div>

                                <div class="card-body p-4">
                                    <div class="row g-4">
                                        <?php foreach ($group['fields'] as $setting): ?>
                                            <?php
                                            $fieldId = 'setting-' . str_replace('.', '-', $setting['key']);
                                            $isRequired = ($setting['required'] ?? true) === true;
                                            $columnClass = ($setting['width'] ?? 'full') === 'half'
                                                ? 'col-12 col-lg-6'
                                                : 'col-12';
                                            ?>
                                            <div class="<?= $e($columnClass) ?>">
                                                <div class="admin-setting-field h-100">
                                                    <div class="admin-setting-field__heading">
                                                        <div class="min-w-0">
                                                            <label class="form-label fw-semibold mb-1" for="<?= $e($fieldId) ?>">
                                                                <?= $e($setting['label']) ?>
                                                                <?php if (!$isRequired): ?>
                                                                    <span class="admin-setting-optional">Optional</span>
                                                                <?php endif; ?>
                                                            </label>
                                                            <p class="small text-secondary mb-0">
                                                                <?= $e($setting['description']) ?>
                                                            </p>
                                                        </div>

                                                        <span class="badge <?= $setting['public'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                            <?= $setting['public'] ? 'Public' : 'Private' ?>
                                                        </span>
                                                    </div>

                                                    <?php if (($setting['control'] ?? 'input') === 'textarea'): ?>
                                                        <textarea
                                                            class="form-control"
                                                            id="<?= $e($fieldId) ?>"
                                                            data-setting-key="<?= $e($setting['key']) ?>"
                                                            data-setting-optional="<?= $isRequired ? 'false' : 'true' ?>"
                                                            maxlength="<?= $e((string) $setting['maximumLength']) ?>"
                                                            rows="<?= $e((string) ($setting['rows'] ?? 4)) ?>"
                                                            placeholder="<?= $e($setting['placeholder'] ?? '') ?>"
                                                            autocomplete="<?= $e($setting['autocomplete'] ?? 'off') ?>"
                                                            <?= $isRequired ? 'required' : '' ?>
                                                        ><?= $e($setting['value']) ?></textarea>
                                                    <?php else: ?>
                                                        <input
                                                            class="form-control"
                                                            id="<?= $e($fieldId) ?>"
                                                            data-setting-key="<?= $e($setting['key']) ?>"
                                                            data-setting-optional="<?= $isRequired ? 'false' : 'true' ?>"
                                                            type="<?= $e($setting['input_type'] ?? 'text') ?>"
                                                            maxlength="<?= $e((string) $setting['maximumLength']) ?>"
                                                            value="<?= $e($setting['value']) ?>"
                                                            placeholder="<?= $e($setting['placeholder'] ?? '') ?>"
                                                            autocomplete="<?= $e($setting['autocomplete'] ?? 'off') ?>"
                                                            <?= $isRequired ? 'required' : '' ?>
                                                        >
                                                    <?php endif; ?>

                                                    <div class="admin-setting-field__meta">
                                                        <span>
                                                            <?= $setting['configured']
                                                                ? 'Updated ' . $e((string) $setting['updatedAt'])
                                                                : 'Not configured yet.' ?>
                                                        </span>
                                                        <code><?= $e($setting['key']) ?></code>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-settings-savebar mt-4">
                        <div>
                            <strong>Save approved settings</strong>
                            <p class="mb-0 text-secondary small">
                                Optional blank fields are ignored. Existing configured values are not removed.
                            </p>
                        </div>
                        <button class="btn btn-primary px-4" type="submit">Save settings</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <noscript><p class="alert alert-warning mt-3 mb-0">JavaScript is required to save settings.</p></noscript>
</section>
<script src="/assets/js/admin-cms.js" defer></script>
