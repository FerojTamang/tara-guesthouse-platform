<section aria-labelledby="invoice-detail-heading">
    <div class="admin-page-heading mb-4 d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="admin-eyebrow mb-2">Issued invoice</p>
            <h1 id="invoice-detail-heading" class="h2 mb-2"><?= $e($invoice['number']) ?></h1>
            <p class="text-secondary mb-0">
                Stable financial snapshot issued <?= $e($invoice['issuedAt']) ?>.
            </p>
        </div>
        <div class="d-flex align-items-start gap-2">
            <a class="btn btn-outline-secondary" href="/admin/invoices">Back to invoices</a>
            <a
                class="btn btn-primary"
                href="/admin/invoices/<?= $e($invoice['id']) ?>/print"
            >Print invoice</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card admin-panel h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Invoice items</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <caption class="visually-hidden">Immutable invoice line items</caption>
                            <thead>
                                <tr>
                                    <th class="px-4 py-3" scope="col">Description</th>
                                    <th class="py-3 text-end" scope="col">Quantity</th>
                                    <th class="py-3 text-end" scope="col">Unit price</th>
                                    <th class="px-4 py-3 text-end" scope="col">Line total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoice['items'] as $item): ?>
                                    <tr>
                                        <th class="px-4 py-3" scope="row"><?= $e($item['description']) ?></th>
                                        <td class="py-3 text-end"><?= $e($item['quantity']) ?></td>
                                        <td class="py-3 text-end"><?= $e($invoice['currency']) ?> <?= $e($item['unitPrice']) ?></td>
                                        <td class="px-4 py-3 text-end"><?= $e($invoice['currency']) ?> <?= $e($item['lineTotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="px-4 py-3 text-end" colspan="3" scope="row">Subtotal</th>
                                    <td class="px-4 py-3 text-end"><?= $e($invoice['currency']) ?> <?= $e($invoice['subtotal']) ?></td>
                                </tr>
                                <tr>
                                    <th class="px-4 py-3 text-end" colspan="3" scope="row">Tax</th>
                                    <td class="px-4 py-3 text-end"><?= $e($invoice['currency']) ?> <?= $e($invoice['taxAmount']) ?></td>
                                </tr>
                                <tr>
                                    <th class="px-4 py-3 text-end" colspan="3" scope="row">Total</th>
                                    <td class="px-4 py-3 text-end fw-bold"><?= $e($invoice['currency']) ?> <?= $e($invoice['totalAmount']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card admin-panel mb-4">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Booking and Guest</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Booking</dt>
                        <dd class="col-sm-7">
                            <a href="/admin/bookings/<?= $e($invoice['bookingId']) ?>">
                                <?= $e($invoice['bookingReference']) ?>
                            </a>
                        </dd>
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7"><?= $e($invoice['bookingStatus']) ?></dd>
                        <dt class="col-sm-5">Guest</dt>
                        <dd class="col-sm-7"><?= $e($invoice['guestName']) ?></dd>
                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7 text-break"><?= $e($invoice['guestEmail']) ?></dd>
                        <dt class="col-sm-5">Phone</dt>
                        <dd class="col-sm-7"><?= $e($invoice['guestPhone']) ?></dd>
                        <dt class="col-sm-5">Stay</dt>
                        <dd class="col-sm-7 mb-0"><?= $e($invoice['checkIn']) ?> to <?= $e($invoice['checkOut']) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card admin-panel">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Issue evidence</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Payment</dt>
                        <dd class="col-sm-7">
                            <a href="/admin/payments/<?= $e($invoice['paymentId']) ?>">#<?= $e($invoice['paymentId']) ?></a>
                        </dd>
                        <dt class="col-sm-5">Method</dt>
                        <dd class="col-sm-7"><?= $e($invoice['paymentMethod']) ?></dd>
                        <dt class="col-sm-5">Reference</dt>
                        <dd class="col-sm-7 text-break">
                            <?= $invoice['transactionReference'] === null ? 'Not supplied' : $e($invoice['transactionReference']) ?>
                        </dd>
                        <dt class="col-sm-5">Verified</dt>
                        <dd class="col-sm-7"><?= $e($invoice['verifiedAt']) ?></dd>
                        <dt class="col-sm-5">Issued by</dt>
                        <dd class="col-sm-7 mb-0"><?= $e($invoice['createdBy']) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</section>
