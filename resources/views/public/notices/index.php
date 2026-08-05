<?php
$formatDate = static function (mixed $value, string $format = 'F j, Y'): ?string {
    if (!is_string($value) || $value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format($format);
    } catch (Throwable) {
        return null;
    }
};
?>
<section class="tg-publishing-index" aria-labelledby="notices-heading">
    <header class="tg-publishing-hero">
        <p class="tg-eyebrow">Guest information</p>
        <h1 id="notices-heading">Current notices</h1>
        <p>Important updates, events, and practical information for guests and visitors.</p>
    </header>

    <?php if ($items === []): ?>
        <div class="tg-empty-state tg-publishing-empty">
            <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => 'calendar']) ?></span>
            <h2>No active notices</h2>
            <p>There are no current guest notices at this time.</p>
        </div>
    <?php else: ?>
        <div class="tg-notice-list">
            <?php foreach ($items as $item): ?>
                <?php
                $style = is_string($item['notice_style'] ?? null) ? $item['notice_style'] : 'information';
                $publishedDate = $formatDate($item['published_at'] ?? null);
                $expiryDate = $formatDate($item['expires_at'] ?? null, 'F j, Y \a\t g:i A');
                ?>
                <article class="tg-notice-card tg-notice-card--<?= $e($style) ?>">
                    <div class="tg-notice-card__marker" aria-hidden="true"><?= $partial('components.public-icon', ['name' => $style === 'event' ? 'calendar' : 'leaf']) ?></div>
                    <div class="tg-notice-card__body">
                        <div class="tg-content-card__badges">
                            <span class="tg-content-badge"><?= $e(ucfirst($style)) ?></span>
                            <?php if (($item['is_pinned'] ?? false) === true): ?><span class="tg-content-badge tg-content-badge--soft">Pinned</span><?php endif; ?>
                        </div>
                        <h2><a href="<?= $e((string) $item['href']) ?>"><?= $e((string) $item['title']) ?></a></h2>
                        <p><?= $e((string) $item['summary']) ?></p>
                        <div class="tg-notice-card__meta">
                            <?php if ($publishedDate !== null): ?>
                                <span>Published <time datetime="<?= $e((string) $item['published_at']) ?>"><?= $e($publishedDate) ?></time></span>
                            <?php endif; ?>
                            <?php if ($expiryDate !== null): ?>
                                <span>Active until <time datetime="<?= $e((string) $item['expires_at']) ?>"><?= $e($expiryDate) ?></time></span>
                            <?php endif; ?>
                        </div>
                        <a class="tg-text-link" href="<?= $e((string) $item['href']) ?>">
                            Read full notice <?= $partial('components.public-icon', ['name' => 'arrow']) ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
