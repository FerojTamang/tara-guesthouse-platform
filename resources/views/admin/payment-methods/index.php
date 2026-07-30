<section aria-labelledby="payment-methods-heading">
    <div class="admin-page-heading d-flex flex-column flex-md-row align-items-md-start justify-content-md-between gap-3 mb-4">
        <div>
            <p class="admin-eyebrow mb-2">Manual payments</p>
            <h1 id="payment-methods-heading" class="h2 mb-2">Payment methods</h1>
            <p class="text-secondary mb-0">
                Configure the manual methods Guests may select when submitting payment evidence.
            </p>
        </div>
        <a class="btn btn-primary" href="/admin/payment-methods/create">Create payment method</a>
    </div>

    <?php if ($notice !== null): ?>
        <div class="alert alert-success" role="status"><?= $e($notice) ?></div>
    <?php endif; ?>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($methods === []): ?>
                <p class="p-4 mb-0 text-secondary">No payment methods have been configured yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Configured manual payment methods</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Name</th>
                                <th class="py-3" scope="col">Account reference</th>
                                <th class="py-3" scope="col">Proof</th>
                                <th class="py-3" scope="col">QR image</th>
                                <th class="py-3 text-end" scope="col">Status</th>
                                <th class="px-4 py-3 text-end" scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($methods as $method): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row"><?= $e($method['name']) ?></th>
                                    <td class="py-3">
                                        <?= $method['accountReference'] === null
                                            ? 'Not set'
                                            : $e($method['accountReference']) ?>
                                    </td>
                                    <td class="py-3">
                                        <?= $method['requiresProof'] ? 'Required' : 'Optional' ?>
                                    </td>
                                    <td class="py-3">
                                        <?= $method['qrConfigured'] ? 'Configured' : 'Not configured' ?>
                                    </td>
                                    <td class="py-3 text-end">
                                        <span class="badge <?= $method['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $method['active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end text-nowrap">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="/admin/payment-methods/<?= $e($method['id']) ?>/edit"
                                        >Edit</a>
                                        <form
                                            class="d-inline"
                                            method="post"
                                            action="/admin/payment-methods/<?= $e($method['id']) ?>/<?= $method['active'] ? 'deactivate' : 'activate' ?>"
                                        >
                                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                <?= $method['active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
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
