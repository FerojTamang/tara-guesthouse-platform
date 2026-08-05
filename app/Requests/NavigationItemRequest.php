<?php

declare(strict_types=1);

namespace App\Requests;

use App\Exceptions\CmsException;
use App\Models\NavigationItem;
use App\Services\NavigationRouteCatalog;

/** Validates one exact Administrator navigation item payload. */
final readonly class NavigationItemRequest
{
    private function __construct(
        private string $location,
        private string $label,
        private string $linkType,
        private ?string $routeKey,
        private ?int $pageId,
        private ?int $parentId,
    ) {
    }

    public static function fromInput(
        mixed $location,
        mixed $label,
        mixed $linkType,
        mixed $routeKey,
        mixed $pageId,
        mixed $parentId,
    ): self {
        if (!is_string($location) || !is_string($label) || !is_string($linkType)) {
            throw CmsException::invalidInput('The navigation item payload is invalid.');
        }

        $label = trim($label);
        $routeKey = self::nullableString($routeKey);
        $pageId = self::nullableIdentifier($pageId);
        $parentId = self::nullableIdentifier($parentId);

        if (
            !in_array($location, NavigationItem::LOCATIONS, true)
            || $label === ''
            || !mb_check_encoding($label, 'UTF-8')
            || str_contains($label, "\0")
            || mb_strlen($label, 'UTF-8') > 80
            || !in_array($linkType, NavigationItem::LINK_TYPES, true)
        ) {
            throw CmsException::invalidInput('The navigation item is invalid.');
        }

        $routes = new NavigationRouteCatalog();
        $validTarget = match ($linkType) {
            NavigationItem::ROUTE => is_string($routeKey) && $routes->contains($routeKey) && $pageId === null,
            NavigationItem::PAGE => $routeKey === null && is_int($pageId) && $pageId > 0,
            NavigationItem::GROUP => $routeKey === null && $pageId === null,
            default => false,
        };

        if (
            !$validTarget
            || ($location === NavigationItem::FOOTER && $parentId !== null)
            || ($linkType === NavigationItem::GROUP && ($location !== NavigationItem::MAIN || $parentId !== null))
        ) {
            throw CmsException::invalidInput('The navigation destination is invalid.');
        }

        return new self($location, $label, $linkType, $routeKey, $pageId, $parentId);
    }

    public function location(): string { return $this->location; }
    public function label(): string { return $this->label; }
    public function linkType(): string { return $this->linkType; }
    public function routeKey(): ?string { return $this->routeKey; }
    public function pageId(): ?int { return $this->pageId; }
    public function parentId(): ?int { return $this->parentId; }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw CmsException::invalidInput('The navigation destination is invalid.');
        }

        return $value;
    }

    private static function nullableIdentifier(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) || $value <= 0) {
            throw CmsException::invalidInput('The navigation destination is invalid.');
        }

        return $value;
    }
}
