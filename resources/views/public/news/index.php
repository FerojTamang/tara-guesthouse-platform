<?php
$formatDate = static function (mixed $value): ?string {
    if (!is_string($value) || $value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('F j, Y');
    } catch (Throwable) {
        return null;
    }
};
?>
<section class="tg-publishing-index" aria-labelledby="news-heading">
    <header class="tg-publishing-hero">
        <p class="tg-eyebrow">From Tara Guest House</p>
        <h1 id="news-heading">News &amp; stories</h1>
        <p>Guest house updates, local stories, and useful information for planning a memorable stay in Sauraha.</p>
    </header>

    <?php if ($items === []): ?>
        <div class="tg-empty-state tg-publishing-empty">
            <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => 'leaf']) ?></span>
            <h2>No news has been published yet</h2>
            <p>Please check again soon for updates from Tara Guest House.</p>
        </div>
    <?php else: ?>
        <div class="tg-news-grid">
            <?php foreach ($items as $item): ?>
                <?php
                $image = is_array($item['featured_image'] ?? null) ? $item['featured_image'] : null;
                $publishedDate = $formatDate($item['published_at'] ?? null);
                ?>
                <article class="tg-card tg-news-card<?= ($item['is_pinned'] ?? false) === true ? ' tg-news-card--pinned' : '' ?>">
                    <a class="tg-card__media tg-news-card__media" href="<?= $e((string) $item['href']) ?>" aria-label="Read <?= $e((string) $item['title']) ?>">
                        <?php if ($image !== null): ?>
                            <img
                                src="<?= $e((string) ($image['url'] ?? '')) ?>"
                                alt="<?= $e((string) ($image['alt_text'] ?? '')) ?>"
                                width="<?= $e((int) ($image['width'] ?? 0)) ?>"
                                height="<?= $e((int) ($image['height'] ?? 0)) ?>"
                                loading="lazy"
                                decoding="async"
                            >
                        <?php else: ?>
                            <span class="tg-news-card__placeholder" aria-hidden="true"><?= $partial('components.public-icon', ['name' => 'leaf']) ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="tg-card__body">
                        <div class="tg-content-card__badges">
                            <?php if (($item['is_pinned'] ?? false) === true): ?><span class="tg-content-badge">Pinned</span><?php endif; ?>
                            <?php if (($item['is_featured'] ?? false) === true): ?><span class="tg-content-badge tg-content-badge--soft">Featured</span><?php endif; ?>
                        </div>
                        <?php if ($publishedDate !== null): ?>
                            <p class="tg-card__kicker"><time datetime="<?= $e((string) $item['published_at']) ?>"><?= $e($publishedDate) ?></time></p>
                        <?php endif; ?>
                        <h2><a href="<?= $e((string) $item['href']) ?>"><?= $e((string) $item['title']) ?></a></h2>
                        <p class="tg-card__description"><?= $e((string) $item['summary']) ?></p>
                        <a class="tg-text-link" href="<?= $e((string) $item['href']) ?>">
                            Read story <?= $partial('components.public-icon', ['name' => 'arrow']) ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
