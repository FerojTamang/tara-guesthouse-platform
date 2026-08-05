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

    <div class="card admin-panel">
        <div class="card-body p-4">
            <form method="post" action="<?= $e($action) ?>" enctype="multipart/form-data">
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
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="row g-3 align-items-start">
                                <div class="col-12 col-lg-8">
                                    <label class="form-label" for="payment-method-qr-image">Payment QR image</label>
                                    <input
                                        id="payment-method-qr-image"
                                        class="form-control"
                                        type="file"
                                        name="qr_image"
                                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                        aria-describedby="payment-method-qr-help"
                                    >
                                    <div id="payment-method-qr-help" class="form-text">
                                        Optional. Upload a JPG, PNG, or WebP QR image up to 5 MiB. The image is shown to Guests on the payment page.
                                    </div>
                                    <?php if ($qrConfigured): ?>
                                        <div class="form-check mt-3">
                                            <input
                                                id="payment-method-remove-qr"
                                                class="form-check-input"
                                                type="checkbox"
                                                name="remove_qr_image"
                                                value="1"
                                            >
                                            <label class="form-check-label" for="payment-method-remove-qr">
                                                Remove the current QR image
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($qrImageUrl !== null): ?>
                                    <div class="col-12 col-lg-4">
                                        <p class="form-label mb-2">Current QR image</p>
                                        <img
                                            class="img-fluid rounded border bg-white p-2"
                                            src="<?= $e($qrImageUrl) ?>"
                                            alt="Current payment QR image"
                                            width="320"
                                            height="320"
                                        >
                                    </div>
                                <?php endif; ?>
                            </div>
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
