<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use InvalidArgumentException;

/** Represents one immutable ordered public navigation item. */
final readonly class NavigationItem
{
    public const MAIN = 'main';
    public const FOOTER = 'footer';
    public const LOCATIONS = [self::MAIN, self::FOOTER];

    public const ROUTE = 'route';
    public const PAGE = 'page';
    public const GROUP = 'group';
    public const LINK_TYPES = [self::ROUTE, self::PAGE, self::GROUP];

    public function __construct(
        private int $id,
        private string $location,
        private string $label,
        private string $linkType,
        private ?string $routeKey,
        private ?int $pageId,
        private ?int $parentId,
        private int $sortOrder,
        private bool $active,
        private ?int $createdBy,
        private ?int $updatedBy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        if (
            $id <= 0
            || !in_array($location, self::LOCATIONS, true)
            || !$this->requiredText($label, 80)
            || !in_array($linkType, self::LINK_TYPES, true)
            || !$this->validTarget($linkType, $routeKey, $pageId)
            || ($parentId !== null && $parentId <= 0)
            || ($location === self::FOOTER && $parentId !== null)
            || ($linkType === self::GROUP && ($location !== self::MAIN || $parentId !== null))
            || $sortOrder <= 0
            || ($createdBy !== null && $createdBy <= 0)
            || ($updatedBy !== null && $updatedBy <= 0)
            || $createdAt->getOffset() !== 0
            || $updatedAt->getOffset() !== 0
        ) {
            throw new InvalidArgumentException('Navigation item data is invalid.');
        }
    }

    public function id(): int { return $this->id; }
    public function location(): string { return $this->location; }
    public function label(): string { return $this->label; }
    public function linkType(): string { return $this->linkType; }
    public function routeKey(): ?string { return $this->routeKey; }
    public function pageId(): ?int { return $this->pageId; }
    public function parentId(): ?int { return $this->parentId; }
    public function sortOrder(): int { return $this->sortOrder; }
    public function isActive(): bool { return $this->active; }
    public function createdBy(): ?int { return $this->createdBy; }
    public function updatedBy(): ?int { return $this->updatedBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function isGroup(): bool { return $this->linkType === self::GROUP; }

    private function requiredText(string $value, int $maximumLength): bool
    {
        return $value !== ''
            && $value === trim($value)
            && mb_check_encoding($value, 'UTF-8')
            && !str_contains($value, "\0")
            && mb_strlen($value, 'UTF-8') <= $maximumLength;
    }

    private function validTarget(string $linkType, ?string $routeKey, ?int $pageId): bool
    {
        return match ($linkType) {
            self::ROUTE => is_string($routeKey) && $routeKey !== '' && $pageId === null,
            self::PAGE => $routeKey === null && is_int($pageId) && $pageId > 0,
            self::GROUP => $routeKey === null && $pageId === null,
            default => false,
        };
    }
}
