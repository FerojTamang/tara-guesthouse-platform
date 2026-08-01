<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use App\Models\CmsPage;
use App\Models\SiteSetting;
use App\Repositories\PageRepository;
use App\Repositories\SettingRepository;
use DateTimeImmutable;
use JsonException;
use Throwable;

/**
 * Exposes published CMS pages, approved public settings, and homepage composition.
 */
final readonly class PublicCmsService
{
    /** @var list<string> */
    private const RESERVED_PAGE_SLUGS = [
        'activities', 'admin', 'api', 'availability', 'booking', 'contact',
        'gallery', 'review', 'reviews', 'rooms',
    ];

    /** @var \Closure(): SettingDefinitionRegistry */
    private \Closure $definitions;

    /** @var \Closure(): array */
    private \Closure $reviews;

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
    ) {
        $this->definitions = \Closure::fromCallable($definitions);
        $this->reviews = \Closure::fromCallable($reviews ?? static fn (): array => []);

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
     *     reviews: list<array{guest_name: string, rating: int, title: ?string, comment: string, created_at: string}>
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
                'activities' => $this->activities->list(),
                'gallery' => $this->gallery->list(),
                'reviews' => $this->homepageReviews(),
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
     *     page_id: int,
     *     title: string,
     *     slug: string,
     *     content: string,
     *     meta_title: ?string,
     *     meta_description: ?string,
     *     published_at: string,
     *     canonical_url: ?string,
     *     site_name: string
     * }
     */
    public function page(string $slug): array
    {
        return $this->execute(function () use ($slug): array {
            if (
                preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
                || mb_strlen($slug, 'UTF-8') > 200
                || in_array($slug, self::RESERVED_PAGE_SLUGS, true)
            ) {
                throw CmsException::pageNotFound();
            }

            $page = $this->pages->findPublishedBySlug($slug);

            if (!$page instanceof CmsPage || !$page->isPublished()) {
                throw CmsException::pageNotFound();
            }

            $publishedAt = $page->publishedAt();

            if (!$publishedAt instanceof DateTimeImmutable) {
                throw CmsException::failure();
            }

            return [
                'page_id' => $page->id(),
                'title' => $page->title(),
                'slug' => $page->slug(),
                'content' => $page->content(),
                'meta_title' => $page->metaTitle(),
                'meta_description' => $page->metaDescription(),
                'published_at' => $publishedAt->format(DATE_ATOM),
                'canonical_url' => $this->canonical('/' . $page->slug()),
                'site_name' => $this->applicationName,
            ];
        });
    }

    /**
     * @return array{
     *     navigation_pages: list<array{title: string, slug: string}>,
     *     footer_settings: list<array{setting_key: string, label: string, value: mixed, value_type: string}>
     * }
     */
    public function layoutContext(): array
    {
        return $this->execute(function (): array {
            try {
                $publicSettings = $this->publicSettings();
            } catch (CmsException) {
                // Public pages remain readable when optional footer settings are unavailable.
                $publicSettings = [];
            }

            return [
                'navigation_pages' => $this->navigationPages(),
                'footer_settings' => $this->footerSettings($publicSettings),
            ];
        });
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
        $result = [];

        foreach ($this->pages->findAllPublished() as $page) {
            if (
                !$page instanceof CmsPage
                || !$page->isPublished()
                || in_array($page->slug(), self::RESERVED_PAGE_SLUGS, true)
            ) {
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
