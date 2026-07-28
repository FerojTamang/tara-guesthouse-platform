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

    <div class="card admin-panel mt-4" aria-labelledby="booking-status-actions-heading">
        <div class="card-header bg-white">
            <h2 id="booking-status-actions-heading" class="h5 mb-0">Status actions</h2>
        </div>
        <div class="card-body">
            <?php if ($booking['statusActions'] === []): ?>
                <p class="mb-0 text-secondary">
                    This booking has no permitted Administrator status actions.
                </p>
            <?php else: ?>
                <p class="text-secondary">
                    Only the actions allowed from the current state are shown. Every successful change is recorded in booking history and the operational audit log.
                </p>
                <div
                    id="booking-status-action-message"
                    class="alert d-none"
                    role="alert"
                    aria-live="polite"
                ></div>
                <div class="row g-3">
                    <?php foreach ($booking['statusActions'] as $index => $action): ?>
                        <div class="col-lg-6">
                            <form
                                method="post"
                                action="/api/v1/admin/bookings/<?= $e($booking['id']) ?>/status"
                                class="border rounded p-3 h-100"
                                data-booking-status-form
                                data-target-status="<?= $e($action['status']) ?>"
                            >
                                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                <h3 class="h6"><?= $e($action['label']) ?></h3>

                                <?php if ($action['requiresReason']): ?>
                                    <div class="mb-3">
                                        <label
                                            class="form-label"
                                            for="booking-status-reason-<?= $e($index) ?>"
                                        ><?= $e($action['reasonLabel']) ?></label>
                                        <textarea
                                            class="form-control"
                                            id="booking-status-reason-<?= $e($index) ?>"
                                            name="reason"
                                            rows="3"
                                            maxlength="1000"
                                            required
                                        ></textarea>
                                        <div class="form-text">
                                            Required. Maximum 1,000 characters.
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <button
                                    class="btn <?= $e($action['buttonClass']) ?>"
                                    type="submit"
                                ><?= $e($action['label']) ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <noscript>
                    <p class="alert alert-warning mt-3 mb-0">
                        JavaScript is required to submit a booking status action safely.
                    </p>
                </noscript>
            <?php endif; ?>
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

</section>

<?php if ($booking['statusActions'] !== []): ?>
    <script>
        (() => {
            const forms = document.querySelectorAll('[data-booking-status-form]');
            const message = document.getElementById('booking-status-action-message');

            const showMessage = (text, type) => {
                if (!(message instanceof HTMLElement)) {
                    return;
                }

                message.textContent = text;
                message.className = `alert alert-${type}`;
            };

            forms.forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const target = form.dataset.targetStatus;
                    const csrf = form.querySelector('input[name="_csrf"]');
                    const reason = form.querySelector('textarea[name="reason"]');
                    const button = form.querySelector('button[type="submit"]');

                    if (!target || !(csrf instanceof HTMLInputElement)) {
                        showMessage('The booking action could not be prepared.', 'danger');
                        return;
                    }

                    const payload = {status: target};

                    if (reason instanceof HTMLTextAreaElement) {
                        payload.reason = reason.value;
                    }

                    if (button instanceof HTMLButtonElement) {
                        button.disabled = true;
                    }

                    showMessage('Saving the booking status…', 'info');

                    try {
                        const response = await fetch(form.action, {
                            method: 'PATCH',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrf.value,
                            },
                            body: JSON.stringify(payload),
                        });
                        const body = await response.json().catch(() => null);

                        if (!response.ok) {
                            const validationMessage = body?.errors?.booking?.[0];
                            throw new Error(
                                validationMessage
                                || body?.message
                                || 'The booking status could not be updated.',
                            );
                        }

                        showMessage('Booking status updated. Refreshing…', 'success');
                        window.location.reload();
                    } catch (failure) {
                        showMessage(
                            failure instanceof Error
                                ? failure.message
                                : 'The booking status could not be updated.',
                            'danger',
                        );

                        if (button instanceof HTMLButtonElement) {
                            button.disabled = false;
                        }
                    }
                });
            });
        })();
    </script>
<?php endif; ?>
