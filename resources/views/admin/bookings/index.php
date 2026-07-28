<section aria-labelledby="bookings-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Booking management</p>
        <h1 id="bookings-heading" class="h2 mb-2">Bookings</h1>
        <p class="text-secondary mb-0">
            Review Guest booking snapshots and filter by the canonical booking state.
        </p>
    </div>

    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <div class="card admin-panel mb-4">
        <div class="card-body">
            <form method="get" action="/admin/bookings" class="row g-3 align-items-end">
                <div class="col-sm-8 col-md-5 col-lg-4">
                    <label class="form-label" for="booking-status">Booking status</label>
                    <select class="form-select" id="booking-status" name="status">
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
                    <a class="btn btn-outline-secondary" href="/admin/bookings">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card admin-panel">
        <div class="card-body p-0">
            <?php if ($bookings === []): ?>
                <p class="p-4 mb-0 text-secondary">No bookings match this view.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Current Guest bookings</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Booking</th>
                                <th class="py-3" scope="col">Guest</th>
                                <th class="py-3" scope="col">Stay</th>
                                <th class="py-3" scope="col">Guests</th>
                                <th class="py-3" scope="col">Total</th>
                                <th class="py-3" scope="col">Status</th>
                                <th class="px-4 py-3 text-end" scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row">
                                        <span class="d-block"><?= $e($booking['reference']) ?></span>
                                        <span class="d-block small text-secondary">
                                            Received <?= $e($booking['createdAt']) ?>
                                        </span>
                                    </th>
                                    <td class="py-3"><?= $e($booking['guestName']) ?></td>
                                    <td class="py-3">
                                        <span class="d-block"><?= $e($booking['checkIn']) ?></span>
                                        <span class="d-block small text-secondary">
                                            to <?= $e($booking['checkOut']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3"><?= $e($booking['guests']) ?></td>
                                    <td class="py-3"><?= $e($booking['total']) ?></td>
                                    <td class="py-3">
                                        <span class="badge <?= $e($booking['statusBadge']) ?>">
                                            <?= $e($booking['statusLabel']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <a
                                            class="btn btn-sm btn-outline-primary"
                                            href="/admin/bookings/<?= $e($booking['id']) ?>"
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
