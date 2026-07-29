<section aria-labelledby="payments-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Payment review</p>
        <h1 id="payments-heading" class="h2 mb-2">Payments</h1>
        <p class="text-secondary mb-0">
            Review every retained manual payment attempt separately. Status changes are handled only through the approved verification workflow.
        </p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <div class="card admin-panel mb-4">
        <div class="card-body">
            <form method="get" action="/admin/payments" class="row g-3 align-items-end">
                <div class="col-sm-8 col-md-5 col-lg-4">
                    <label class="form-label" for="payment-status">Payment status</label>
                    <select class="form-select" id="payment-status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($statusOptions as $option): ?>
                            <option
                                value="<?= $e($option['value']) ?>"
                                <?= $selectedStatus === $option['value'] ? 'selected' : '' ?>
                            >
                                <?= $e($option['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-auto">
                    <button class="btn btn-primary" type="submit">Apply filter</button>
                    <a class="btn btn-outline-secondary" href="/admin/payments">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($payments === []): ?>
                <p class="p-4 mb-0 text-secondary">No payment attempts match this view.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Retained manual payment attempts</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Attempt</th>
                                <th class="py-3" scope="col">Booking</th>
                                <th class="py-3" scope="col">Guest</th>
                                <th class="py-3" scope="col">Method</th>
                                <th class="py-3" scope="col">Amount</th>
                                <th class="py-3" scope="col">Status</th>
                                <th class="py-3" scope="col">Submitted</th>
                                <th class="px-4 py-3 text-end" scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row">
                                        <span class="d-block">#<?= $e($payment['id']) ?></span>
                                        <span class="d-block small text-secondary">
                                            Created <?= $e($payment['createdAt']) ?>
                                        </span>
                                    </th>
                                    <td class="py-3">
                                        <a href="/admin/bookings/<?= $e($payment['bookingId']) ?>"><?= $e($payment['bookingReference']) ?></a>
                                    </td>
                                    <td class="py-3"><?= $e($payment['guestName']) ?></td>
                                    <td class="py-3">
                                        <span class="d-block"><?= $e($payment['paymentMethod']) ?></span>
                                        <?php if ($payment['hasProof']): ?>
                                            <span class="d-block small text-secondary">Proof attached</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3"><?= $e($payment['amount']) ?></td>
                                    <td class="py-3">
                                        <span class="badge <?= $e($payment['statusBadge']) ?>">
                                            <?= $e($payment['statusLabel']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3"><?= $e($payment['submittedAt']) ?></td>
                                    <td class="px-4 py-3 text-end">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="/admin/payments/<?= $e($payment['id']) ?>"
                                        >View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
