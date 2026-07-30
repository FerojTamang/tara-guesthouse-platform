<section aria-labelledby="invoices-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Financial snapshots</p>
        <h1 id="invoices-heading" class="h2 mb-2">Invoices</h1>
        <p class="text-secondary mb-0">
            Issued invoices retain their original NPR totals and stable invoice number. They cannot be deleted or edited.
        </p>
    </div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($invoices === []): ?>
                <p class="p-4 mb-0 text-secondary">No invoices have been issued.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Issued invoice snapshots</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Invoice</th>
                                <th class="py-3" scope="col">Booking</th>
                                <th class="py-3" scope="col">Guest</th>
                                <th class="py-3" scope="col">Total</th>
                                <th class="py-3" scope="col">Issued</th>
                                <th class="px-4 py-3 text-end" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row">
                                        <?= $e($invoice['number']) ?>
                                    </th>
                                    <td class="py-3">
                                        <a href="/admin/bookings/<?= $e($invoice['bookingId']) ?>">
                                            <?= $e($invoice['bookingReference']) ?>
                                        </a>
                                    </td>
                                    <td class="py-3"><?= $e($invoice['guestName']) ?></td>
                                    <td class="py-3"><?= $e($invoice['total']) ?></td>
                                    <td class="py-3"><?= $e($invoice['issuedAt']) ?></td>
                                    <td class="px-4 py-3 text-end">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="/admin/invoices/<?= $e($invoice['id']) ?>"
                                        >View</a>
                                        <a
                                            class="btn btn-sm btn-outline-secondary"
                                            href="/admin/invoices/<?= $e($invoice['id']) ?>/print"
                                        >Print</a>
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
