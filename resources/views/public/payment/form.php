<?php
$paymentMethodError = $fieldErrors['paymentMethod'] ?? null;
$transactionReferenceError = $fieldErrors['transactionReference'] ?? null;
$proofError = $fieldErrors['proof'] ?? null;
$guestNotesError = $fieldErrors['guestNotes'] ?? null;
$proofDescription = 'proof_upload_help'
    . ($proofError !== null ? ' proof_upload_error' : '');
?>
<section class="container py-5" aria-labelledby="payment-title">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <header class="mb-4">
                <p class="text-uppercase text-secondary fw-semibold mb-2">Manual payment</p>
                <h1 id="payment-title" class="display-6">Submit your payment details</h1>
                <p class="lead mb-0">
                    Your booking remains pending until Tara Guest House verifies the payment.
                </p>
            </header>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert" tabindex="-1">
                    <h2 class="h5">Please review your payment details</h2>
                    <p class="mb-0"><?= $e($error) ?></p>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h4">Booking summary</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Booking reference</dt>
                        <dd class="col-sm-7 fw-semibold"><?= $e($booking['bookingReference']) ?></dd>

                        <dt class="col-sm-5">Amount due</dt>
                        <dd class="col-sm-7 fw-semibold">
                            <?= $e($booking['currency']) ?> <?= $e($booking['totalAmount']) ?>
                        </dd>

                        <dt class="col-sm-5">Payment deadline</dt>
                        <dd class="col-sm-7"><?= $e($booking['confirmationDeadlineAt']) ?></dd>
                    </dl>
                </div>
            </div>

            <?php if ($paymentMethods === []): ?>
                <div class="alert alert-warning" role="status">
                    No payment method is currently available. Please contact Tara Guest House before the deadline.
                </div>
            <?php else: ?>
                <form
                    method="post"
                    action="/booking/payment"
                    enctype="multipart/form-data"
                    class="card border-0 shadow-sm"
                    novalidate
                    data-payment-form
                >
                    <div class="card-body p-4">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                        <div class="mb-3">
                            <label for="payment_method_id" class="form-label">Payment method</label>
                            <select
                                id="payment_method_id"
                                name="payment_method_id"
                                class="form-select<?= $paymentMethodError !== null ? ' is-invalid' : '' ?>"
                                required
                                data-payment-method
                                <?= $paymentMethodError !== null
                                    ? 'aria-invalid="true" aria-describedby="payment_method_id_error"'
                                    : '' ?>
                            >
                                <option value="">Choose a payment method</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option
                                        value="<?= $e($method['id']) ?>"
                                        data-instructions="<?= $e($method['instructions'] ?? '') ?>"
                                        data-account-reference="<?= $e($method['accountReference'] ?? '') ?>"
                                        data-requires-proof="<?= $method['requiresProof'] ? '1' : '0' ?>"
                                        <?= $values['paymentMethodId'] === (string) $method['id'] ? 'selected' : '' ?>
                                    >
                                        <?= $e(
                                            $method['name']
                                            . ($method['requiresProof'] ? ' — proof required' : ''),
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($paymentMethodError !== null): ?>
                                <div id="payment_method_id_error" class="invalid-feedback">
                                    <?= $e($paymentMethodError) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="alert alert-light border d-none" data-payment-method-details aria-live="polite">
                            <p class="mb-2 d-none" data-payment-instructions></p>
                            <p class="mb-0 d-none">
                                <strong>Account/reference:</strong>
                                <span data-payment-account-reference></span>
                            </p>
                        </div>

                        <div class="mb-3">
                            <label for="transaction_reference" class="form-label">
                                Transaction/reference code
                                <span class="text-secondary">(optional)</span>
                            </label>
                            <input
                                id="transaction_reference"
                                name="transaction_reference"
                                type="text"
                                maxlength="100"
                                class="form-control<?= $transactionReferenceError !== null ? ' is-invalid' : '' ?>"
                                value="<?= $e($values['transactionReference']) ?>"
                                autocomplete="off"
                                <?= $transactionReferenceError !== null
                                    ? 'aria-invalid="true" aria-describedby="transaction_reference_error"'
                                    : '' ?>
                            >
                            <?php if ($transactionReferenceError !== null): ?>
                                <div id="transaction_reference_error" class="invalid-feedback">
                                    <?= $e($transactionReferenceError) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="proof_upload" class="form-label">
                                Payment proof
                                <span class="text-secondary" data-proof-requirement>
                                    (required only for selected methods)
                                </span>
                            </label>
                            <input
                                id="proof_upload"
                                name="proof_upload"
                                type="file"
                                accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf"
                                class="form-control<?= $proofError !== null ? ' is-invalid' : '' ?>"
                                aria-describedby="<?= $e($proofDescription) ?>"
                                <?= $proofError !== null ? 'aria-invalid="true"' : '' ?>
                                data-payment-proof
                            >
                            <div id="proof_upload_help" class="form-text">
                                Upload a JPG, PNG, or PDF no larger than 5 MiB. Files are stored privately.
                            </div>
                            <?php if ($proofError !== null): ?>
                                <div id="proof_upload_error" class="invalid-feedback">
                                    <?= $e($proofError) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-0">
                            <label for="guest_notes" class="form-label">
                                Notes <span class="text-secondary">(optional)</span>
                            </label>
                            <textarea
                                id="guest_notes"
                                name="guest_notes"
                                rows="4"
                                maxlength="65535"
                                class="form-control<?= $guestNotesError !== null ? ' is-invalid' : '' ?>"
                                <?= $guestNotesError !== null
                                    ? 'aria-invalid="true" aria-describedby="guest_notes_error"'
                                    : '' ?>
                            ><?= $e($values['guestNotes']) ?></textarea>
                            <?php if ($guestNotesError !== null): ?>
                                <div id="guest_notes_error" class="invalid-feedback">
                                    <?= $e($guestNotesError) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-footer bg-white border-0 px-4 pb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Submit payment for review
                        </button>
                        <a href="/rooms" class="btn btn-link">View rooms</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<script src="/assets/js/public-payment.js" defer></script>
