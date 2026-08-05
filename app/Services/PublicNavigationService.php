<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CmsPage;
use App\Models\NavigationItem;
use App\Repositories\NavigationRepository;
use App\Repositories\PageContentProfileRepository;
use App\Repositories\PageRepository;
use Throwable;

/** Resolves active navigation items into safe public links and dropdown groups. */
final readonly class PublicNavigationService
{
    public function __construct(
        private NavigationRepository $navigation,
        private PageRepository $pages,
        private NavigationRouteCatalog $routes,
        private ?PageContentProfileRepository $profiles = null,
    ) {
    }

    /**
     * @return array{
     *     main: list<array{label: string, href: ?string, children: list<array{label: string, href: string}>}>,
     *     footer: list<array{label: string, href: string}>
     * }
     */
    public function layout(): array
    {
        try {
            $items = $this->navigation->findAllActive();
            $publishedPages = [];
            $hasPageTarget = false;
            foreach ($items as $item) {
                if ($item->linkType() === NavigationItem::PAGE) {
                    $hasPageTarget = true;
                    break;
                }
            }
            if ($hasPageTarget) {
                foreach ($this->pages->findAllPublished() as $page) {
                    if (
                        $page instanceof CmsPage
                        && $page->isPublished()
                        && !$this->routes->isReservedPageSlug($page->slug())
                    ) {
                        $publishedPages[$page->id()] = $page;
                    }
                }
            }

            $main = [];
            $footer = [];
            $childrenByParent = [];

            foreach ($items as $item) {
                if ($item->parentId() !== null) {
                    $childrenByParent[$item->parentId()][] = $item;
                }
            }

            foreach ($items as $item) {
                if ($item->parentId() !== null) {
                    continue;
                }

                if ($item->location() === NavigationItem::FOOTER) {
                    $link = $this->link($item, $publishedPages);
                    if ($link !== null) {
                        $footer[] = $link;
                    }
                    continue;
                }

                if ($item->isGroup()) {
                    $children = [];
                    foreach ($childrenByParent[$item->id()] ?? [] as $child) {
                        $link = $this->link($child, $publishedPages);
                        if ($link !== null) {
                            $children[] = $link;
                        }
                    }
                    if ($children !== []) {
                        $main[] = [
                            'label' => $item->label(),
                            'href' => null,
                            'children' => $children,
                        ];
                    }
                    continue;
                }

                $link = $this->link($item, $publishedPages);
                if ($link !== null) {
                    $main[] = [...$link, 'children' => []];
                }
            }

            return ['main' => $main, 'footer' => $footer];
        } catch (Throwable) {
            return $this->fallback();
        }
    }

    /**
     * @param array<int, CmsPage> $publishedPages
     * @return array{label: string, href: string}|null
     */
    private function link(NavigationItem $item, array $publishedPages): ?array
    {
        if ($item->linkType() === NavigationItem::ROUTE) {
            $routeKey = $item->routeKey();
            $path = is_string($routeKey) ? $this->routes->path($routeKey) : null;

            return is_string($path) ? ['label' => $item->label(), 'href' => $path] : null;
        }

        if ($item->linkType() !== NavigationItem::PAGE || $item->pageId() === null) {
            return null;
        }

        $page = $publishedPages[$item->pageId()] ?? null;
        if (!$page instanceof CmsPage) {
            return null;
        }

        $contentType = $this->profiles?->findByPageId($page->id())?->contentType() ?? 'page';
        $prefix = match ($contentType) {
            'news' => '/news/',
            'notice' => '/notices/',
            default => '/',
        };

        return ['label' => $item->label(), 'href' => $prefix . $page->slug()];
    }

    /**
     * @return array{
     *     main: list<array{label: string, href: ?string, children: list<array{label: string, href: string}>}>,
     *     footer: list<array{label: string, href: string}>
     * }
     */
    private function fallback(): array
    {
        $main = [];
        foreach ($this->routes->all() as $route) {
            $main[] = [
                'label' => $route['label'],
                'href' => $route['path'],
                'children' => [],
            ];
        }

        return ['main' => $main, 'footer' => []];
    }
}
