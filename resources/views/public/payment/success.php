<section class="container py-5" aria-labelledby="payment-success-title">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <p class="text-uppercase text-success fw-semibold mb-2">Payment submitted</p>
                    <h1 id="payment-success-title" class="display-6">Your payment is under review</h1>
                    <p class="lead">
                        Tara Guest House has received your payment details. Your booking remains
                        pending until an Administrator verifies them.
                    </p>

                    <dl class="row border-top border-bottom py-3 my-4">
                        <dt class="col-sm-5">Booking reference</dt>
                        <dd class="col-sm-7 fw-semibold"><?= $e($payment['booking_reference']) ?></dd>

                        <dt class="col-sm-5">Payment status</dt>
                        <dd class="col-sm-7 text-capitalize"><?= $e($payment['payment_status']) ?></dd>

                        <dt class="col-sm-5">Amount submitted</dt>
                        <dd class="col-sm-7">
                            <?= $e($payment['currency']) ?> <?= $e($payment['amount']) ?>
                        </dd>

                        <dt class="col-sm-5">Submitted at</dt>
                        <dd class="col-sm-7"><?= $e($payment['submitted_at']) ?></dd>
                    </dl>

                    <div class="alert alert-info mb-4" role="status">
                        Submission does not confirm the booking. Tara Guest House will review the
                        payment before the confirmation deadline.
                    </div>

                    <a href="/rooms" class="btn btn-primary">View rooms</a>
                    <a href="/availability" class="btn btn-link">Check another stay</a>
                </div>
            </div>
        </div>
    </div>
</section>
