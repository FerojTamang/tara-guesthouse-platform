<article>
    <h1><?= $e($room['name']) ?></h1>
    <p><?= $e($room['roomTypeName']) ?></p>
    <img class="img-fluid mb-3" src="<?= $e($room['primaryImageUrl']) ?>" alt="<?= $e($room['name']) ?>">
    <p><?= nl2br($e($room['description'])) ?></p>
    <p>NPR <?= $e($room['pricePerNight']) ?> per night</p>
    <p><?= $e($room['capacityAdults']) ?> adults, <?= $e($room['capacityChildren']) ?> children</p>
    <?php if ($room['amenities'] !== []): ?><h2 class="h4">Amenities</h2><ul><?php foreach ($room['amenities'] as $amenity): ?><li><?= $e($amenity['name']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($room['images'] !== []): ?><div class="row g-3"><?php foreach ($room['images'] as $image): ?><div class="col-md-4"><img class="img-fluid" src="<?= $e($image['url']) ?>" alt="<?= $e($image['altText']) ?>"></div><?php endforeach; ?></div><?php endif; ?>
</article>
