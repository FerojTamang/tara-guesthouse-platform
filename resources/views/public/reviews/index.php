<section class="py-5" aria-labelledby="reviews-heading">
    <div class="row justify-content-center text-center mb-5">
        <div class="col-lg-8">
            <p class="text-uppercase text-secondary fw-semibold mb-2">Verified stays</p>
            <h1 id="reviews-heading" class="display-6">Guest reviews</h1>
            <p class="lead mb-0">
                These reviews were submitted through single-use invitations linked to completed stays
                and published only after Administrator approval.
            </p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" role="status">
            Thank you. Your review was submitted for moderation.
        </div>
    <?php endif; ?>

    <?php if ($reviews === []): ?>
        <div class="alert alert-secondary" role="status">
            No approved Guest reviews are available yet.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($reviews as $review): ?>
                <div class="col-lg-6">
                    <article class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <p class="mb-3" aria-label="<?= $e((string) $review['rating']) ?> out of 5 stars">
                                <span aria-hidden="true"><?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?></span>
                            </p>
                            <?php if ($review['title'] !== null): ?>
                                <h2 class="h5"><?= $e($review['title']) ?></h2>
                            <?php endif; ?>
                            <p class="mb-4"><?= nl2br($e($review['comment']), false) ?></p>
                            <footer class="text-secondary">
                                <span class="fw-semibold"><?= $e($review['guest_name']) ?></span>
                                <span aria-hidden="true"> · </span>
                                <time datetime="<?= $e($review['created_at']) ?>">
                                    Verified Guest
                                </time>
                            </footer>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
