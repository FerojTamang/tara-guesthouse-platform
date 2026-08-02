<?php

declare(strict_types=1);

$placeholderImage = '/assets/images/room-placeholder.svg';
$heroImageUrl = null;

foreach ($rooms as $candidateRoom) {
    if ($candidateRoom['primaryImageUrl'] !== $placeholderImage) {
        $heroImageUrl = $candidateRoom['primaryImageUrl'];
        break;
    }
}

$roomCount = count($rooms);
$leadRoom = $rooms[0] ?? null;
$remainingRooms = array_slice($rooms, 1);
?>
<div class="tg-rooms-page">
    <section class="tg-rooms-hero" aria-labelledby="rooms-heading">
        <div class="tg-rooms-hero__media" aria-hidden="true">
            <?php if ($heroImageUrl !== null): ?>
                <img src="<?= $e($heroImageUrl) ?>" alt="" decoding="async">
            <?php else: ?>
                <picture>
                    <source srcset="/assets/images/home/home-hero-sauraha-jungle-lodge.webp" type="image/webp">
                    <img src="/assets/images/home/home-hero-sauraha-jungle-lodge.png" alt="" decoding="async">
                </picture>
            <?php endif; ?>
        </div>
        <div class="tg-rooms-hero__overlay" aria-hidden="true"></div>
        <div class="tg-container tg-rooms-hero__content">
            <nav class="tg-breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="/">Home</a></li>
                    <li aria-current="page">Rooms</li>
                </ol>
            </nav>
            <p class="tg-eyebrow tg-eyebrow--light">Rooms &amp; Stays</p>
            <h1 id="rooms-heading">Rest Easy, Stay Close to Nature</h1>
            <p>Comfortable rooms, peaceful surroundings, and a welcoming place to return to after discovering Sauraha.</p>
        </div>
    </section>

    <div class="tg-container tg-rooms-search-wrap">
        <section class="tg-rooms-search" aria-labelledby="rooms-search-heading">
            <div class="tg-rooms-search__intro">
                <p class="tg-eyebrow">Plan your stay</p>
                <h2 id="rooms-search-heading">Check availability</h2>
            </div>
            <form class="tg-rooms-search__form" method="get" action="/availability">
                <div class="tg-form-field">
                    <label for="rooms-check-in">Check-in</label>
                    <input id="rooms-check-in" name="check_in" type="date" required>
                </div>
                <div class="tg-form-field">
                    <label for="rooms-check-out">Check-out</label>
                    <input id="rooms-check-out" name="check_out" type="date" required>
                </div>
                <div class="tg-form-field">
                    <label for="rooms-adults">Adults</label>
                    <input id="rooms-adults" name="adults" type="number" min="1" step="1" value="1" required>
                </div>
                <div class="tg-form-field">
                    <label for="rooms-children">Children</label>
                    <input id="rooms-children" name="children" type="number" min="0" step="1" value="0" required>
                </div>
                <button class="tg-button tg-button--mint" type="submit" data-ripple>
                    Check Availability
                    <?= $partial('components.public-icon', ['name' => 'arrow']) ?>
                </button>
            </form>
        </section>
    </div>

    <section class="tg-rooms-listing" aria-labelledby="rooms-listing-heading">
        <div class="tg-container">
            <header class="tg-rooms-listing__header">
                <div>
                    <p class="tg-eyebrow">Our accommodation</p>
                    <h2 id="rooms-listing-heading">Choose Your Room</h2>
                    <p>Find the room that best fits your stay and continue to the existing room details page for complete information.</p>
                </div>
                <p class="tg-rooms-listing__count"><?= $e((string) $roomCount) ?> <?= $roomCount === 1 ? 'room' : 'rooms' ?></p>
            </header>

            <?php if ($leadRoom === null): ?>
                <div class="tg-empty-state tg-rooms-empty">
                    <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => 'bed']) ?></span>
                    <h3>No rooms to display</h3>
                    <p>No rooms are currently available to display. Please check availability or contact Tara Guest House for assistance.</p>
                    <div class="tg-button-row tg-button-row--center">
                        <a class="tg-button tg-button--mint" href="/availability" data-ripple>Check Availability</a>
                        <a class="tg-button tg-button--outline" href="/contact" data-ripple>Contact Us</a>
                    </div>
                </div>
            <?php else: ?>
                <article class="tg-card tg-rooms-lead" data-room-slug="<?= $e($leadRoom['slug']) ?>">
                    <a class="tg-rooms-lead__media" href="/rooms/<?= $e($leadRoom['slug']) ?>" aria-label="View details for <?= $e($leadRoom['name']) ?>">
                        <img src="<?= $e($leadRoom['primaryImageUrl']) ?>" alt="<?= $e($leadRoom['name']) ?>" loading="lazy" decoding="async">
                    </a>
                    <div class="tg-rooms-lead__body">
                        <p class="tg-card__kicker"><?= $e($leadRoom['roomTypeName']) ?></p>
                        <h3><?= $e($leadRoom['name']) ?></h3>
                        <?php if ($leadRoom['description'] !== ''): ?><p class="tg-rooms-lead__description"><?= $e($leadRoom['description']) ?></p><?php endif; ?>
                        <div class="tg-room-facts" aria-label="Room capacity">
                            <span><?= $partial('components.public-icon', ['name' => 'users']) ?> <?= $e((string) $leadRoom['capacityAdults']) ?> adult<?= $leadRoom['capacityAdults'] === 1 ? '' : 's' ?></span>
                            <span><?= $e((string) $leadRoom['capacityChildren']) ?> child<?= $leadRoom['capacityChildren'] === 1 ? '' : 'ren' ?></span>
                        </div>
                        <?php if ($leadRoom['amenities'] !== []): ?>
                            <ul class="tg-room-amenities" aria-label="Room amenities">
                                <?php foreach ($leadRoom['amenities'] as $amenity): ?>
                                    <li><?= $partial('components.public-icon', ['name' => 'check']) ?> <?= $e($amenity['name']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="tg-rooms-lead__footer">
                            <p class="tg-price"><span>NPR</span> <?= $e($leadRoom['pricePerNight']) ?><small>per night</small></p>
                            <div class="tg-button-row">
                                <a class="tg-button tg-button--emerald" href="/rooms/<?= $e($leadRoom['slug']) ?>" data-ripple>View Room</a>
                                <a class="tg-button tg-button--outline" href="/availability" data-ripple>Check Availability</a>
                            </div>
                        </div>
                    </div>
                </article>

                <?php if ($remainingRooms !== []): ?>
                    <div class="tg-rooms-grid" aria-label="More rooms">
                        <?php foreach ($remainingRooms as $room): ?>
                            <article class="tg-card tg-room-card tg-room-card--listing" data-room-slug="<?= $e($room['slug']) ?>">
                                <a class="tg-card__media" href="/rooms/<?= $e($room['slug']) ?>" aria-label="View details for <?= $e($room['name']) ?>">
                                    <img src="<?= $e($room['primaryImageUrl']) ?>" alt="<?= $e($room['name']) ?>" loading="lazy" decoding="async">
                                </a>
                                <div class="tg-card__body">
                                    <p class="tg-card__kicker"><?= $e($room['roomTypeName']) ?></p>
                                    <h3><?= $e($room['name']) ?></h3>
                                    <?php if ($room['description'] !== ''): ?><p class="tg-card__description"><?= $e($room['description']) ?></p><?php endif; ?>
                                    <div class="tg-room-facts" aria-label="Room capacity">
                                        <span><?= $partial('components.public-icon', ['name' => 'users']) ?> <?= $e((string) $room['capacityAdults']) ?> adult<?= $room['capacityAdults'] === 1 ? '' : 's' ?></span>
                                        <span><?= $e((string) $room['capacityChildren']) ?> child<?= $room['capacityChildren'] === 1 ? '' : 'ren' ?></span>
                                    </div>
                                    <?php if ($room['amenities'] !== []): ?>
                                        <ul class="tg-room-amenities" aria-label="Room amenities">
                                            <?php foreach ($room['amenities'] as $amenity): ?><li><?= $partial('components.public-icon', ['name' => 'check']) ?> <?= $e($amenity['name']) ?></li><?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <div class="tg-card__footer">
                                        <p class="tg-price"><span>NPR</span> <?= $e($room['pricePerNight']) ?><small>per night</small></p>
                                        <a class="tg-button tg-button--outline tg-button--small" href="/rooms/<?= $e($room['slug']) ?>" data-ripple>View Room</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="tg-rooms-benefits" aria-label="Why book directly with Tara Guest House">
        <div class="tg-container tg-rooms-benefits__grid">
            <?php foreach ([
                ['bed', 'Comfortable Spaces', 'Rooms designed for a restful stay'],
                ['calendar', 'Direct Availability', 'Check dates through the official website'],
                ['check', 'Clear Room Details', 'Review capacity and room information before continuing'],
                ['heart', 'Warm Hospitality', 'A welcoming base while visiting Sauraha'],
            ] as [$icon, $heading, $copy]): ?>
                <article class="tg-rooms-benefit">
                    <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => $icon]) ?></span>
                    <div><h2><?= $e($heading) ?></h2><p><?= $e($copy) ?></p></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tg-rooms-cta" aria-labelledby="rooms-cta-heading">
        <div class="tg-container tg-rooms-cta__content">
            <p class="tg-eyebrow tg-eyebrow--light">Plan your stay</p>
            <h2 id="rooms-cta-heading">Found the Right Room?</h2>
            <p>Check your dates and continue through the official availability process.</p>
            <div class="tg-button-row tg-button-row--center">
                <a class="tg-button tg-button--mint" href="/availability" data-ripple>Check Availability</a>
                <a class="tg-button tg-button--light-outline" href="/contact" data-ripple>Contact Us</a>
            </div>
        </div>
    </section>
</div>
