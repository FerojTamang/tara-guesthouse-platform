<section aria-labelledby="gallery-heading">
    <div class="row align-items-end g-3 mb-4">
        <div class="col-lg-8">
            <p class="text-uppercase fw-semibold text-secondary mb-2">Tara Guest House</p>
            <h1 id="gallery-heading" class="display-6 mb-2">Gallery</h1>
            <p class="lead mb-0">See the guest house, surrounding spaces, and local atmosphere.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a class="btn btn-outline-primary" href="/availability">Check room availability</a>
        </div>
    </div>

    <?php if ($galleryItems === []): ?>
        <div class="alert alert-info" role="status">
            No gallery images are currently published. Please check again later.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($galleryItems as $index => $item): ?>
                <?php
                $headingId = 'gallery-item-heading-' . (string) $index;
                $captionId = 'gallery-item-caption-' . (string) $index;
                ?>
                <div class="col">
                    <article class="card h-100 shadow-sm" aria-labelledby="<?= $e($headingId) ?>">
                        <img
                            class="card-img-top img-fluid"
                            src="<?= $e($item['image_url']) ?>"
                            alt="<?= $e($item['alt_text']) ?>"
                            loading="lazy"
                            decoding="async"
                            <?php if ($item['caption'] !== null): ?>aria-describedby="<?= $e($captionId) ?>"<?php endif; ?>
                        >
                        <div class="card-body">
                            <h2 id="<?= $e($headingId) ?>" class="h5 card-title">
                                <?= $e($item['title']) ?>
                            </h2>
                            <?php if ($item['caption'] !== null): ?>
                                <p id="<?= $e($captionId) ?>" class="card-text mb-0">
                                    <?= nl2br($e($item['caption'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
