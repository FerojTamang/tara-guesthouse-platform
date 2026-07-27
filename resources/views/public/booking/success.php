<section class="container py-5" aria-labelledby="booking-success-title">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <p class="text-uppercase text-success fw-semibold mb-2">Request received</p>
                    <h1 id="booking-success-title" class="display-6">Your booking is pending</h1>
                    <p class="lead">
                        Tara Guest House has received your request. Keep the booking
                        reference below for future communication.
                    </p>

                    <dl class="row border-top border-bottom py-3 my-4">
                        <dt class="col-sm-5">Booking reference</dt>
                        <dd class="col-sm-7 fw-semibold"><?= $e($booking['booking_reference']) ?></dd>

                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7 text-capitalize"><?= $e($booking['booking_status']) ?></dd>

                        <dt class="col-sm-5">Confirmation deadline</dt>
                        <dd class="col-sm-7"><?= $e($booking['confirmation_deadline_at']) ?></dd>

                        <dt class="col-sm-5">Estimated total</dt>
                        <dd class="col-sm-7">
                            <?= $e($booking['currency']) ?> <?= $e($booking['total_amount']) ?>
                        </dd>
                    </dl>

                    <div class="alert alert-info mb-4" role="status">
                        No payment has been recorded. Contact Tara Guest House before
                        the confirmation deadline to arrange the approved manual payment.
                    </div>

                    <a href="/rooms" class="btn btn-primary">View rooms</a>
                    <a href="/availability" class="btn btn-link">Check another stay</a>
                </div>
            </div>
        </div>
    </div>
</section>
