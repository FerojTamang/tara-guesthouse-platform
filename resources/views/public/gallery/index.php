<?php
$galleryCount = count($galleryItems);
?>
<section class="tg-gallery-hero" aria-labelledby="gallery-heading">
    <div class="tg-container tg-gallery-hero__inner">
        <nav class="tg-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="/">Home</a></li>
                <li aria-current="page">Gallery</li>
            </ol>
        </nav>
        <div class="tg-gallery-hero__content">
            <p class="tg-eyebrow tg-eyebrow--light">Tara Guest House</p>
            <h1 id="gallery-heading">Moments from Sauraha</h1>
            <p>Explore real views of the guest house, surrounding nature, and the atmosphere that makes every stay memorable.</p>
            <span class="tg-gallery-hero__count"><?= $e((string) $galleryCount) ?> published <?= $galleryCount === 1 ? 'moment' : 'moments' ?></span>
        </div>
    </div>
</section>

<section class="tg-gallery-section" aria-labelledby="gallery-collection-heading">
    <div class="tg-container">
        <div class="tg-section-heading tg-gallery-section__heading">
            <div>
                <p class="tg-eyebrow">Visual stories</p>
                <h2 id="gallery-collection-heading">Guest house, nature, and local life</h2>
                <p>Every photograph below is published through Tara Guest House’s managed gallery.</p>
            </div>
            <a class="tg-button tg-button--outline" href="/availability" data-ripple>Plan your stay</a>
        </div>

        <?php if ($galleryItems === []): ?>
            <div class="tg-gallery-empty" role="status">
                <span class="tg-gallery-empty__icon" aria-hidden="true"><?= $partial('components.public-icon', ['name' => 'heart']) ?></span>
                <div>
                    <h2>More moments are coming soon</h2>
                    <p>No gallery images are currently published. Please check again later.</p>
                </div>
                <a class="tg-button tg-button--emerald" href="/rooms" data-ripple>Explore Rooms</a>
            </div>
        <?php else: ?>
            <div class="tg-gallery-grid">
                <?php foreach ($galleryItems as $index => $item): ?>
                    <?php
                    $headingId = 'gallery-item-heading-' . (string) $index;
                    $captionId = 'gallery-item-caption-' . (string) $index;
                    $featuredClass = $index === 0 ? ' tg-gallery-card--featured' : '';
                    ?>
                    <article class="tg-gallery-card<?= $featuredClass ?>" aria-labelledby="<?= $e($headingId) ?>">
                        <figure>
                            <div class="tg-gallery-card__media">
                                <img
                                    src="<?= $e($item['image_url']) ?>"
                                    alt="<?= $e($item['alt_text']) ?>"
                                    loading="lazy"
                                    decoding="async"
                                    width="960"
                                    height="720"
                                    <?php if ($item['caption'] !== null): ?>aria-describedby="<?= $e($captionId) ?>"<?php endif; ?>
                                >
                                <div class="tg-gallery-card__overlay" aria-hidden="true"></div>
                            </div>
                            <figcaption class="tg-gallery-card__caption">
                                <p class="tg-eyebrow">Tara Guest House</p>
                                <h2 id="<?= $e($headingId) ?>"><?= $e($item['title']) ?></h2>
                                <?php if ($item['caption'] !== null): ?>
                                    <p id="<?= $e($captionId) ?>"><?= nl2br($e($item['caption'])) ?></p>
                                <?php endif; ?>
                            </figcaption>
                        </figure>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="tg-gallery-cta" aria-labelledby="gallery-cta-heading">
    <div class="tg-container tg-gallery-cta__inner">
        <div>
            <p class="tg-eyebrow">Create your own memories</p>
            <h2 id="gallery-cta-heading">See Sauraha from a calm and welcoming base</h2>
            <p>Explore our rooms or check availability for your preferred dates.</p>
        </div>
        <div class="tg-button-row">
            <a class="tg-button tg-button--emerald" href="/rooms" data-ripple>Explore Rooms</a>
            <a class="tg-button tg-button--outline" href="/availability" data-ripple>Check Availability</a>
        </div>
    </div>
</section>
