<?php
$paymentMethodError = $fieldErrors['paymentMethod'] ?? null;
$transactionReferenceError = $fieldErrors['transactionReference'] ?? null;
$proofError = $fieldErrors['proof'] ?? null;
$guestNotesError = $fieldErrors['guestNotes'] ?? null;
$proofDescription = $proofError !== null ? 'proof_upload_help proof_upload_error' : 'proof_upload_help';
?>
<section class="tg-journey-hero" aria-labelledby="payment-title"><div class="tg-container"><nav class="tg-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><span aria-current="page">Payment</span></nav><p class="tg-eyebrow">Manual payment</p><h1 id="payment-title">Submit your payment details</h1><p>Your booking remains pending until Tara Guest House verifies the payment.</p></div></section>

<div class="tg-container tg-journey-content">
    <ol class="tg-progress" aria-label="Booking progress"><li class="is-complete">Room</li><li class="is-complete">Guest Details</li><li aria-current="step">Payment</li></ol>
    <?php if ($error !== null): ?><div class="tg-alert tg-alert--error" role="alert" tabindex="-1"><strong>Please review your payment details.</strong><p><?= $e($error) ?></p></div><?php endif; ?>

    <div class="tg-payment-layout">
        <aside class="tg-payment-summary" aria-labelledby="payment-summary-title"><p class="tg-eyebrow">Pending booking</p><h2 id="payment-summary-title">Booking summary</h2><dl><div><dt>Booking reference</dt><dd><?= $e($booking['bookingReference']) ?></dd></div><div><dt>Exact amount due</dt><dd><?= $e($booking['currency']) ?> <?= $e($booking['totalAmount']) ?></dd></div><div><dt>Payment deadline</dt><dd><?= $e($booking['confirmationDeadlineAt']) ?></dd></div></dl><p>Payment verification is required before the booking is confirmed.</p></aside>

        <?php if ($paymentMethods === []): ?>
            <section class="tg-empty-state tg-payment-empty" aria-labelledby="payment-empty-title"><p class="tg-eyebrow">Assistance required</p><h2 id="payment-empty-title">Payment instructions are unavailable</h2><p>Payment instructions are not currently available online. Please contact Tara Guest House and provide your booking reference for assistance before the pending-booking deadline.</p><a class="tg-button tg-button--emerald" href="/contact" data-ripple>Contact Tara Guest House</a></section>
        <?php else: ?>
            <form method="post" action="/booking/payment" enctype="multipart/form-data" class="tg-payment-form tg-journey-form" novalidate data-payment-form>
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <h2>Choose a payment method</h2>
                <div><label for="payment_method_id">Payment method</label><select id="payment_method_id" name="payment_method_id" required data-payment-method <?= $paymentMethodError !== null ? 'aria-invalid="true" aria-describedby="payment_method_id_error"' : '' ?>><option value="">Choose a payment method</option><?php foreach ($paymentMethods as $method): ?><option value="<?= $e($method['id']) ?>" data-instructions="<?= $e($method['instructions'] ?? '') ?>" data-account-reference="<?= $e($method['accountReference'] ?? '') ?>" data-requires-proof="<?= $method['requiresProof'] ? '1' : '0' ?>" <?= $values['paymentMethodId'] === (string) $method['id'] ? 'selected' : '' ?>><?= $e($method['name'] . ($method['requiresProof'] ? ' — proof required' : '')) ?></option><?php endforeach; ?></select><?php if ($paymentMethodError !== null): ?><p id="payment_method_id_error" class="tg-field-error"><?= $e($paymentMethodError) ?></p><?php endif; ?></div>

                <div class="tg-method-details d-none" data-payment-method-details aria-live="polite"><p class="d-none" data-payment-instructions></p><p class="d-none"><strong>Account/reference:</strong> <span data-payment-account-reference></span></p></div>

                <div class="tg-payment-method-list" aria-label="Payment method guidance"><?php foreach ($paymentMethods as $method): ?><article class="tg-payment-method-card"><h3><?= $e($method['name']) ?></h3><?php if ($method['instructions'] !== null): ?><p><?= nl2br($e($method['instructions'])) ?></p><?php endif; ?><?php if ($method['accountReference'] !== null): ?><p><strong>Account/reference:</strong> <?= $e($method['accountReference']) ?></p><?php endif; ?><?php if ($method['qrImageUrl'] !== null): ?><img src="<?= $e($method['qrImageUrl']) ?>" alt="<?= $e($method['name']) ?> payment QR code" loading="lazy"><?php endif; ?><p><?= $method['requiresProof'] ? 'Payment proof is required.' : 'Payment proof is optional.' ?></p></article><?php endforeach; ?></div>

                <div><label for="transaction_reference">Transaction/reference code <span>(optional)</span></label><input id="transaction_reference" name="transaction_reference" type="text" maxlength="100" value="<?= $e($values['transactionReference']) ?>" autocomplete="off" <?= $transactionReferenceError !== null ? 'aria-invalid="true" aria-describedby="transaction_reference_error"' : '' ?>><?php if ($transactionReferenceError !== null): ?><p id="transaction_reference_error" class="tg-field-error"><?= $e($transactionReferenceError) ?></p><?php endif; ?></div>
                <div><label for="proof_upload">Payment proof <span data-proof-requirement>(required only for selected methods)</span></label><input id="proof_upload" name="proof_upload" type="file" accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" aria-describedby="<?= $e($proofDescription) ?>" <?= $proofError !== null ? 'aria-invalid="true"' : '' ?> data-payment-proof><p id="proof_upload_help" class="tg-form-note">Upload a JPG, PNG, or PDF no larger than 5 MiB. Files are stored privately.</p><?php if ($proofError !== null): ?><p id="proof_upload_error" class="tg-field-error"><?= $e($proofError) ?></p><?php endif; ?></div>
                <div><label for="guest_notes">Notes <span>(optional)</span></label><textarea id="guest_notes" name="guest_notes" rows="4" maxlength="65535" <?= $guestNotesError !== null ? 'aria-invalid="true" aria-describedby="guest_notes_error"' : '' ?>><?= $e($values['guestNotes']) ?></textarea><?php if ($guestNotesError !== null): ?><p id="guest_notes_error" class="tg-field-error"><?= $e($guestNotesError) ?></p><?php endif; ?></div>
                <button type="submit" class="tg-button tg-button--emerald tg-button--full" data-ripple>Submit payment for review</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script src="/assets/js/public-payment.js" defer></script>
