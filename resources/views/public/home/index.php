<section class="py-4 py-lg-5" aria-labelledby="home-heading">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <p class="text-uppercase fw-semibold text-secondary mb-2">Welcome</p>
            <h1 id="home-heading" class="display-4 fw-semibold mb-3"><?= $e($siteName) ?></h1>
            <p class="lead mb-4">A comfortable base for exploring Dhulikhel and the surrounding hills.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary btn-lg" href="/availability">Check availability</a>
                <a class="btn btn-outline-primary btn-lg" href="/rooms">Explore rooms</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="p-4 rounded-3 bg-body-tertiary border">
                <h2 class="h4">Plan your stay</h2>
                <p class="mb-0">Browse published rooms, activities, and gallery images before choosing your dates.</p>
            </div>
        </div>
    </div>
</section>

<section class="py-4" aria-labelledby="home-rooms-heading">
    <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
        <div><p class="text-uppercase fw-semibold text-secondary mb-1">Stay</p><h2 id="home-rooms-heading" class="h1 mb-0">Rooms</h2></div>
        <a href="/rooms">View all rooms</a>
    </div>
    <?php if ($rooms === []): ?>
        <p class="text-secondary">No rooms are currently published.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($rooms as $room): ?>
                <div class="col"><article class="card h-100 shadow-sm">
                    <img class="card-img-top" src="<?= $e($room['primaryImageUrl']) ?>" alt="<?= $e($room['name']) ?>" loading="lazy" decoding="async">
                    <div class="card-body d-flex flex-column">
                        <h3 class="h4"><a href="/rooms/<?= $e($room['slug']) ?>"><?= $e($room['name']) ?></a></h3>
                        <p class="flex-grow-1"><?= $e($room['description']) ?></p>
                        <p class="fw-semibold mb-0">NPR <?= $e($room['pricePerNight']) ?> per night</p>
                    </div>
                </article></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="py-4" aria-labelledby="home-activities-heading">
    <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
        <div><p class="text-uppercase fw-semibold text-secondary mb-1">Explore</p><h2 id="home-activities-heading" class="h1 mb-0">Activities</h2></div>
        <a href="/activities">View all activities</a>
    </div>
    <?php if ($activities === []): ?>
        <p class="text-secondary">No activities are currently published.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($activities as $activity): ?>
                <div class="col"><article class="card h-100 shadow-sm"><div class="card-body d-flex flex-column">
                    <h3 class="h4"><?= $e($activity['title']) ?></h3>
                    <p class="flex-grow-1"><?= nl2br($e($activity['description'])) ?></p>
                    <p class="fw-semibold mb-0">NPR <?= $e($activity['price']) ?></p>
                </div></article></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="py-4" aria-labelledby="home-gallery-heading">
    <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
        <div><p class="text-uppercase fw-semibold text-secondary mb-1">See</p><h2 id="home-gallery-heading" class="h1 mb-0">Gallery</h2></div>
        <a href="/gallery">View the gallery</a>
    </div>
    <?php if ($galleryItems === []): ?>
        <p class="text-secondary">No gallery images are currently published.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($galleryItems as $item): ?>
                <div class="col"><article class="card h-100 shadow-sm">
                    <img class="card-img-top" src="<?= $e($item['image_url']) ?>" alt="<?= $e($item['alt_text']) ?>" loading="lazy" decoding="async">
                    <div class="card-body"><h3 class="h5 mb-0"><?= $e($item['title']) ?></h3></div>
                </article></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($reviews !== []): ?>
<section class="py-4" aria-labelledby="home-reviews-heading">
    <div class="d-flex justify-content-between align-items-end gap-3 mb-3">
        <div>
            <p class="text-uppercase fw-semibold text-secondary mb-1">Verified stays</p>
            <h2 id="home-reviews-heading" class="h1 mb-0">Guest reviews</h2>
        </div>
        <a href="/reviews">Read all reviews</a>
    </div>
    <div class="row g-4">
        <?php foreach ($reviews as $review): ?>
            <div class="col-lg-4">
                <article class="card h-100 shadow-sm">
                    <div class="card-body">
                        <p aria-label="<?= $e((string) $review['rating']) ?> out of 5 stars">
                            <span aria-hidden="true"><?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?></span>
                        </p>
                        <?php if ($review['title'] !== null): ?>
                            <h3 class="h5"><?= $e($review['title']) ?></h3>
                        <?php endif; ?>
                        <p><?= nl2br($e($review['comment']), false) ?></p>
                        <p class="text-secondary mb-0"><?= $e($review['guest_name']) ?></p>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
