<section aria-labelledby="availability-heading">
    <h1 id="availability-heading">Check room availability</h1>
    <p>Enter your stay dates and the number of guests to see rooms available for the complete stay.</p>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $e($error) ?></p>
    <?php endif; ?>

    <form method="get" action="/availability">
        <div>
            <label for="availability-check-in">Check-in date</label>
            <input
                id="availability-check-in"
                name="check_in"
                type="date"
                value="<?= $e($values['checkIn']) ?>"
                required
            >
        </div>

        <div>
            <label for="availability-check-out">Check-out date</label>
            <input
                id="availability-check-out"
                name="check_out"
                type="date"
                value="<?= $e($values['checkOut']) ?>"
                required
            >
        </div>

        <div>
            <label for="availability-adults">Adults</label>
            <input
                id="availability-adults"
                name="adults"
                type="number"
                min="1"
                step="1"
                value="<?= $e($values['adults']) ?>"
                required
            >
        </div>

        <div>
            <label for="availability-children">Children</label>
            <input
                id="availability-children"
                name="children"
                type="number"
                min="0"
                step="1"
                value="<?= $e($values['children']) ?>"
                required
            >
        </div>

        <button type="submit">Check availability</button>
    </form>

    <?php if ($submitted && $error === null): ?>
        <section aria-labelledby="availability-results-heading">
            <h2 id="availability-results-heading">Available rooms</h2>

            <?php if ($rooms === []): ?>
                <p>No rooms match the selected dates and occupancy.</p>
            <?php else: ?>
                <p><?= $e(count($rooms)) ?> room<?= count($rooms) === 1 ? '' : 's' ?> available.</p>

                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <?php foreach ($rooms as $room): ?>
                        <div class="col">
                            <article class="card h-100">
                                <div class="card-body">
                                    <h3 class="h4">
                                        <a href="/rooms/<?= $e($room['slug']) ?>">
                                            <?= $e($room['name']) ?>
                                        </a>
                                    </h3>
                                    <p>NPR <?= $e($room['price_per_night']) ?> per night</p>
                                    <p>This room is available for the selected stay.</p>
                                    <a href="/rooms/<?= $e($room['slug']) ?>">View room details</a>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
