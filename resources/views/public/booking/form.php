<section class="container py-5" aria-labelledby="booking-title">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <header class="mb-4">
                <p class="text-uppercase text-secondary fw-semibold mb-2">Guest booking request</p>
                <h1 id="booking-title" class="display-6">Request your stay</h1>
                <p class="lead mb-0">
                    Your request remains pending until Tara Guest House confirms it.
                    No online payment is collected on this form.
                </p>
            </header>

            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert">
                    <?= $e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/booking" class="card border-0 shadow-sm" novalidate>
                <div class="card-body p-4">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="room_id" class="form-label">Room ID</label>
                            <input
                                id="room_id"
                                name="room_id"
                                type="number"
                                min="1"
                                step="1"
                                class="form-control"
                                value="<?= $e($values['roomId']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="check_in" class="form-label">Check-in</label>
                            <input
                                id="check_in"
                                name="check_in"
                                type="date"
                                class="form-control"
                                value="<?= $e($values['checkIn']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="check_out" class="form-label">Check-out</label>
                            <input
                                id="check_out"
                                name="check_out"
                                type="date"
                                class="form-control"
                                value="<?= $e($values['checkOut']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="guest_full_name" class="form-label">Full name</label>
                            <input
                                id="guest_full_name"
                                name="guest_full_name"
                                type="text"
                                maxlength="120"
                                autocomplete="name"
                                class="form-control"
                                value="<?= $e($values['guestFullName']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="guest_email" class="form-label">Email</label>
                            <input
                                id="guest_email"
                                name="guest_email"
                                type="email"
                                maxlength="150"
                                autocomplete="email"
                                class="form-control"
                                value="<?= $e($values['guestEmail']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="guest_phone" class="form-label">Phone</label>
                            <input
                                id="guest_phone"
                                name="guest_phone"
                                type="tel"
                                maxlength="30"
                                autocomplete="tel"
                                class="form-control"
                                value="<?= $e($values['guestPhone']) ?>"
                                required
                            >
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="adults" class="form-label">Adults</label>
                            <input
                                id="adults"
                                name="adults"
                                type="number"
                                min="1"
                                step="1"
                                class="form-control"
                                value="<?= $e($values['adults']) ?>"
                                required
                            >
                        </div>

                        <div class="col-6 col-md-3">
                            <label for="children" class="form-label">Children</label>
                            <input
                                id="children"
                                name="children"
                                type="number"
                                min="0"
                                step="1"
                                class="form-control"
                                value="<?= $e($values['children']) ?>"
                                required
                            >
                        </div>

                        <div class="col-12">
                            <label for="special_request" class="form-label">
                                Special request <span class="text-secondary">(optional)</span>
                            </label>
                            <textarea
                                id="special_request"
                                name="special_request"
                                rows="4"
                                maxlength="1000"
                                class="form-control"
                            ><?= $e($values['specialRequest']) ?></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input
                                    id="terms_accepted"
                                    name="terms_accepted"
                                    type="checkbox"
                                    value="1"
                                    class="form-check-input"
                                    <?= $values['termsAccepted'] ? 'checked' : '' ?>
                                    required
                                >
                                <label for="terms_accepted" class="form-check-label">
                                    I confirm that the booking details are correct and accept the booking policy.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-0 px-4 pb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Submit booking request
                    </button>
                    <a href="/availability" class="btn btn-link">Check availability again</a>
                </div>
            </form>
        </div>
    </div>
</section>
