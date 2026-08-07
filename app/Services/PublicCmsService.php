<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use App\Models\CmsPage;
use App\Models\ContentMedia;
use App\Models\PageContentProfile;
use App\Models\PublishedContentItem;
use App\Models\SiteSetting;
use App\Repositories\ContentMediaRepository;
use App\Repositories\PageContentProfileRepository;
use App\Repositories\PageRepository;
use App\Repositories\PublicContentCatalogRepository;
use App\Repositories\SettingRepository;
use DateTimeImmutable;
use JsonException;
use Throwable;

/**
 * Exposes published CMS pages, approved public settings, and homepage composition.
 */
final readonly class PublicCmsService
{
    /** @var \Closure(): SettingDefinitionRegistry */
    private \Closure $definitions;

    /** @var \Closure(): array */
    private \Closure $reviews;

    /** @var \Closure(): DateTimeImmutable */
    private \Closure $clock;

    /**
     * @param callable(): SettingDefinitionRegistry $definitions
     * @param null|callable(): array $reviews
     */
    public function __construct(
        private PageRepository $pages,
        private SettingRepository $settings,
        callable $definitions,
        private PublicRoomCatalogService $rooms,
        private PublicActivityService $activities,
        private PublicGalleryService $gallery,
        private string $applicationName,
        private ?string $applicationUrl,
        ?callable $reviews = null,
        private ?PageContentProfileRepository $profiles = null,
        private ?ContentMediaRepository $contentMedia = null,
        ?callable $clock = null,
        private ?PublicContentCatalogRepository $publicContent = null,
    ) {
        $this->definitions = \Closure::fromCallable($definitions);
        $this->reviews = \Closure::fromCallable($reviews ?? static fn (): array => []);
        $this->clock = \Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        if ($applicationName === '' || $applicationName !== trim($applicationName)) {
            throw CmsException::failure();
        }

        if ($applicationUrl !== null) {
            $parts = parse_url($applicationUrl);

            if (
                filter_var($applicationUrl, FILTER_VALIDATE_URL) === false
                || !is_array($parts)
                || !isset($parts['scheme'], $parts['host'])
                || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['query'])
                || isset($parts['fragment'])
            ) {
                throw CmsException::failure();
            }
        }
    }

    /**
     * @return array{
     *     site_name: string,
     *     title: string,
     *     meta_description: string,
     *     canonical_url: ?string,
     *     navigation_pages: list<array{title: string, slug: string}>,
     *     footer_settings: list<array{setting_key: string, label: string, value: mixed, value_type: string}>,
     *     rooms: list<array<string, mixed>>,
     *     activities: list<array<string, mixed>>,
     *     gallery: list<array<string, mixed>>,
     *     reviews: list<array{guest_name: string, rating: int, title: ?string, comment: string, created_at: string}>,
     *     latest_news: list<array<string, mixed>>,
     *     active_notice: ?array<string, mixed>
     * }
     */
    public function homepage(): array
    {
        return $this->execute(function (): array {
            $layout = $this->layoutContext();

            return [
                'site_name' => $this->applicationName,
                'title' => $this->applicationName,
                'meta_description' => sprintf(
                    'Explore rooms, activities, and the gallery at %s.',
                    $this->applicationName,
                ),
                'canonical_url' => $this->canonical('/'),
                'navigation_pages' => $layout['navigation_pages'],
                'footer_settings' => $layout['footer_settings'],
                'rooms' => $this->rooms->list(),
                'activities' => $this->activities->homepage(4),
                'gallery' => $this->gallery->homepage(6),
                'reviews' => $this->homepageReviews(),
                'latest_news' => array_slice($this->contentListing('news'), 0, 3),
                'active_notice' => $this->contentListing('notice')[0] ?? null,
            ];
        });
    }

    /** @return list<array{guest_name: string, rating: int, title: ?string, comment: string, created_at: string}> */
    private function homepageReviews(): array
    {
        $reviews = ($this->reviews)();

        if (!is_array($reviews) || !array_is_list($reviews)) {
            throw CmsException::failure();
        }

        return $reviews;
    }

    /**
     * @return array{
     *     title: string,
     *     meta_description: string,
     *     canonical_url: ?string,
     *     items: list<array<string, mixed>>
     * }
     */
    public function newsIndex(): array
    {
        return $this->execute(fn (): array => [
            'title' => 'News | ' . $this->applicationName,
            'meta_description' => sprintf('Latest news and updates from %s.', $this->applicationName),
            'canonical_url' => $this->canonical('/news'),
            'items' => $this->contentListing('news'),
        ]);
    }

    /**
     * @return array{
     *     title: string,
     *     meta_description: string,
     *     canonical_url: ?string,
     *     items: list<array<string, mixed>>
     * }
     */
    public function noticesIndex(): array
    {
        return $this->execute(fn (): array => [
            'title' => 'Notices | ' . $this->applicationName,
            'meta_description' => sprintf('Current guest notices and important information from %s.', $this->applicationName),
            'canonical_url' => $this->canonical('/notices'),
            'items' => $this->contentListing('notice'),
        ]);
    }

    /** @return array<string, mixed> */
    public function newsArticle(string $slug): array
    {
        return $this->pageProjection($slug, 'news');
    }

    /** @return array<string, mixed> */
    public function notice(string $slug): array
    {
        return $this->pageProjection($slug, 'notice');
    }

    /** @return array<string, mixed> */
    public function page(string $slug): array
    {
        return $this->pageProjection($slug, null);
    }

    /**
     * @return array{
     *     page_id: int,
     *     title: string,
     *     slug: string,
     *     content: string,
     *     content_type: string,
     *     summary: ?string,
     *     notice_style: ?string,
     *     meta_title: ?string,
     *     meta_description: ?string,
     *     published_at: string,
     *     expires_at: ?string,
     *     is_featured: bool,
     *     is_pinned: bool,
     *     featured_image: ?array{url: string, alt_text: string, width: int, height: int},
     *     social_title: ?string,
     *     social_description: ?string,
     *     social_image: ?string,
     *     seo_noindex: bool,
     *     canonical_url: ?string,
     *     site_name: string
     * }
     */
    private function pageProjection(string $slug, ?string $expectedType): array
    {
        return $this->execute(function () use ($slug, $expectedType): array {
            if (
                preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
                || mb_strlen($slug, 'UTF-8') > 200
                || (new NavigationRouteCatalog())->isReservedPageSlug($slug)
            ) {
                throw CmsException::pageNotFound();
            }

            $page = $this->pages->findPublishedBySlug($slug);
            $now = $this->now();

            if (!$page instanceof CmsPage || !$page->isPublicAt($now)) {
                throw CmsException::pageNotFound();
            }

            $publishedAt = $page->publishedAt();
            if (!$publishedAt instanceof DateTimeImmutable) {
                throw CmsException::failure();
            }

            $profile = $this->profiles?->findByPageId($page->id());
            if ($profile instanceof PageContentProfile && $profile->isExpiredAt($now)) {
                throw CmsException::pageNotFound();
            }

            $contentType = $profile?->contentType() ?? 'page';
            if ($expectedType !== null && $contentType !== $expectedType) {
                throw CmsException::pageNotFound();
            }

            $featured = $this->media($profile?->featuredMediaId());
            $social = $this->media($profile?->socialMediaId()) ?? $featured;
            $canonicalPath = match ($contentType) {
                'news' => '/news/' . $page->slug(),
                'notice' => '/notices/' . $page->slug(),
                default => '/' . $page->slug(),
            };

            return [
                'page_id' => $page->id(),
                'title' => $page->title(),
                'slug' => $page->slug(),
                'content' => $page->content(),
                'content_type' => $contentType,
                'summary' => $profile?->summary(),
                'notice_style' => $profile?->noticeStyle(),
                'meta_title' => $page->metaTitle(),
                'meta_description' => $page->metaDescription(),
                'published_at' => $publishedAt->format(DATE_ATOM),
                'expires_at' => $profile?->expiresAt()?->format(DATE_ATOM),
                'is_featured' => $profile?->isFeatured() ?? false,
                'is_pinned' => $profile?->isPinned() ?? false,
                'featured_image' => $featured,
                'social_title' => $profile?->socialTitle(),
                'social_description' => $profile?->socialDescription(),
                'social_image' => isset($social['url'])
                    ? ($this->canonical($social['url']) ?? $social['url'])
                    : null,
                'seo_noindex' => $profile?->seoNoindex() ?? false,
                'canonical_url' => $this->canonical($canonicalPath),
                'site_name' => $this->applicationName,
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    private function contentListing(string $contentType): array
    {
        $items = $this->publicContent instanceof PublicContentCatalogRepository
            ? $this->publicContent->findAllPublishedByType($contentType)
            : $this->fallbackContentItems($contentType);
        $now = $this->now();
        $items = array_values(array_filter(
            $items,
            static fn (mixed $item): bool => $item instanceof PublishedContentItem
                && $item->profile()->contentType() === $contentType
                && $item->page()->isPublicAt($now)
                && !$item->profile()->isExpiredAt($now),
        ));

        usort($items, function (PublishedContentItem $left, PublishedContentItem $right) use ($contentType): int {
            $leftProfile = $left->profile();
            $rightProfile = $right->profile();
            $leftStyle = $contentType === 'notice' ? $this->noticePriority($leftProfile->noticeStyle()) : 0;
            $rightStyle = $contentType === 'notice' ? $this->noticePriority($rightProfile->noticeStyle()) : 0;

            return [
                $rightProfile->isPinned() ? 1 : 0,
                $rightStyle,
                $rightProfile->isFeatured() ? 1 : 0,
                $right->page()->publishedAt()?->getTimestamp() ?? 0,
                $right->page()->id(),
            ] <=> [
                $leftProfile->isPinned() ? 1 : 0,
                $leftStyle,
                $leftProfile->isFeatured() ? 1 : 0,
                $left->page()->publishedAt()?->getTimestamp() ?? 0,
                $left->page()->id(),
            ];
        });

        return array_map($this->contentCard(...), $items);
    }

    /** @return list<PublishedContentItem> */
    private function fallbackContentItems(string $contentType): array
    {
        if (!$this->profiles instanceof PageContentProfileRepository) {
            return [];
        }

        $now = $this->now();
        $pages = array_values(array_filter(
            $this->pages->findAllPublished(),
            static fn (mixed $page): bool => $page instanceof CmsPage && $page->isPublicAt($now),
        ));
        $profiles = $this->profiles->findByPageIds(array_map(
            static fn (CmsPage $page): int => $page->id(),
            $pages,
        ));
        $items = [];
        foreach ($pages as $page) {
            $profile = $profiles[$page->id()] ?? null;
            if (
                !$profile instanceof PageContentProfile
                || $profile->contentType() !== $contentType
                || $profile->isExpiredAt($now)
            ) {
                continue;
            }

            $media = null;
            $mediaId = $profile->featuredMediaId();
            if ($mediaId !== null && $this->contentMedia instanceof ContentMediaRepository) {
                $candidate = $this->contentMedia->findActiveById($mediaId);
                $media = $candidate instanceof ContentMedia ? $candidate : null;
            }
            $items[] = new PublishedContentItem($page, $profile, $media);
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function contentCard(PublishedContentItem $item): array
    {
        $page = $item->page();
        $profile = $item->profile();
        $publishedAt = $page->publishedAt();
        if (!$publishedAt instanceof DateTimeImmutable) {
            throw CmsException::failure();
        }

        $media = $item->featuredMedia();
        $contentType = $profile->contentType();

        return [
            'page_id' => $page->id(),
            'title' => $page->title(),
            'slug' => $page->slug(),
            'summary' => $this->contentSummary($page, $profile),
            'content_type' => $contentType,
            'notice_style' => $profile->noticeStyle(),
            'published_at' => $publishedAt->format(DATE_ATOM),
            'expires_at' => $profile->expiresAt()?->format(DATE_ATOM),
            'is_featured' => $profile->isFeatured(),
            'is_pinned' => $profile->isPinned(),
            'href' => ($contentType === 'news' ? '/news/' : '/notices/') . $page->slug(),
            'featured_image' => $media instanceof ContentMedia ? [
                'url' => $media->publicUrl(),
                'alt_text' => $media->altText(),
                'width' => $media->width(),
                'height' => $media->height(),
            ] : null,
        ];
    }

    private function contentSummary(CmsPage $page, PageContentProfile $profile): string
    {
        if ($profile->summary() !== null) {
            return $profile->summary();
        }

        $content = $page->content();
        try {
            $document = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
            if (
                is_array($document)
                && ($document['format'] ?? null) === 'tara-blocks-v1'
                && is_array($document['blocks'] ?? null)
            ) {
                $summary = $document['summary'] ?? null;
                if (is_string($summary) && trim($summary) !== '') {
                    return trim($summary);
                }

                $content = '';
                foreach ($document['blocks'] as $block) {
                    if (!is_array($block) || !is_string($block['type'] ?? null)) {
                        continue;
                    }

                    $candidate = match ($block['type']) {
                        'heading', 'paragraph', 'image_text', 'quote' => $block['text'] ?? null,
                        'callout' => $block['text'] ?? ($block['title'] ?? null),
                        default => null,
                    };
                    if (is_string($candidate) && trim($candidate) !== '') {
                        $content = trim($candidate);
                        break;
                    }
                }
            }
        } catch (JsonException) {
            // Legacy plain-text content remains a valid summary source.
        }

        $content = preg_replace('/\s+/u', ' ', trim($content)) ?? '';
        if ($content === '') {
            return $profile->contentType() === 'notice'
                ? 'Read the full guest notice.'
                : 'Read the full update.';
        }
        if (mb_strlen($content, 'UTF-8') <= 220) {
            return $content;
        }

        return rtrim(mb_substr($content, 0, 217, 'UTF-8')) . '...';
    }

    private function noticePriority(?string $style): int
    {
        return match ($style) {
            'important' => 4,
            'warning' => 3,
            'event' => 2,
            default => 1,
        };
    }

    /** @return array{url: string, alt_text: string, width: int, height: int}|null */
    private function media(?int $mediaId): ?array
    {
        if ($mediaId === null || !$this->contentMedia instanceof ContentMediaRepository) {
            return null;
        }

        $media = $this->contentMedia->findActiveById($mediaId);
        if (!$media instanceof ContentMedia) {
            return null;
        }

        return [
            'url' => $media->publicUrl(),
            'alt_text' => $media->altText(),
            'width' => $media->width(),
            'height' => $media->height(),
        ];
    }

    /**
     * @return array{
     *     navigation_pages: list<array{title: string, slug: string}>,
     *     footer_settings: list<array{setting_key: string, label: string, value: mixed, value_type: string}>
     * }
     */
    public function layoutContext(): array
    {
        return $this->execute(fn (): array => [
            'navigation_pages' => $this->navigationPages(),
            'footer_settings' => $this->footerLayoutSettings(),
        ]);
    }

    /** @return list<array{setting_key: string, label: string, value: mixed, value_type: string}> */
    public function footerLayoutSettings(): array
    {
        try {
            return $this->footerSettings($this->publicSettings());
        } catch (CmsException) {
            // Optional settings must not prevent public pages from rendering.
            return [];
        }
    }

    /**
     * @return list<array{setting_key: string, value: mixed, value_type: string}>
     */
    public function publicSettings(): array
    {
        return $this->execute(function (): array {
            try {
                $definitions = ($this->definitions)();
            } catch (CmsException) {
                // Missing or malformed definitions must never expose stored values.
                return [];
            } catch (Throwable) {
                return [];
            }

            if (!$definitions instanceof SettingDefinitionRegistry) {
                return [];
            }

            $result = [];

            foreach ($this->settings->findAllPublic() as $setting) {
                if (!$setting instanceof SiteSetting || !$setting->isPublic()) {
                    throw CmsException::failure();
                }

                try {
                    $definition = $definitions->get($setting->key());
                    $canonical = $definition->normalize($setting->value());
                } catch (CmsException $exception) {
                    throw CmsException::failure($exception);
                }

                if (
                    !$definition->isPublic()
                    || $definition->valueType() !== $setting->valueType()
                    || $canonical !== $setting->value()
                ) {
                    throw CmsException::failure();
                }

                $result[] = [
                    'setting_key' => $setting->key(),
                    'value' => $this->typedValue($setting),
                    'value_type' => $setting->valueType(),
                ];
            }

            return $result;
        });
    }

    /** @return list<array{title: string, slug: string}> */
    private function navigationPages(): array
    {
        $routes = new NavigationRouteCatalog();
        $pages = array_values(array_filter(
            $this->pages->findAllPublished(),
            static fn (mixed $page): bool => $page instanceof CmsPage
                && $page->isPublished()
                && !$routes->isReservedPageSlug($page->slug()),
        ));
        $profiles = $this->profiles instanceof PageContentProfileRepository && $pages !== []
            ? $this->profiles->findByPageIds(array_map(
                static fn (CmsPage $page): int => $page->id(),
                $pages,
            ))
            : [];
        $result = [];

        foreach ($pages as $page) {
            $profile = $profiles[$page->id()] ?? null;
            $contentType = $profile?->contentType() ?? 'page';
            if (in_array($contentType, ['news', 'notice'], true)) {
                continue;
            }

            $result[] = ['title' => $page->title(), 'slug' => $page->slug()];
        }

        return $result;
    }

    /**
     * @param list<array{setting_key: string, value: mixed, value_type: string}> $settings
     * @return list<array{setting_key: string, label: string, value: mixed, value_type: string}>
     */
    private function footerSettings(array $settings): array
    {
        $result = [];

        foreach ($settings as $setting) {
            $key = $setting['setting_key'];

            if (!$this->isFooterSettingKey($key) || !is_scalar($setting['value'])) {
                continue;
            }

            $result[] = [
                ...$setting,
                'label' => ucwords(str_replace(['.', '_', '-'], ' ', $key)),
            ];
        }

        return $result;
    }

    private function isFooterSettingKey(string $key): bool
    {
        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (in_array($segment, [
                'address', 'business', 'contact', 'email', 'footer', 'hours',
                'phone', 'social',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private function typedValue(SiteSetting $setting): mixed
    {
        return match ($setting->valueType()) {
            'integer' => $this->integerValue($setting->value()),
            'boolean' => $setting->value() === 'true',
            'json' => $this->jsonValue($setting->value()),
            default => $setting->value(),
        };
    }

    private function integerValue(string $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if (!is_int($integer)) {
            throw CmsException::failure();
        }

        return $integer;
    }

    /** @return array<mixed>|object */
    private function jsonValue(string $value): array|object
    {
        try {
            $decoded = json_decode($value, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw CmsException::failure($exception);
        }

        if (!is_array($decoded) && !is_object($decoded)) {
            throw CmsException::failure();
        }

        return $decoded;
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();
        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw CmsException::failure();
        }
        return $value;
    }

    private function canonical(string $path): ?string
    {
        if ($this->applicationUrl === null) {
            return null;
        }

        return rtrim($this->applicationUrl, '/') . $path;
    }

    private function execute(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
