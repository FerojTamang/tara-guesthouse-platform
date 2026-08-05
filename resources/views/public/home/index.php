<?php
$homeLatestNews = is_array($latestNews ?? null) && array_is_list($latestNews) ? $latestNews : [];
$homeActiveNotice = is_array($activeNotice ?? null) ? $activeNotice : null;
$homeDate = static function (mixed $value): ?string {
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
<div class="tg-home">
    <section class="tg-hero" aria-labelledby="home-heading">
        <div class="tg-container tg-hero__inner">
            <div class="tg-hero__copy">
                <p class="tg-eyebrow tg-eyebrow--light">Sauraha, Nepal</p>
                <h1 id="home-heading">Nature&rsquo;s Luxury.<br><span>Your Jungle Retreat.</span></h1>
                <p class="tg-hero__lead">Experience warm hospitality, peaceful stays, and memorable moments in the heart of Sauraha.</p>
                <div class="tg-button-row">
                    <a class="tg-button tg-button--mint" href="/availability" data-ripple>
                        Check Availability
                        <?= $partial('components.public-icon', ['name' => 'arrow']) ?>
                    </a>
                    <a class="tg-text-link tg-text-link--light" href="/rooms">
                        Explore our rooms
                        <?= $partial('components.public-icon', ['name' => 'arrow']) ?>
                    </a>
                </div>
            </div>

            <aside class="tg-availability-card" aria-labelledby="home-availability-heading">
                <p class="tg-eyebrow">Plan your stay</p>
                <h2 id="home-availability-heading">Find your room</h2>
                <p class="tg-availability-card__intro">Choose your dates and guest count to see rooms available for your complete stay.</p>
                <form class="tg-availability-form" method="get" action="/availability">
                    <div class="tg-form-field">
                        <label for="home-check-in">Check-in</label>
                        <input id="home-check-in" name="check_in" type="date" required>
                    </div>
                    <div class="tg-form-field">
                        <label for="home-check-out">Check-out</label>
                        <input id="home-check-out" name="check_out" type="date" required>
                    </div>
                    <div class="tg-form-grid">
                        <div class="tg-form-field">
                            <label for="home-adults">Adults</label>
                            <input id="home-adults" name="adults" type="number" min="1" step="1" value="1" required>
                        </div>
                        <div class="tg-form-field">
                            <label for="home-children">Children</label>
                            <input id="home-children" name="children" type="number" min="0" step="1" value="0" required>
                        </div>
                    </div>
                    <button class="tg-button tg-button--emerald tg-button--block" type="submit" data-ripple>
                        Check Availability
                        <?= $partial('components.public-icon', ['name' => 'arrow']) ?>
                    </button>
                </form>
            </aside>
        </div>
    </section>

    <section class="tg-trust" aria-label="Why guests choose Tara Guest House">
        <div class="tg-container tg-trust__grid">
            <?php foreach ([
                ['bed', 'Comfortable Rooms', 'Well-prepared spaces for a restful stay'],
                ['calendar', 'Easy Direct Booking', 'Check availability through the official website'],
                ['compass', 'Sauraha Experiences', 'Discover nearby nature and culture'],
                ['heart', 'Warm Local Hospitality', 'A welcoming base for your visit'],
            ] as [$icon, $heading, $copy]): ?>
                <article class="tg-trust-item">
                    <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => $icon]) ?></span>
                    <div>
                        <h2><?= $e($heading) ?></h2>
                        <p><?= $e($copy) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($homeActiveNotice !== null || $homeLatestNews !== []): ?>
        <section class="tg-home-updates" aria-label="Latest news and guest notices">
            <div class="tg-container">
                <?php if ($homeActiveNotice !== null): ?>
                    <?php $noticeStyle = is_string($homeActiveNotice['notice_style'] ?? null) ? $homeActiveNotice['notice_style'] : 'information'; ?>
                    <article class="tg-home-notice tg-home-notice--<?= $e($noticeStyle) ?>">
                        <div class="tg-home-notice__icon" aria-hidden="true"><?= $partial('components.public-icon', ['name' => $noticeStyle === 'event' ? 'calendar' : 'leaf']) ?></div>
                        <div class="tg-home-notice__body">
                            <p class="tg-eyebrow mb-2"><?= $e(ucfirst($noticeStyle)) ?> notice</p>
                            <h2><?= $e((string) $homeActiveNotice['title']) ?></h2>
                            <p><?= $e((string) $homeActiveNotice['summary']) ?></p>
                        </div>
                        <a class="tg-button tg-button--outline tg-button--small" href="<?= $e((string) $homeActiveNotice['href']) ?>">Read notice</a>
                    </article>
                <?php endif; ?>

                <?php if ($homeLatestNews !== []): ?>
                    <div class="tg-home-news">
                        <div class="tg-section-heading">
                            <div>
                                <p class="tg-eyebrow">Latest from Tara</p>
                                <h2 id="home-news-heading">News &amp; stories</h2>
                            </div>
                            <a class="tg-text-link" href="/news">View all news <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a>
                        </div>
                        <div class="tg-home-news__grid" aria-labelledby="home-news-heading">
                            <?php foreach ($homeLatestNews as $newsItem): ?>
                                <?php
                                $newsImage = is_array($newsItem['featured_image'] ?? null) ? $newsItem['featured_image'] : null;
                                $newsDate = $homeDate($newsItem['published_at'] ?? null);
                                ?>
                                <article class="tg-card tg-home-news-card">
                                    <?php if ($newsImage !== null): ?>
                                        <a class="tg-card__media tg-home-news-card__media" href="<?= $e((string) $newsItem['href']) ?>">
                                            <img
                                                src="<?= $e((string) ($newsImage['url'] ?? '')) ?>"
                                                alt="<?= $e((string) ($newsImage['alt_text'] ?? '')) ?>"
                                                width="<?= $e((int) ($newsImage['width'] ?? 0)) ?>"
                                                height="<?= $e((int) ($newsImage['height'] ?? 0)) ?>"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        </a>
                                    <?php endif; ?>
                                    <div class="tg-card__body">
                                        <?php if ($newsDate !== null): ?><p class="tg-card__kicker"><time datetime="<?= $e((string) $newsItem['published_at']) ?>"><?= $e($newsDate) ?></time></p><?php endif; ?>
                                        <h3><a href="<?= $e((string) $newsItem['href']) ?>"><?= $e((string) $newsItem['title']) ?></a></h3>
                                        <p class="tg-card__description"><?= $e((string) $newsItem['summary']) ?></p>
                                        <a class="tg-text-link" href="<?= $e((string) $newsItem['href']) ?>">Read story <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($homeActiveNotice !== null): ?>
                    <p class="tg-home-updates__notices-link"><a class="tg-text-link" href="/notices">View all notices <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="tg-home-content">
        <section class="tg-discovery tg-container" aria-label="Rooms and Sauraha activities">
            <div class="tg-discovery__rooms">
                <div class="tg-section-heading">
                    <div>
                        <p class="tg-eyebrow">Rest well</p>
                        <h2 id="home-rooms-heading">Featured Rooms</h2>
                    </div>
                    <a class="tg-text-link" href="/rooms">View all rooms <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a>
                </div>

                <?php if ($rooms === []): ?>
                    <div class="tg-empty-state">
                        <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => 'bed']) ?></span>
                        <h3>Rooms are being prepared</h3>
                        <p>No rooms are currently published. Please check again soon.</p>
                    </div>
                <?php else: ?>
                    <div class="tg-room-grid" aria-labelledby="home-rooms-heading">
                        <?php foreach (array_slice($rooms, 0, 3) as $room): ?>
                            <article class="tg-card tg-room-card">
                                <a class="tg-card__media" href="/rooms/<?= $e($room['slug']) ?>" aria-label="View details for <?= $e($room['name']) ?>">
                                    <img
                                        src="<?= $e($room['primaryImageUrl']) ?>"
                                        alt="<?= $e($room['name']) ?>"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </a>
                                <div class="tg-card__body">
                                    <p class="tg-card__kicker"><?= $e($room['roomTypeName']) ?></p>
                                    <h3><a href="/rooms/<?= $e($room['slug']) ?>"><?= $e($room['name']) ?></a></h3>
                                    <p class="tg-card__description"><?= $e($room['description']) ?></p>
                                    <div class="tg-room-card__meta">
                                        <span><?= $partial('components.public-icon', ['name' => 'users']) ?> <?= $e((string) $room['capacityAdults']) ?> adult<?= $room['capacityAdults'] === 1 ? '' : 's' ?></span>
                                        <span><?= $e((string) $room['capacityChildren']) ?> child<?= $room['capacityChildren'] === 1 ? '' : 'ren' ?></span>
                                    </div>
                                    <div class="tg-card__footer">
                                        <p class="tg-price"><span>NPR</span> <?= $e($room['pricePerNight']) ?><small>/ night</small></p>
                                        <a class="tg-button tg-button--outline tg-button--small" href="/rooms/<?= $e($room['slug']) ?>" data-ripple>View Room</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tg-discovery__activities">
                <div class="tg-section-heading">
                    <div>
                        <p class="tg-eyebrow">Explore nearby</p>
                        <h2 id="home-activities-heading">Popular Activities</h2>
                    </div>
                    <a class="tg-text-link" href="/activities">All activities <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a>
                </div>

                <?php if ($activities === []): ?>
                    <div class="tg-empty-state">
                        <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => 'compass']) ?></span>
                        <h3>Experiences are being curated</h3>
                        <p>No activities are currently published.</p>
                    </div>
                <?php else: ?>
                    <div class="tg-activity-grid" aria-labelledby="home-activities-heading">
                        <?php foreach ($activities as $activity): ?>
                            <article class="tg-card tg-activity-card">
                                <div class="tg-card__media tg-activity-card__media">
                                    <?php if ($activity['image_url'] !== null): ?>
                                        <img src="<?= $e($activity['image_url']) ?>" alt="<?= $e($activity['title']) ?>" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="tg-image-placeholder" aria-hidden="true"><?= $partial('components.public-icon', ['name' => 'leaf']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="tg-card__body">
                                    <h3><?= $e($activity['title']) ?></h3>
                                    <p class="tg-card__description"><?= $e($activity['summary']) ?></p>
                                    <?php if ($activity['duration'] !== null || $activity['location'] !== null): ?>
                                        <div class="tg-activity-card__meta">
                                            <?php if ($activity['duration'] !== null): ?><span><?= $partial('components.public-icon', ['name' => 'calendar']) ?> <?= $e($activity['duration']) ?></span><?php endif; ?>
                                            <?php if ($activity['location'] !== null): ?><span><?= $partial('components.public-icon', ['name' => 'pin']) ?> <?= $e($activity['location']) ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($activity['price'] !== null): ?>
                                        <p class="tg-activity-card__price">NPR <?= $e($activity['price']) ?><?= $activity['price_unit'] === null ? '' : ' ' . $e($activity['price_unit']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="tg-story tg-container" aria-label="Gallery and guest house story">
            <div class="tg-story__gallery">
                <div class="tg-section-heading">
                    <div>
                        <p class="tg-eyebrow">A glimpse of Tara</p>
                        <h2 id="home-gallery-heading">Gallery Preview</h2>
                    </div>
                    <a class="tg-text-link" href="/gallery">View gallery <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a>
                </div>

                <?php if ($galleryItems === []): ?>
                    <div class="tg-empty-state">
                        <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => 'leaf']) ?></span>
                        <h3>The gallery is being prepared</h3>
                        <p>No gallery images are currently published.</p>
                    </div>
                <?php else: ?>
                    <div class="tg-gallery-mosaic" aria-labelledby="home-gallery-heading">
                        <?php foreach ($galleryItems as $index => $item): ?>
                            <figure class="tg-gallery-tile tg-gallery-tile--<?= $e((string) ($index + 1)) ?>">
                                <img
                                    src="<?= $e($item['image_url']) ?>"
                                    alt="<?= $e($item['alt_text']) ?>"
                                    loading="lazy"
                                    decoding="async"
                                >
                                <figcaption>
                                    <strong><?= $e($item['title']) ?></strong>
                                    <?php if ($item['caption'] !== null): ?><span><?= $e($item['caption']) ?></span><?php endif; ?>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <article class="tg-about" aria-labelledby="home-about-heading">
                <div class="tg-about__image-wrap">
                    <picture>
                        <source srcset="/assets/images/home/about-tara-guest-house.webp" type="image/webp">
                        <img
                            src="/assets/images/home/about-tara-guest-house.png"
                            width="1254"
                            height="1254"
                            alt="Tara Guest House courtyard surrounded by tropical greenery"
                            loading="lazy"
                            decoding="async"
                        >
                    </picture>
                    <span class="tg-about__line" aria-hidden="true"></span>
                </div>
                <div class="tg-about__content">
                    <p class="tg-eyebrow">A thoughtful stay</p>
                    <h2 id="home-about-heading">Why Stay With Us</h2>
                    <p class="tg-about__lead">Tara Guest House offers a calm and welcoming base for discovering the natural rhythm of Sauraha.</p>
                    <ul class="tg-feature-list">
                        <?php foreach ([
                            'Relaxed, comfortable stays',
                            'Thoughtful local hospitality',
                            'Convenient access to Sauraha experiences',
                            'A peaceful setting for rest and exploration',
                        ] as $feature): ?>
                            <li><span><?= $partial('components.public-icon', ['name' => 'check']) ?></span><?= $e($feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="tg-text-link" href="/contact">Plan your visit <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a>
                </div>
            </article>
        </section>
    </div>

    <section class="tg-reviews" aria-labelledby="home-reviews-heading">
        <div class="tg-container">
            <div class="tg-section-heading tg-section-heading--center">
                <div>
                    <p class="tg-eyebrow">From verified stays</p>
                    <h2 id="home-reviews-heading">Guest Reviews</h2>
                    <p>Reflections shared by guests after their stay.</p>
                </div>
            </div>
            <?php if ($reviews === []): ?>
                <div class="tg-empty-state tg-empty-state--reviews">
                    <span class="tg-icon-circle"><?= $partial('components.public-icon', ['name' => 'heart']) ?></span>
                    <h3>Guest stories will appear here</h3>
                    <p>Approved reviews from verified stays have not been published yet.</p>
                </div>
            <?php else: ?>
                <div class="tg-review-grid">
                    <?php foreach ($reviews as $review): ?>
                        <article class="tg-review-card">
                            <div class="tg-rating" aria-label="<?= $e((string) $review['rating']) ?> out of 5 stars">
                                <span aria-hidden="true"><?= str_repeat('&#9733;', $review['rating']) ?><?= str_repeat('&#9734;', 5 - $review['rating']) ?></span>
                            </div>
                            <?php if ($review['title'] !== null): ?><h3><?= $e($review['title']) ?></h3><?php endif; ?>
                            <blockquote><p><?= nl2br($e($review['comment']), false) ?></p></blockquote>
                            <footer>
                                <span class="tg-review-card__avatar" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($review['guest_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                                <div><strong><?= $e($review['guest_name']) ?></strong><span>Verified stay</span></div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="tg-reviews__link"><a class="tg-text-link" href="/reviews">Read all guest reviews <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a></div>
        </div>
    </section>

    <section class="tg-booking-cta" aria-labelledby="home-cta-heading">
        <div class="tg-container tg-booking-cta__content">
            <p class="tg-eyebrow tg-eyebrow--light">Your Sauraha stay</p>
            <h2 id="home-cta-heading">Ready for Your Jungle Escape?</h2>
            <p>Plan your stay and discover the natural character of Sauraha.</p>
            <div class="tg-button-row tg-button-row--center">
                <a class="tg-button tg-button--mint" href="/availability" data-ripple>Check Availability <?= $partial('components.public-icon', ['name' => 'arrow']) ?></a>
                <a class="tg-button tg-button--light-outline" href="/contact" data-ripple>Contact Us</a>
            </div>
        </div>
    </section>
</div>
