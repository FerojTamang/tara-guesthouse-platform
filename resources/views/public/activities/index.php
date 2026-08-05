<?php
$activityCount = count($activities);
?>
<section class="tg-activities-hero" aria-labelledby="activities-heading">
    <div class="tg-container tg-activities-hero__inner">
        <nav class="tg-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="/">Home</a></li>
                <li aria-current="page">Activities</li>
            </ol>
        </nav>
        <div class="tg-activities-hero__content">
            <p class="tg-eyebrow tg-eyebrow--light">Explore Sauraha</p>
            <h1 id="activities-heading">Experiences shaped by nature and local life</h1>
            <p>Discover wildlife, river journeys, village culture, and peaceful outdoor moments around Tara Guest House.</p>
            <div class="tg-activities-hero__meta" aria-label="Activity catalogue summary">
                <span><?= $e((string) $activityCount) ?> published <?= $activityCount === 1 ? 'experience' : 'experiences' ?></span>
                <span>Local guidance available</span>
            </div>
        </div>
    </div>
</section>

<section class="tg-activities-section" aria-labelledby="activity-list-heading">
    <div class="tg-container">
        <div class="tg-section-heading">
            <div>
                <p class="tg-eyebrow">Explore experiences</p>
                <h2 id="activity-list-heading">Make more of your stay</h2>
                <p class="tg-activities-section__intro">Choose from real activities published by Tara Guest House. Contact us for current timing and arrangements.</p>
            </div>
            <a class="tg-button tg-button--outline" href="/contact" data-ripple>Ask our local team</a>
        </div>

        <?php if ($activities === []): ?>
            <div class="tg-activities-empty" role="status">
                <span class="tg-activities-empty__icon" aria-hidden="true"><?= $partial('components.public-icon', ['name' => 'compass']) ?></span>
                <div>
                    <h2>New experiences are being prepared</h2>
                    <p>No activities are currently published. Please check again later or contact us for local recommendations.</p>
                </div>
                <a class="tg-button tg-button--emerald" href="/contact" data-ripple>Contact Tara Guest House</a>
            </div>
        <?php else: ?>
            <div class="tg-activities-grid">
                <?php foreach ($activities as $index => $activity): ?>
                    <?php
                    $headingId = 'activity-heading-' . (string) $index;
                    $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                    ?>
                    <article class="tg-activity-card" aria-labelledby="<?= $e($headingId) ?>">
                        <div class="tg-activity-card__media">
                            <?php if ($activity['image_url'] !== null): ?>
                                <img
                                    src="<?= $e($activity['image_url']) ?>"
                                    alt="<?= $e($activity['title']) ?>"
                                    loading="lazy"
                                    decoding="async"
                                    width="720"
                                    height="480"
                                >
                            <?php else: ?>
                                <div class="tg-activity-card__placeholder" aria-hidden="true">
                                    <?= $partial('components.public-icon', ['name' => 'compass']) ?>
                                </div>
                            <?php endif; ?>
                            <span class="tg-activity-card__number" aria-hidden="true"><?= $e($number) ?></span>
                        </div>
                        <div class="tg-activity-card__body">
                            <p class="tg-eyebrow">Local experience</p>
                            <h2 id="<?= $e($headingId) ?>"><?= $e($activity['title']) ?></h2>
                            <p class="tg-activity-card__summary"><?= nl2br($e($activity['summary'])) ?></p>

                            <?php if ($activity['duration'] !== null || $activity['location'] !== null): ?>
                                <dl class="tg-activity-card__details">
                                    <?php if ($activity['duration'] !== null): ?>
                                        <div>
                                            <dt><?= $partial('components.public-icon', ['name' => 'calendar']) ?><span>Duration</span></dt>
                                            <dd><?= $e($activity['duration']) ?></dd>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($activity['location'] !== null): ?>
                                        <div>
                                            <dt><?= $partial('components.public-icon', ['name' => 'pin']) ?><span>Location</span></dt>
                                            <dd><?= $e($activity['location']) ?></dd>
                                        </div>
                                    <?php endif; ?>
                                </dl>
                            <?php endif; ?>

                            <div class="tg-activity-card__footer">
                                <div class="tg-activity-card__price">
                                    <?php if ($activity['price'] !== null): ?>
                                        <span>From</span>
                                        <strong>NPR <?= $e($activity['price']) ?></strong>
                                        <?php if ($activity['price_unit'] !== null): ?>
                                            <small><?= $e($activity['price_unit']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span>Pricing</span>
                                        <strong>Ask us</strong>
                                    <?php endif; ?>
                                </div>
                                <a class="tg-button tg-button--emerald tg-button--small" href="/contact" data-ripple aria-label="Ask about <?= $e($activity['title']) ?>">
                                    Ask about this activity
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="tg-activities-cta" aria-labelledby="activities-cta-heading">
    <div class="tg-container tg-activities-cta__inner">
        <div class="tg-activities-cta__icon" aria-hidden="true"><?= $partial('components.public-icon', ['name' => 'leaf']) ?></div>
        <div>
            <p class="tg-eyebrow">Plan with confidence</p>
            <h2 id="activities-cta-heading">Pair your stay with a memorable Sauraha experience</h2>
            <p>Check room availability first, then contact our team to arrange activities around your dates.</p>
        </div>
        <div class="tg-button-row">
            <a class="tg-button tg-button--emerald" href="/availability" data-ripple>Check Availability</a>
            <a class="tg-button tg-button--outline" href="/contact" data-ripple>Contact Us</a>
        </div>
    </div>
</section>
