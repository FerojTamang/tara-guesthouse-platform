<?php
$document = json_decode($page['content'], true);
$isStructured = is_array($document) && ($document['format'] ?? null) === 'tara-blocks-v1' && is_array($document['blocks'] ?? null);
$blocks = $isStructured ? $document['blocks'] : [];
$contentType = is_string($page['content_type'] ?? null) ? $page['content_type'] : 'page';
$contentTypeLabel = match ($contentType) {
    'news' => 'News',
    'notice' => 'Notice',
    'policy' => 'Policy',
    default => 'Guest information',
};
$summary = is_string($page['summary'] ?? null) && trim($page['summary']) !== ''
    ? $page['summary']
    : ($isStructured && is_string($document['summary'] ?? null) ? trim($document['summary']) : '');
$featuredImage = is_array($page['featured_image'] ?? null) ? $page['featured_image'] : null;
$noticeStyle = is_string($page['notice_style'] ?? null) ? $page['notice_style'] : 'information';
$indexHref = is_string($contentIndexHref ?? null) ? $contentIndexHref : null;
$indexLabel = is_string($contentIndexLabel ?? null) ? $contentIndexLabel : null;
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
$publishedDate = $formatDate($page['published_at'] ?? null);
$expiryDate = $formatDate($page['expires_at'] ?? null, 'F j, Y \a\t g:i A');

$resolveContentImage = static function (array $block): ?array {
    $url = $block['url'] ?? null;
    if (!is_string($url) || $url === '') {
        return null;
    }

    $managed = array_key_exists('media_id', $block);
    if ($managed) {
        $mediaId = $block['media_id'];
        $width = $block['width'] ?? null;
        $height = $block['height'] ?? null;

        if (
            !is_int($mediaId)
            || $mediaId <= 0
            || !is_int($width)
            || $width <= 0
            || !is_int($height)
            || $height <= 0
            || preg_match('/^\/uploads\/content\/[a-f0-9]{64}\.(?:jpg|png|webp)$/D', $url) !== 1
        ) {
            return null;
        }

        return ['url' => $url, 'width' => $width, 'height' => $height];
    }

    // Preserve Phase A content created before the managed media library existed.
    if (filter_var($url, FILTER_VALIDATE_URL) === false && !str_starts_with($url, '/')) {
        return null;
    }

    $width = is_int($block['width'] ?? null) && $block['width'] > 0 ? $block['width'] : null;
    $height = is_int($block['height'] ?? null) && $block['height'] > 0 ? $block['height'] : null;

    return ['url' => $url, 'width' => $width, 'height' => $height];
};
?>
<article class="tg-content-detail mx-auto" aria-labelledby="cms-page-heading">
    <?php if ($indexHref !== null && $indexLabel !== null): ?>
        <p class="tg-content-detail__back">
            <a class="tg-text-link" href="<?= $e($indexHref) ?>">
                <?= $partial('components.public-icon', ['name' => 'arrow']) ?>
                <?= $e($indexLabel) ?>
            </a>
        </p>
    <?php endif; ?>

    <header class="tg-content-detail__header text-center mx-auto">
        <p class="tg-eyebrow mb-2"><?= $e($contentTypeLabel) ?></p>
        <h1 id="cms-page-heading" class="display-5 mb-3"><?= $e($page['title']) ?></h1>
        <?php if ($publishedDate !== null && in_array($contentType, ['news', 'notice'], true)): ?>
            <p class="tg-content-detail__date mb-3">
                Published <time datetime="<?= $e((string) $page['published_at']) ?>"><?= $e($publishedDate) ?></time>
            </p>
        <?php endif; ?>
        <?php if ($summary !== '' && $contentType !== 'notice'): ?>
            <p class="lead text-secondary mb-0"><?= $e($summary) ?></p>
        <?php endif; ?>
    </header>

    <?php if ($featuredImage !== null): ?>
        <figure class="tg-content-detail__featured">
            <img
                src="<?= $e((string) ($featuredImage['url'] ?? '')) ?>"
                alt="<?= $e((string) ($featuredImage['alt_text'] ?? '')) ?>"
                width="<?= $e((int) ($featuredImage['width'] ?? 0)) ?>"
                height="<?= $e((int) ($featuredImage['height'] ?? 0)) ?>"
                decoding="async"
            >
        </figure>
    <?php endif; ?>

    <?php if ($contentType === 'notice'): ?>
        <div class="tg-notice-banner tg-notice-banner--<?= $e($noticeStyle) ?>" role="note">
            <div>
                <p class="tg-notice-banner__label mb-2"><?= $e(ucfirst($noticeStyle)) ?> notice</p>
                <p class="mb-0"><?= $e($summary !== '' ? $summary : 'Guest notice') ?></p>
            </div>
            <?php if ($expiryDate !== null): ?>
                <p class="tg-notice-banner__expiry mb-0">
                    Active until <time datetime="<?= $e((string) $page['expires_at']) ?>"><?= $e($expiryDate) ?></time>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$isStructured): ?>
        <div class="tg-content-detail__body fs-5 lh-lg mx-auto"><?= nl2br($e($page['content'])) ?></div>
    <?php else: ?>
        <div class="cms-rendered-content tg-content-detail__body">
            <?php foreach ($blocks as $block): ?>
                <?php if (!is_array($block) || !is_string($block['type'] ?? null)) continue; ?>
                <?php $type = $block['type']; ?>
                <?php if ($type === 'heading'): ?>
                    <?php $level = in_array((int) ($block['level'] ?? 2), [2,3,4], true) ? (int) $block['level'] : 2; ?>
                    <<?= 'h'.$level ?>><?= $e((string) ($block['text'] ?? '')) ?></<?= 'h'.$level ?>>
                <?php elseif ($type === 'paragraph'): ?>
                    <div class="fs-5 lh-lg"><?= nl2br($e((string) ($block['text'] ?? ''))) ?></div>
                <?php elseif ($type === 'image'): ?>
                    <?php $image = $resolveContentImage($block); if ($image === null) continue; ?>
                    <figure class="mb-0">
                        <img
                            src="<?= $e($image['url']) ?>"
                            alt="<?= $e((string) ($block['alt'] ?? '')) ?>"
                            <?php if ($image['width'] !== null && $image['height'] !== null): ?>width="<?= $e($image['width']) ?>" height="<?= $e($image['height']) ?>"<?php endif; ?>
                            loading="lazy"
                            decoding="async"
                        >
                        <?php if (trim((string) ($block['caption'] ?? '')) !== ''): ?><figcaption class="text-secondary small mt-2"><?= $e((string) $block['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php elseif ($type === 'image_text'): ?>
                    <?php $image = $resolveContentImage($block); if ($image === null) continue; ?>
                    <section class="cms-rendered-content__image-text <?= ($block['position'] ?? 'left') === 'right' ? 'cms-rendered-content__image-text--right' : '' ?>">
                        <div class="cms-rendered-content__media">
                            <img
                                src="<?= $e($image['url']) ?>"
                                alt="<?= $e((string) ($block['alt'] ?? '')) ?>"
                                <?php if ($image['width'] !== null && $image['height'] !== null): ?>width="<?= $e($image['width']) ?>" height="<?= $e($image['height']) ?>"<?php endif; ?>
                                loading="lazy"
                                decoding="async"
                            >
                        </div>
                        <div class="fs-5 lh-lg"><?= nl2br($e((string) ($block['text'] ?? ''))) ?></div>
                    </section>
                <?php elseif ($type === 'callout'): ?>
                    <aside class="cms-rendered-content__callout"><strong class="d-block mb-2"><?= $e((string) ($block['title'] ?? 'Important')) ?></strong><div><?= nl2br($e((string) ($block['text'] ?? ''))) ?></div></aside>
                <?php elseif ($type === 'quote'): ?>
                    <blockquote>“<?= $e((string) ($block['text'] ?? '')) ?>”<?php if (trim((string) ($block['cite'] ?? '')) !== ''): ?><footer class="small text-secondary mt-2">— <?= $e((string) $block['cite']) ?></footer><?php endif; ?></blockquote>
                <?php elseif ($type === 'button'): ?>
                    <?php $url = (string) ($block['url'] ?? ''); if ($url === '' || (filter_var($url, FILTER_VALIDATE_URL) === false && !str_starts_with($url, '/'))) continue; ?>
                    <p><a class="tg-button tg-button--emerald" href="<?= $e($url) ?>" data-ripple><?= $e((string) ($block['label'] ?? 'Learn more')) ?></a></p>
                <?php elseif ($type === 'divider'): ?><hr>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($indexHref !== null && $indexLabel !== null): ?>
        <footer class="tg-content-detail__footer">
            <a class="tg-button tg-button--outline" href="<?= $e($indexHref) ?>"><?= $e($indexLabel) ?></a>
        </footer>
    <?php endif; ?>
</article>
