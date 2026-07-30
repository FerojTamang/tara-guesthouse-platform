<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $e($title) ?> | Tara Guest House</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
    <style>
        @page { margin: 16mm; }
        body { background: #f8f9fa; }
        .invoice-sheet { max-width: 900px; margin: 2rem auto; background: #fff; }
        @media print {
            body { background: #fff; }
            .invoice-sheet { max-width: none; margin: 0; border: 0 !important; box-shadow: none !important; }
            .print-controls { display: none !important; }
        }
    </style>
</head>
<body>
    <main class="invoice-sheet border rounded shadow-sm p-4 p-md-5">
        <div class="print-controls d-flex justify-content-end gap-2 mb-4">
            <a class="btn btn-outline-secondary" href="/admin/invoices/<?= $e($invoice['id']) ?>">Back</a>
            <button class="btn btn-primary" type="button" onclick="window.print()">Print</button>
        </div>

        <header class="d-flex flex-column flex-md-row justify-content-between gap-4 border-bottom pb-4 mb-4">
            <div>
                <p class="text-uppercase small text-secondary mb-1">Tara Guest House</p>
                <h1 class="h3 mb-1">Invoice</h1>
                <p class="mb-0"><?= $e($invoice['number']) ?></p>
            </div>
            <dl class="row mb-0 text-md-end">
                <dt class="col-5">Issued</dt>
                <dd class="col-7"><?= $e($invoice['issuedAt']) ?></dd>
                <dt class="col-5">Currency</dt>
                <dd class="col-7 mb-0"><?= $e($invoice['currency']) ?></dd>
            </dl>
        </header>

        <div class="row g-4 mb-4">
            <section class="col-md-6" aria-labelledby="print-bill-to">
                <h2 id="print-bill-to" class="h6 text-uppercase text-secondary">Bill to</h2>
                <p class="fw-semibold mb-1"><?= $e($invoice['guestName']) ?></p>
                <p class="mb-1"><?= $e($invoice['guestEmail']) ?></p>
                <p class="mb-0"><?= $e($invoice['guestPhone']) ?></p>
            </section>
            <section class="col-md-6" aria-labelledby="print-booking">
                <h2 id="print-booking" class="h6 text-uppercase text-secondary">Booking</h2>
                <p class="mb-1">Reference: <?= $e($invoice['bookingReference']) ?></p>
                <p class="mb-1">Stay: <?= $e($invoice['checkIn']) ?> to <?= $e($invoice['checkOut']) ?></p>
                <p class="mb-0">Verified payment #<?= $e($invoice['paymentId']) ?></p>
            </section>
        </div>

        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Description</th>
                        <th class="text-end" scope="col">Quantity</th>
                        <th class="text-end" scope="col">Unit price</th>
                        <th class="text-end" scope="col">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice['items'] as $item): ?>
                        <tr>
                            <th scope="row"><?= $e($item['description']) ?></th>
                            <td class="text-end"><?= $e($item['quantity']) ?></td>
                            <td class="text-end"><?= $e($invoice['currency']) ?> <?= $e($item['unitPrice']) ?></td>
                            <td class="text-end"><?= $e($invoice['currency']) ?> <?= $e($item['lineTotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-end" colspan="3" scope="row">Subtotal</th>
                        <td class="text-end"><?= $e($invoice['currency']) ?> <?= $e($invoice['subtotal']) ?></td>
                    </tr>
                    <tr>
                        <th class="text-end" colspan="3" scope="row">Tax</th>
                        <td class="text-end"><?= $e($invoice['currency']) ?> <?= $e($invoice['taxAmount']) ?></td>
                    </tr>
                    <tr>
                        <th class="text-end" colspan="3" scope="row">Total</th>
                        <td class="text-end fw-bold"><?= $e($invoice['currency']) ?> <?= $e($invoice['totalAmount']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <footer class="border-top pt-3 small text-secondary">
            This invoice is a stable financial snapshot. Tax is NPR 0.00 under the approved Version 1 policy.
        </footer>
    </main>
</body>
</html>
