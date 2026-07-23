<section>
    <h1 class="mb-4">Rooms</h1>
    <?php if ($rooms === []): ?>
        <p>No rooms are currently available to view.</p>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($rooms as $room): ?>
                <div class="col"><article class="card h-100">
                    <img class="card-img-top" src="<?= $e($room['primaryImageUrl']) ?>" alt="<?= $e($room['name']) ?>">
                    <div class="card-body"><h2 class="h4"><a href="/rooms/<?= $e($room['slug']) ?>"><?= $e($room['name']) ?></a></h2>
                    <p><?= $e($room['roomTypeName']) ?></p><p><?= $e($room['description']) ?></p>
                    <p>NPR <?= $e($room['pricePerNight']) ?> per night</p><p><?= $e($room['capacityAdults']) ?> adults, <?= $e($room['capacityChildren']) ?> children</p>
                    <?php if ($room['amenities'] !== []): ?><ul><?php foreach ($room['amenities'] as $amenity): ?><li><?= $e($amenity['name']) ?></li><?php endforeach; ?></ul><?php endif; ?>
                    </div></article></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
