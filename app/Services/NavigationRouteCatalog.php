<?php

declare(strict_types=1);

namespace App\Services;

/** Provides the approved built-in public routes available to navigation items. */
final class NavigationRouteCatalog
{
    /** @var list<string> */
    private const RESERVED_PAGE_SLUGS = [
        'activities', 'admin', 'api', 'availability', 'booking', 'contact',
        'gallery', 'news', 'notices', 'review', 'reviews', 'rooms',
    ];

    /** @var array<string, array{label: string, path: string}> */
    private const ROUTES = [
        'home' => ['label' => 'Home', 'path' => '/'],
        'rooms' => ['label' => 'Rooms', 'path' => '/rooms'],
        'activities' => ['label' => 'Activities', 'path' => '/activities'],
        'gallery' => ['label' => 'Gallery', 'path' => '/gallery'],
        'news' => ['label' => 'News', 'path' => '/news'],
        'notices' => ['label' => 'Notices', 'path' => '/notices'],
        'reviews' => ['label' => 'Reviews', 'path' => '/reviews'],
        'availability' => ['label' => 'Availability', 'path' => '/availability'],
        'contact' => ['label' => 'Contact', 'path' => '/contact'],
    ];

    /** @return list<array{key: string, label: string, path: string}> */
    public function all(): array
    {
        $result = [];
        foreach (self::ROUTES as $key => $route) {
            $result[] = ['key' => $key, ...$route];
        }

        return $result;
    }

    public function contains(string $key): bool
    {
        return array_key_exists($key, self::ROUTES);
    }

    public function path(string $key): ?string
    {
        return self::ROUTES[$key]['path'] ?? null;
    }

    public function isReservedPageSlug(string $slug): bool
    {
        return in_array($slug, self::RESERVED_PAGE_SLUGS, true);
    }
}
