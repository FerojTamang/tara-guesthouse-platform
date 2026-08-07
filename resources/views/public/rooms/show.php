<?php
$galleryImages = array_map(
    static fn (array $image): array => ['url' => $image['url'], 'altText' => $image['altText']],
    $room['images'],
);
$primaryAlt = $room['name'];

foreach ($galleryImages as $image) {
    if ($image['url'] === $room['primaryImageUrl']) {
        $primaryAlt = $image['altText'];
        break;
    }
}

$primaryDisplay = ['url' => $room['primaryImageUrl'], 'altText' => $primaryAlt];
$supportingImages = array_values(array_filter(
    $galleryImages,
    static fn (array $image): bool => $image['url'] !== $room['primaryImageUrl'],
));
$displayImages = array_merge([$primaryDisplay], array_slice($supportingImages, 0, 4));
$hasViewer = count($galleryImages) > 1;
$displayImageCount = count($displayImages);
?>
<article class="tg-room-detail" data-room-detail data-room-slug="<?= $e($room['slug']) ?>">
    <section class="tg-room-detail__hero" aria-labelledby="room-detail-title">
        <div class="tg-container">
            <nav class="tg-breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a><span aria-hidden="true">/</span><a href="/rooms">Rooms</a><span aria-hidden="true">/</span><span aria-current="page"><?= $e($room['name']) ?></span>
            </nav>
            <div class="tg-room-detail__heading">
                <div class="tg-room-detail__identity">
                    <p class="tg-eyebrow"><?= $e($room['roomTypeName']) ?></p>
                    <h1 id="room-detail-title"><?= $e($room['name']) ?></h1>
                    <div class="tg-room-detail__intro-facts" aria-label="Room capacity">
                        <span><?= $e($room['capacityAdults']) ?> adult<?= $room['capacityAdults'] === 1 ? '' : 's' ?></span>
                        <span><?= $e($room['capacityChildren']) ?> child<?= $room['capacityChildren'] === 1 ? '' : 'ren' ?></span>
                        <span><?= $e((string) count($galleryImages)) ?> photo<?= count($galleryImages) === 1 ? '' : 's' ?></span>
                    </div>
                </div>
                <p class="tg-room-detail__price"><span>From</span><strong>NPR <?= $e($room['pricePerNight']) ?></strong><span>per night</span></p>
            </div>
            <div class="tg-room-gallery tg-room-gallery--count-<?= $e((string) $displayImageCount) ?><?= $displayImageCount < 2 ? ' tg-room-gallery--single' : '' ?>" aria-label="Photos of <?= $e($room['name']) ?>">
                <?php foreach ($displayImages as $index => $image): ?>
                    <?php $viewerIndex = array_search($image['url'], array_column($galleryImages, 'url'), true); ?>
                    <?php if ($hasViewer && $viewerIndex !== false): ?>
                        <button class="tg-room-gallery__item tg-room-gallery__item--<?= $e((string) ($index + 1)) ?>" type="button" data-photo-open="<?= $e((string) $viewerIndex) ?>" aria-label="Open photo <?= $e((string) ($viewerIndex + 1)) ?> of <?= $e((string) count($galleryImages)) ?>">
                            <img src="<?= $e($image['url']) ?>" alt="<?= $e($image['altText']) ?>" width="1200" height="800" <?= $index === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"' ?>>
                        </button>
                    <?php else: ?>
                        <figure class="tg-room-gallery__item tg-room-gallery__item--<?= $e((string) ($index + 1)) ?>"><img src="<?= $e($image['url']) ?>" alt="<?= $e($image['altText']) ?>" width="1200" height="800" loading="eager" fetchpriority="high"></figure>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="tg-room-gallery__count" aria-label="<?= $e((string) count($galleryImages)) ?> room photos">
                    <?= count($galleryImages) === 0 ? 'Photo unavailable' : $e((string) count($galleryImages)) . ' photo' . (count($galleryImages) === 1 ? '' : 's') ?>
                </div>
                <?php if ($hasViewer): ?><button class="tg-room-gallery__all" type="button" data-photo-open="0">View all photos</button><?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($hasViewer): ?>
        <div class="tg-photo-viewer" data-photo-viewer hidden role="dialog" aria-modal="true" aria-labelledby="photo-viewer-title">
            <div class="tg-photo-viewer__backdrop" data-photo-close></div>
            <div class="tg-photo-viewer__panel">
                <div class="tg-photo-viewer__header"><h2 id="photo-viewer-title"><?= $e($room['name']) ?> photos</h2><button type="button" data-photo-close aria-label="Close photo viewer">&times;</button></div>
                <div class="tg-photo-viewer__stage">
                    <button type="button" data-photo-previous aria-label="Previous photo">&#8592;</button>
                    <img data-photo-current src="<?= $e($galleryImages[0]['url']) ?>" alt="<?= $e($galleryImages[0]['altText']) ?>" width="1400" height="933">
                    <button type="button" data-photo-next aria-label="Next photo">&#8594;</button>
                </div>
                <p class="tg-photo-viewer__counter" aria-live="polite" data-photo-counter>1 of <?= $e((string) count($galleryImages)) ?></p>
                <div class="tg-photo-viewer__thumbnails" aria-label="Choose a photo">
                    <?php foreach ($galleryImages as $index => $image): ?><button type="button" data-photo-select="<?= $e((string) $index) ?>" data-photo-src="<?= $e($image['url']) ?>" data-photo-alt="<?= $e($image['altText']) ?>" aria-label="Show photo <?= $e((string) ($index + 1)) ?>"><img src="<?= $e($image['url']) ?>" alt="" width="160" height="110" loading="lazy"></button><?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="tg-container tg-room-detail__layout">
        <div class="tg-room-detail__content">
            <section class="tg-room-overview" aria-labelledby="room-overview-title">
                <p class="tg-eyebrow">Your stay</p>
                <h2 id="room-overview-title">A comfortable base in Sauraha</h2>
                <p class="tg-room-capacity-summary">
                    <?= $partial('components.public-icon', ['name' => 'users']) ?>
                    Sleeps up to <?= $e($room['capacityAdults']) ?> adult<?= $room['capacityAdults'] === 1 ? '' : 's' ?>
                    <?php if ($room['capacityChildren'] > 0): ?> and <?= $e($room['capacityChildren']) ?> child<?= $room['capacityChildren'] === 1 ? '' : 'ren' ?><?php endif; ?>
                </p>
                <p class="tg-room-description"><?= nl2br($e($room['description'])) ?></p>
            </section>
            <section class="tg-room-amenities-section" aria-labelledby="room-amenities-title">
                <p class="tg-eyebrow">Included</p>
                <h2 id="room-amenities-title">Room amenities</h2>
                <?php if ($room['amenities'] === []): ?>
                    <p class="tg-room-amenities-empty">Detailed amenity information is available on request.</p>
                <?php else: ?>
                    <ul class="tg-amenity-list">
                        <?php foreach ($room['amenities'] as $amenity): ?>
                            <li><?= $partial('components.public-icon', ['name' => 'check']) ?><span><?= $e($amenity['name']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <section class="tg-room-info" aria-labelledby="room-info-title">
                <p class="tg-eyebrow">Good to know</p>
                <h2 id="room-info-title">A request is not yet a confirmed stay</h2>
                <p>Availability is checked for your complete stay. A booking remains pending until the approved payment is submitted and verified.</p>
            </section>
        </div>

        <aside class="tg-reservation-card" aria-labelledby="room-availability-title" data-availability-card>
            <p class="tg-eyebrow">Plan your stay</p><h2 id="room-availability-title">Check this room</h2><p class="tg-reservation-card__rate"><strong>NPR <?= $e($room['pricePerNight']) ?></strong> per night</p>
            <p class="tg-availability-state<?= $availabilityResult === 'available' ? ' is-available' : ($availabilityResult === 'unavailable' ? ' is-unavailable' : '') ?>" data-availability-state aria-live="polite"><?= $availabilityResult === 'available' ? 'Available for your selected stay.' : ($availabilityResult === 'unavailable' ? 'Not available for these dates.' : 'Select your dates to check this room.') ?></p>
            <div class="tg-inline-calendar" data-availability-calendar role="region" aria-labelledby="room-availability-calendar-heading">
                <div class="tg-inline-calendar__toolbar"><button type="button" data-calendar-previous aria-label="Previous month">&#8592;</button><strong id="room-availability-calendar-heading" data-calendar-heading>Availability calendar</strong><button type="button" data-calendar-next aria-label="Next month">&#8594;</button></div>
                <div class="tg-inline-calendar__months" data-calendar-months><p>Use the date fields below to choose your stay. Calendar details load when JavaScript is available.</p></div>
                <div class="tg-inline-calendar__legend"><span><i class="is-available"></i>Available</span><span><i class="is-unavailable"></i>Unavailable</span><span><i class="is-selected"></i>Selected</span></div>
                <p class="tg-inline-calendar__message" data-calendar-message aria-live="polite"></p>
            </div>
            <form method="get" action="/availability" class="tg-journey-form" data-room-availability-form>
                <input type="hidden" name="room" value="<?= $e($room['slug']) ?>">
                <div class="tg-form-grid">
                    <div><label for="room-check-in">Check-in</label><input id="room-check-in" name="check_in" type="date" value="<?= $e($availabilityValues['checkIn']) ?>" required></div>
                    <div><label for="room-check-out">Check-out</label><input id="room-check-out" name="check_out" type="date" value="<?= $e($availabilityValues['checkOut']) ?>" required></div>
                    <div><label for="room-adults">Adults</label><input id="room-adults" name="adults" type="number" min="1" step="1" value="<?= $e($availabilityValues['adults']) ?>" required></div>
                    <div><label for="room-children">Children</label><input id="room-children" name="children" type="number" min="0" step="1" value="<?= $e($availabilityValues['children']) ?>" required></div>
                </div>
                <button class="tg-button tg-button--emerald tg-button--full" type="submit" data-ripple>Check This Room’s Availability</button>
            </form>
            <p class="tg-form-note">The calendar is advisory. Availability and the final total are rechecked securely when you submit a booking request.</p>
        </aside>
    </div>
    <section class="tg-room-final-cta"><div class="tg-container"><p class="tg-eyebrow">Ready when you are</p><h2>Choose your dates for <?= $e($room['name']) ?></h2><a class="tg-button tg-button--mint" href="#room-availability-title" data-ripple>Check availability</a></div></section>
</article>
