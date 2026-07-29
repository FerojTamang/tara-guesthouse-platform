<section aria-labelledby="payment-detail-heading">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-3 mb-4">
        <div class="admin-page-heading mb-0">
            <p class="admin-eyebrow mb-2">Payment review</p>
            <h1 id="payment-detail-heading" class="h2 mb-2">Payment #<?= $e($payment['id']) ?></h1>
            <p class="text-secondary mb-0">
                Retained attempt for booking
                <a href="/admin/bookings/<?= $e($payment['bookingId']) ?>"><?= $e($payment['bookingReference']) ?></a>.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($payment['proofUrl'] !== null): ?>
                <a
                    class="btn btn-outline-primary"
                    href="<?= $e($payment['proofUrl']) ?>"
                    target="_blank"
                    rel="noopener"
                >Open proof</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="/admin/payments">Back to payments</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card admin-panel h-100">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-0">Payment attempt</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">
                            <span class="badge <?= $e($payment['statusBadge']) ?>"><?= $e($payment['statusLabel']) ?></span>
                        </dd>

                        <dt class="col-sm-5">Amount</dt>
                        <dd class="col-sm-7"><?= $e($payment['amount']) ?></dd>

                        <dt class="col-sm-5">Payment method</dt>
                        <dd class="col-sm-7">
                            <span class="d-block"><?= $e($payment['paymentMethod']) ?></span>
                            <span class="d-block small text-secondary">
                                <?= $payment['paymentMethodActive'] ? 'Currently active' : 'Currently inactive' ?>
                                · <?= $payment['paymentMethodRequiresProof'] ? 'Proof required' : 'Proof optional' ?>
                            </span>
                        </dd>

                        <dt class="col-sm-5">Account reference</dt>
                        <dd class="col-sm-7"><?= $e($payment['paymentMethodAccountReference'] ?? 'Not configured') ?></dd>

                        <dt class="col-sm-5">Transaction reference</dt>
                        <dd class="col-sm-7"><?= $e($payment['transactionReference'] ?? 'Not provided') ?></dd>

                        <dt class="col-sm-5">Guest notes</dt>
                        <dd class="col-sm-7"><?= $e($payment['guestNotes'] ?? 'Not provided') ?></dd>

                        <dt class="col-sm-5">Proof</dt>
                        <dd class="col-sm-7">
                            <?php if ($payment['proofUrl'] === null): ?>
                                Not provided
                            <?php else: ?>
                                <a href="<?= $e($payment['proofUrl']) ?>" target="_blank" rel="noopener">Open protected proof</a>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-5">Submitted</dt>
                        <dd class="col-sm-7"><?= $e($payment['submittedAt'] ?? 'Not submitted') ?></dd>

                        <dt class="col-sm-5">Verified</dt>
                        <dd class="col-sm-7">
                            <?= $e($payment['verifiedAt'] ?? 'Not verified') ?>
                            <?php if ($payment['verifiedBy'] !== null): ?>
                                <span class="d-block small text-secondary">by <?= $e($payment['verifiedBy']) ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-5">Rejected</dt>
                        <dd class="col-sm-7">
                            <?= $e($payment['rejectedAt'] ?? 'Not rejected') ?>
                            <?php if ($payment['rejectedBy'] !== null): ?>
                                <span class="d-block small text-secondary">by <?= $e($payment['rejectedBy']) ?></span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-5">Verification note</dt>
                        <dd class="col-sm-7"><?= $e($payment['verificationNote'] ?? 'Not recorded') ?></dd>

                        <dt class="col-sm-5">Created</dt>
                        <dd class="col-sm-7"><?= $e($payment['createdAt']) ?></dd>

                        <dt class="col-sm-5">Last updated</dt>
                        <dd class="col-sm-7 mb-0"><?= $e($payment['updatedAt']) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card admin-panel mb-4">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-0">Booking context</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Booking</dt>
                        <dd class="col-sm-7">
                            <a href="/admin/bookings/<?= $e($payment['bookingId']) ?>"><?= $e($payment['bookingReference']) ?></a>
                        </dd>

                        <dt class="col-sm-5">Booking status</dt>
                        <dd class="col-sm-7">
                            <span class="badge <?= $e($payment['bookingStatusBadge']) ?>"><?= $e($payment['bookingStatusLabel']) ?></span>
                        </dd>

                        <dt class="col-sm-5">Booking total</dt>
                        <dd class="col-sm-7"><?= $e($payment['bookingTotalAmount']) ?></dd>

                        <dt class="col-sm-5">Stay</dt>
                        <dd class="col-sm-7"><?= $e($payment['checkIn']) ?> to <?= $e($payment['checkOut']) ?></dd>

                        <dt class="col-sm-5">Confirmation deadline</dt>
                        <dd class="col-sm-7 mb-0"><?= $e($payment['confirmationDeadlineAt']) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card admin-panel">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-0">Guest snapshot</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8"><?= $e($payment['guestName']) ?></dd>

                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8"><?= $e($payment['guestEmail']) ?></dd>

                        <dt class="col-sm-4">Phone</dt>
                        <dd class="col-sm-8 mb-0"><?= $e($payment['guestPhone']) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</section>
