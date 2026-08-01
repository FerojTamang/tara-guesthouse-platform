<section aria-labelledby="administrator-dashboard-heading">
    <div class="admin-page-heading mb-4">
        <p class="admin-eyebrow mb-2">Overview</p>
        <h1 id="administrator-dashboard-heading" class="h2 mb-2">Dashboard</h1>
        <p class="text-secondary mb-0">
            <?= isset($reportingDate) ? 'Operational overview for ' . $e($reportingDate) . ' (Asia/Kathmandu).' : 'Operational overview is unavailable.' ?>
        </p>
    </div>
    <?php if (!isset($metrics)): ?>
        <p class="alert alert-danger" role="alert">Dashboard metrics are currently unavailable.</p>
    <?php else: ?>
        <?php $sections = ['Room status' => [['Active rooms', $metrics['activeRooms']], ['Occupied rooms', $metrics['occupiedRooms']], ['Available rooms', $metrics['availableRooms']]], 'Today\'s operations' => [['Arrivals today', $metrics['arrivalsToday']], ['Departures today', $metrics['departuresToday']]], 'Booking overview' => [['Pending bookings', $metrics['pendingBookings']], ['Confirmed bookings', $metrics['confirmedBookings']]], 'Attention required' => [['Pending payment reviews', $metrics['pendingPaymentReviews']], ['Reviews awaiting moderation', $metrics['pendingReviewModeration']], ['Unread notifications', $metrics['unreadNotifications']]]]; ?>
        <?php foreach ($sections as $heading => $cards): ?>
            <section class="mb-4" aria-labelledby="<?= $e(strtolower(str_replace(' ', '-', $heading))) ?>-heading">
                <h2 id="<?= $e(strtolower(str_replace(' ', '-', $heading))) ?>-heading" class="h4 mb-3"><?= $e($heading) ?></h2>
                <div class="row g-3">
                    <?php foreach ($cards as [$label, $count]): ?>
                        <div class="col-12 col-sm-6 col-xl-4"><article class="card admin-panel h-100"><div class="card-body p-4"><p class="admin-card-kicker mb-2"><?= $e($label) ?></p><p class="display-6 mb-0"><?= $e($count) ?></p></div></article></div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
