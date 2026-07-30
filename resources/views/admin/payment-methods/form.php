<section aria-labelledby="payment-method-form-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Manual payments</p>
        <h1 id="payment-method-form-heading" class="h2 mb-2"><?= $e($heading) ?></h1>
        <p class="text-secondary mb-0">
            Configure Guest-facing instructions and whether payment evidence is required.
        </p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <?php if ($qrConfigured): ?>
        <div class="alert alert-info" role="status">
            An existing QR image is configured and will be preserved. QR-image upload management is not part of this workflow.
        </div>
    <?php endif; ?>

    <div class="card admin-panel">
        <div class="card-body p-4">
            <form method="post" action="<?= $e($action) ?>">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="payment-method-name">Name</label>
                        <input
                            id="payment-method-name"
                            class="form-control"
                            type="text"
                            name="name"
                            maxlength="100"
                            required
                            value="<?= $e($values['name']) ?>"
                        >
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="payment-method-account-reference">
                            Account or display reference
                        </label>
                        <input
                            id="payment-method-account-reference"
                            class="form-control"
                            type="text"
                            name="account_reference"
                            maxlength="255"
                            value="<?= $e($values['accountReference']) ?>"
                            aria-describedby="payment-method-account-reference-help"
                        >
                        <div id="payment-method-account-reference-help" class="form-text">
                            Optional Guest-facing account, wallet or transfer reference.
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="payment-method-instructions">Instructions</label>
                        <textarea
                            id="payment-method-instructions"
                            class="form-control"
                            name="instructions"
                            rows="6"
                            maxlength="5000"
                            aria-describedby="payment-method-instructions-help"
                        ><?= $e($values['instructions']) ?></textarea>
                        <div id="payment-method-instructions-help" class="form-text">
                            Optional plain-text steps shown to Guests. Do not enter secret credentials.
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <input type="hidden" name="requires_proof" value="0">
                        <div class="form-check form-switch">
                            <input
                                id="payment-method-requires-proof"
                                class="form-check-input"
                                type="checkbox"
                                name="requires_proof"
                                value="1"
                                <?= $values['requiresProof'] === '1' ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="payment-method-requires-proof">
                                Require payment proof
                            </label>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <input type="hidden" name="active" value="0">
                        <div class="form-check form-switch">
                            <input
                                id="payment-method-active"
                                class="form-check-input"
                                type="checkbox"
                                name="active"
                                value="1"
                                <?= $values['active'] === '1' ? 'checked' : '' ?>
                            >
                            <label class="form-check-label" for="payment-method-active">Active</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary" type="submit"><?= $e($submitLabel) ?></button>
                    <a class="btn btn-outline-secondary" href="/admin/payment-methods">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
