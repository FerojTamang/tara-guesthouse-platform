<section aria-labelledby="activities-heading">
    <div class="row align-items-end g-3 mb-4">
        <div class="col-lg-8">
            <p class="text-uppercase fw-semibold text-secondary mb-2">Explore Dhulikhel</p>
            <h1 id="activities-heading" class="display-6 mb-2">Activities</h1>
            <p class="lead mb-0">Discover experiences available during your stay at Tara Guest House.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a class="btn btn-outline-primary" href="/availability">Check room availability</a>
        </div>
    </div>

    <?php if ($activities === []): ?>
        <div class="alert alert-info" role="status">
            No activities are currently published. Please check again later.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($activities as $index => $activity): ?>
                <?php $headingId = 'activity-heading-' . (string) $index; ?>
                <div class="col">
                    <article class="card h-100 shadow-sm" aria-labelledby="<?= $e($headingId) ?>">
                        <div class="card-body d-flex flex-column">
                            <h2 id="<?= $e($headingId) ?>" class="h4 card-title">
                                <?= $e($activity['title']) ?>
                            </h2>
                            <p class="card-text flex-grow-1">
                                <?= nl2br($e($activity['description'])) ?>
                            </p>
                            <p class="fs-5 fw-semibold mb-0">NPR <?= $e($activity['price']) ?></p>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
