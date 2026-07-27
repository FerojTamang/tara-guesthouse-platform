<section aria-labelledby="booking-detail-heading">
    <div class="admin-page-heading d-flex flex-column flex-md-row align-items-md-start justify-content-md-between gap-3 mb-4">
        <div>
            <p class="admin-eyebrow mb-2">Booking management</p>
            <h1 id="booking-detail-heading" class="h2 mb-2">
                <?= $e($booking['reference']) ?>
            </h1>
            <p class="text-secondary mb-0">
                Read-only Guest, stay, price, policy, status and event snapshots.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge fs-6 <?= $e($booking['statusBadge']) ?>">
                <?= $e($booking['statusLabel']) ?>
            </span>
            <a class="btn btn-outline-secondary" href="/admin/bookings">Back to bookings</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card admin-panel h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Guest snapshot</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Full name</dt>
                        <dd class="col-sm-7"><?= $e($booking['guestName']) ?></dd>

                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7"><?= $e($booking['guestEmail']) ?></dd>

                        <dt class="col-sm-5">Phone</dt>
                        <dd class="col-sm-7"><?= $e($booking['guestPhone']) ?></dd>

                        <dt class="col-sm-5">Adults</dt>
                        <dd class="col-sm-7"><?= $e($booking['adults']) ?></dd>

                        <dt class="col-sm-5">Children</dt>
                        <dd class="col-sm-7"><?= $e($booking['children']) ?></dd>

                        <dt class="col-sm-5">Special request</dt>
                        <dd class="col-sm-7 mb-0">
                            <?php if ($booking['specialRequest'] === null): ?>
                                <span class="text-secondary">None</span>
                            <?php else: ?>
                                <span style="white-space: pre-wrap;"><?= $e($booking['specialRequest']) ?></span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card admin-panel h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Room and price snapshot</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Room identifier</dt>
                        <dd class="col-sm-7">#<?= $e($booking['roomId']) ?></dd>

                        <dt class="col-sm-5">Check-in</dt>
                        <dd class="col-sm-7"><?= $e($booking['checkIn']) ?></dd>

                        <dt class="col-sm-5">Check-out</dt>
                        <dd class="col-sm-7"><?= $e($booking['checkOut']) ?></dd>

                        <dt class="col-sm-5">Nights</dt>
                        <dd class="col-sm-7"><?= $e($booking['nights']) ?></dd>

                        <dt class="col-sm-5">Price per night</dt>
                        <dd class="col-sm-7"><?= $e($booking['pricePerNight']) ?></dd>

                        <dt class="col-sm-5">Room subtotal</dt>
                        <dd class="col-sm-7"><?= $e($booking['roomSubtotal']) ?></dd>

                        <dt class="col-sm-5">Stored room amount</dt>
                        <dd class="col-sm-7"><?= $e($booking['roomAmount']) ?></dd>

                        <dt class="col-sm-5">Stored total</dt>
                        <dd class="col-sm-7 fw-semibold mb-0">
                            <?= $e($booking['totalAmount']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card admin-panel h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Policy and deadline snapshot</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Terms accepted</dt>
                        <dd class="col-sm-7"><?= $e($booking['termsAcceptedAt']) ?></dd>

                        <dt class="col-sm-5">Policy version</dt>
                        <dd class="col-sm-7">
                            <?= $booking['policyVersion'] === null ? 'Not recorded' : $e($booking['policyVersion']) ?>
                        </dd>

                        <dt class="col-sm-5">Confirmation deadline</dt>
                        <dd class="col-sm-7 mb-0">
                            <?= $e($booking['confirmationDeadlineAt']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card admin-panel h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Operational timestamps</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Created</dt>
                        <dd class="col-sm-7"><?= $e($booking['createdAt']) ?></dd>

                        <dt class="col-sm-5">Updated</dt>
                        <dd class="col-sm-7"><?= $e($booking['updatedAt']) ?></dd>

                        <dt class="col-sm-5">Confirmed</dt>
                        <dd class="col-sm-7">
                            <?= $booking['confirmedAt'] === null ? 'Not recorded' : $e($booking['confirmedAt']) ?>
                        </dd>

                        <dt class="col-sm-5">Checked in</dt>
                        <dd class="col-sm-7">
                            <?= $booking['checkedInAt'] === null ? 'Not recorded' : $e($booking['checkedInAt']) ?>
                        </dd>

                        <dt class="col-sm-5">Checked out</dt>
                        <dd class="col-sm-7">
                            <?= $booking['checkedOutAt'] === null ? 'Not recorded' : $e($booking['checkedOutAt']) ?>
                        </dd>

                        <dt class="col-sm-5">Cancelled</dt>
                        <dd class="col-sm-7">
                            <?= $booking['cancelledAt'] === null ? 'Not recorded' : $e($booking['cancelledAt']) ?>
                        </dd>

                        <dt class="col-sm-5">Cancellation reason</dt>
                        <dd class="col-sm-7">
                            <?= $booking['cancellationReason'] === null ? 'Not recorded' : $e($booking['cancellationReason']) ?>
                        </dd>

                        <dt class="col-sm-5">Rejected</dt>
                        <dd class="col-sm-7">
                            <?= $booking['rejectedAt'] === null ? 'Not recorded' : $e($booking['rejectedAt']) ?>
                        </dd>

                        <dt class="col-sm-5">Rejection reason</dt>
                        <dd class="col-sm-7 mb-0">
                            <?= $booking['rejectionReason'] === null ? 'Not recorded' : $e($booking['rejectionReason']) ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card admin-panel mt-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Booking event history</h2>
        </div>
        <div class="card-body p-0">
            <?php if ($booking['events'] === []): ?>
                <p class="p-4 mb-0 text-secondary">No booking events are recorded.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <caption class="visually-hidden">Booking event history</caption>
                        <thead>
                            <tr>
                                <th class="px-4 py-3" scope="col">Event</th>
                                <th class="py-3" scope="col">State change</th>
                                <th class="py-3" scope="col">Actor</th>
                                <th class="py-3" scope="col">Reason</th>
                                <th class="px-4 py-3" scope="col">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['events'] as $event): ?>
                                <tr>
                                    <th class="px-4 py-3" scope="row">
                                        <?= $e($event['eventType']) ?>
                                        <?php if ($event['metadataJson'] !== null): ?>
                                            <details class="small mt-2">
                                                <summary>Metadata</summary>
                                                <code class="d-block text-break mt-1">
                                                    <?= $e($event['metadataJson']) ?>
                                                </code>
                                            </details>
                                        <?php endif; ?>
                                    </th>
                                    <td class="py-3">
                                        <?= $e($event['fromStatus']) ?>
                                        <span aria-hidden="true">→</span>
                                        <?= $e($event['toStatus']) ?>
                                    </td>
                                    <td class="py-3"><?= $e($event['actor']) ?></td>
                                    <td class="py-3">
                                        <?= $event['reason'] === null ? 'Not recorded' : $e($event['reason']) ?>
                                    </td>
                                    <td class="px-4 py-3"><?= $e($event['createdAt']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-secondary mt-4 mb-0" role="note">
        This page is read-only in S2-011. Approved state-transition actions are implemented separately in S2-012.
    </div>
</section>
