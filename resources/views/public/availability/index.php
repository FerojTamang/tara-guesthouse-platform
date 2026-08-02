<?php
$genericQuery = http_build_query([
    'check_in' => $values['checkIn'],
    'check_out' => $values['checkOut'],
    'adults' => $values['adults'],
    'children' => $values['children'],
], '', '&', PHP_QUERY_RFC3986);
$selectedAvailableRoom = $selectedRoom !== null && $rooms !== [] ? $rooms[0] : null;
?>
<section class="tg-journey-hero" aria-labelledby="availability-heading"><div class="tg-container">
    <nav class="tg-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><span aria-current="page">Availability</span></nav>
    <p class="tg-eyebrow">Plan your stay</p><h1 id="availability-heading">Find your room in Sauraha</h1><p>Choose your dates and guests. Availability is calculated for the complete stay.</p>
</div></section>

<div class="tg-container tg-journey-content">
    <?php if ($error !== null): ?><div class="tg-alert tg-alert--error" role="alert" tabindex="-1"><strong>Please review your search.</strong><p><?= $e($error) ?></p></div><?php endif; ?>
    <form method="get" action="/availability" class="tg-search-card tg-journey-form" aria-label="Availability search">
        <?php if ($values['room'] !== ''): ?><input type="hidden" name="room" value="<?= $e($values['room']) ?>"><?php endif; ?>
        <div class="tg-search-card__grid">
            <div><label for="availability-check-in">Check-in date</label><input id="availability-check-in" name="check_in" type="date" value="<?= $e($values['checkIn']) ?>" required></div>
            <div><label for="availability-check-out">Check-out date</label><input id="availability-check-out" name="check_out" type="date" value="<?= $e($values['checkOut']) ?>" required></div>
            <div><label for="availability-adults">Adults</label><input id="availability-adults" name="adults" type="number" min="1" step="1" value="<?= $e($values['adults']) ?>" required></div>
            <div><label for="availability-children">Children</label><input id="availability-children" name="children" type="number" min="0" step="1" value="<?= $e($values['children']) ?>" required></div>
            <button class="tg-button tg-button--emerald" type="submit" data-ripple>Check availability</button>
        </div>
    </form>

    <?php if ($submitted && $error === null): ?>
        <section class="tg-results" aria-labelledby="availability-results-heading">
            <div class="tg-section-heading"><p class="tg-eyebrow">Your stay</p><h2 id="availability-results-heading"><?= $selectedRoom !== null ? 'Your selected room' : ($rooms === [] ? 'No room available for this search' : 'Available rooms') ?></h2><?php if ($selectedRoom === null && $rooms !== []): ?><p><?= $e((string) count($rooms)) ?> room<?= count($rooms) === 1 ? '' : 's' ?> available.</p><?php endif; ?><p><?= $e($values['checkIn']) ?> to <?= $e($values['checkOut']) ?> &middot; <?= $e($values['adults']) ?> adult<?= $values['adults'] === '1' ? '' : 's' ?> &middot; <?= $e($values['children']) ?> child<?= $values['children'] === '1' ? '' : 'ren' ?></p></div>

            <?php if ($selectedRoom !== null): ?>
                <?php
                $resultRoom = $selectedAvailableRoom ?? $selectedRoom;
                $bookingQuery = $selectedAvailableRoom === null ? '' : http_build_query([
                    'room_id' => $selectedAvailableRoom['room_id'],
                    'check_in' => $values['checkIn'],
                    'check_out' => $values['checkOut'],
                    'adults' => $values['adults'],
                    'children' => $values['children'],
                ], '', '&', PHP_QUERY_RFC3986);
                $roomQuery = $genericQuery . '&availability=' . ($selectedAvailableRoom === null ? 'unavailable' : 'available');
                ?>
                <article class="tg-selected-result<?= $selectedAvailableRoom === null ? ' is-unavailable' : ' is-available' ?>">
                    <img src="<?= $e($resultRoom['primary_image_url']) ?>" alt="<?= $e($resultRoom['name']) ?>" width="900" height="600" loading="eager">
                    <div class="tg-selected-result__content">
                        <p class="tg-eyebrow">Selected room &middot; <?= $selectedAvailableRoom === null ? 'Not available for these dates' : 'Available for your selected dates' ?></p>
                        <h3><?= $e($resultRoom['name']) ?></h3><p class="tg-selected-result__type"><?= $e($resultRoom['room_type_name']) ?></p>
                        <?php if ($selectedAvailableRoom === null): ?><p><?= $e($resultRoom['name']) ?> is unavailable for these dates. Try different dates or browse other rooms for the same stay.</p><?php endif; ?>
                        <div class="tg-room-facts"><span><?= $e($resultRoom['capacity_adults']) ?> adults</span><span><?= $e($resultRoom['capacity_children']) ?> children</span><span><?= $e($resultRoom['nights']) ?> night<?= $resultRoom['nights'] === 1 ? '' : 's' ?></span></div>
                        <p class="tg-availability-card__price">NPR <?= $e($resultRoom['price_per_night']) ?> per night</p>
                        <?php if ($resultRoom['amenities'] !== []): ?><ul class="tg-result-amenities"><?php foreach ($resultRoom['amenities'] as $amenity): ?><li><?= $e($amenity['name']) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <div class="tg-action-row">
                            <?php if ($selectedAvailableRoom !== null): ?><a class="tg-button tg-button--emerald" href="/booking?<?= $e($bookingQuery) ?>" data-ripple>Continue Booking</a><?php else: ?><a class="tg-button tg-button--emerald" href="#availability-check-in">Change Dates</a><?php endif; ?>
                            <a class="tg-button tg-button--outline" href="/rooms/<?= $e($resultRoom['slug']) ?>?<?= $e($roomQuery) ?>">View Room</a>
                            <?php if ($selectedAvailableRoom === null): ?><a class="tg-text-link" href="/availability?<?= $e($genericQuery) ?>">View Other Available Rooms</a><?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php elseif ($rooms === []): ?>
                <div class="tg-empty-state"><h3>No rooms match the selected dates and occupancy.</h3><p>Try another stay or contact us for help planning your visit.</p><div class="tg-action-row"><a class="tg-button tg-button--emerald" href="/rooms">View Rooms</a><a class="tg-button tg-button--outline" href="/contact">Contact</a></div></div>
            <?php else: ?>
                <div class="tg-availability-grid">
                    <?php foreach ($rooms as $room): ?>
                        <?php
                        $bookingQuery = http_build_query(['room_id' => $room['room_id'], 'check_in' => $values['checkIn'], 'check_out' => $values['checkOut'], 'adults' => $values['adults'], 'children' => $values['children']], '', '&', PHP_QUERY_RFC3986);
                        $roomQuery = $genericQuery . '&availability=available';
                        ?>
                        <article class="tg-availability-card">
                            <img src="<?= $e($room['primary_image_url']) ?>" alt="<?= $e($room['name']) ?>" width="720" height="480" loading="lazy">
                            <div class="tg-availability-card__content"><p class="tg-eyebrow">Available for your selected dates</p><h3><?= $e($room['name']) ?></h3><p><?= $e($room['room_type_name']) ?> &middot; <?= $e($room['capacity_adults']) ?> adults &middot; <?= $e($room['capacity_children']) ?> children</p><p><?= $e($room['nights']) ?> night<?= $room['nights'] === 1 ? '' : 's' ?>, <?= $e($values['checkIn']) ?> to <?= $e($values['checkOut']) ?></p><p class="tg-availability-card__price">NPR <?= $e($room['price_per_night']) ?> per night</p><?php if ($room['amenities'] !== []): ?><ul class="tg-result-amenities"><?php foreach ($room['amenities'] as $amenity): ?><li><?= $e($amenity['name']) ?></li><?php endforeach; ?></ul><?php endif; ?><div class="tg-action-row"><a class="tg-button tg-button--emerald" href="/booking?<?= $e($bookingQuery) ?>" data-ripple>Continue Booking</a><a class="tg-button tg-button--outline" href="/rooms/<?= $e($room['slug']) ?>?<?= $e($roomQuery) ?>">View Room</a></div></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
