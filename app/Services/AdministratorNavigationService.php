<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CmsException;
use App\Models\AuditLogEntry;
use App\Models\CmsPage;
use App\Models\NavigationItem;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\NavigationRepository;
use App\Repositories\PageRepository;
use App\Requests\NavigationItemRequest;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

/** Owns Administrator navigation validation, ordering, and audit evidence. */
final readonly class AdministratorNavigationService
{
    /** @var \Closure(): DateTimeImmutable */
    private \Closure $clock;

    /** @param null|callable(): DateTimeImmutable $clock */
    public function __construct(
        private NavigationRepository $navigation,
        private PageRepository $pages,
        private AuditLogWriteRepository $auditLogs,
        private CmsTransaction $transaction,
        ?callable $clock = null,
    ) {
        $this->clock = \Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }

    /** @return list<NavigationItem> */
    public function listItems(): array
    {
        return $this->executeRead(fn (): array => $this->navigation->findAll());
    }

    /** @return list<CmsPage> */
    public function listPages(): array
    {
        return $this->executeRead(fn (): array => $this->pages->findAll());
    }

    public function create(
        NavigationItemRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): NavigationItem {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use ($request, $administratorId, $ipAddress, $userAgent): NavigationItem {
            $items = $this->navigation->findAll();
            $this->validateDestination($request, $items, null);
            $sortOrder = $this->nextSortOrder($items, $request->location(), $request->parentId());
            $item = $this->navigation->create(
                $request->location(),
                $request->label(),
                $request->linkType(),
                $request->routeKey(),
                $request->pageId(),
                $request->parentId(),
                $sortOrder,
                $administratorId,
            );

            $this->appendAudit(
                $administratorId,
                'navigation_item_created',
                $item,
                'Administrator added a public navigation item.',
                $ipAddress,
                $userAgent,
            );

            return $item;
        });
    }

    public function update(
        int $id,
        NavigationItemRequest $request,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): NavigationItem {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use ($id, $request, $administratorId, $ipAddress, $userAgent): NavigationItem {
            $items = $this->navigation->findAll();
            $existing = $this->itemFrom($items, $id);
            $this->validateDestination($request, $items, $existing);

            if ($existing->isGroup() && $request->linkType() !== NavigationItem::GROUP) {
                foreach ($items as $item) {
                    if ($item->parentId() === $existing->id()) {
                        throw CmsException::navigationConflict(
                            'Move or remove the dropdown items before changing this group.',
                        );
                    }
                }
            }

            $sameScope = $existing->location() === $request->location()
                && $existing->parentId() === $request->parentId();
            $sortOrder = $sameScope
                ? $existing->sortOrder()
                : $this->nextSortOrder($items, $request->location(), $request->parentId());

            $updated = $this->navigation->update(
                $existing->id(),
                $request->location(),
                $request->label(),
                $request->linkType(),
                $request->routeKey(),
                $request->pageId(),
                $request->parentId(),
                $sortOrder,
                $administratorId,
            );
            if (!$updated instanceof NavigationItem) {
                throw CmsException::navigationItemNotFound();
            }

            $this->appendAudit(
                $administratorId,
                'navigation_item_updated',
                $updated,
                'Administrator updated a public navigation item.',
                $ipAddress,
                $userAgent,
            );

            return $updated;
        });
    }

    public function setActive(
        int $id,
        bool $active,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): NavigationItem {
        $this->assertAdministrator($administratorId);

        return $this->transact(function () use ($id, $active, $administratorId, $ipAddress, $userAgent): NavigationItem {
            $existing = $this->navigation->findById($id);
            if (!$existing instanceof NavigationItem) {
                throw CmsException::navigationItemNotFound();
            }

            $updated = $this->navigation->setActive($id, $active, $administratorId);
            if (!$updated instanceof NavigationItem) {
                throw CmsException::navigationItemNotFound();
            }

            $this->appendAudit(
                $administratorId,
                'navigation_item_state_changed',
                $updated,
                $active
                    ? 'Administrator enabled a public navigation item.'
                    : 'Administrator disabled a public navigation item.',
                $ipAddress,
                $userAgent,
            );

            return $updated;
        });
    }

    public function move(
        int $id,
        string $direction,
        int $administratorId,
        ?string $ipAddress,
        ?string $userAgent,
    ): NavigationItem {
        $this->assertAdministrator($administratorId);
        if (!in_array($direction, ['up', 'down'], true)) {
            throw CmsException::invalidInput('The navigation move direction is invalid.');
        }

        return $this->transact(function () use ($id, $direction, $administratorId, $ipAddress, $userAgent): NavigationItem {
            $items = $this->navigation->findAll();
            $existing = $this->itemFrom($items, $id);
            $siblings = array_values(array_filter(
                $items,
                static fn (NavigationItem $item): bool => $item->location() === $existing->location()
                    && $item->parentId() === $existing->parentId(),
            ));
            usort($siblings, static fn (NavigationItem $left, NavigationItem $right): int => [
                $left->sortOrder(), $left->id(),
            ] <=> [
                $right->sortOrder(), $right->id(),
            ]);

            $index = null;
            foreach ($siblings as $position => $sibling) {
                if ($sibling->id() === $existing->id()) {
                    $index = $position;
                    break;
                }
            }
            if (!is_int($index)) {
                throw CmsException::navigationItemNotFound();
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if (!isset($siblings[$targetIndex])) {
                return $existing;
            }

            $target = $siblings[$targetIndex];
            if (
                !$this->navigation->setSortOrder($existing->id(), $target->sortOrder(), $administratorId)
                || !$this->navigation->setSortOrder($target->id(), $existing->sortOrder(), $administratorId)
            ) {
                throw CmsException::navigationConflict('The navigation order changed before the move completed.');
            }

            $updated = $this->navigation->findById($existing->id());
            if (!$updated instanceof NavigationItem) {
                throw CmsException::navigationItemNotFound();
            }

            $this->appendAudit(
                $administratorId,
                'navigation_item_moved',
                $updated,
                'Administrator changed public navigation order.',
                $ipAddress,
                $userAgent,
            );

            return $updated;
        });
    }

    /** @param list<NavigationItem> $items */
    private function validateDestination(
        NavigationItemRequest $request,
        array $items,
        ?NavigationItem $existing,
    ): void {
        if ($request->pageId() !== null) {
            $page = $this->pages->findById($request->pageId());
            if (!$page instanceof CmsPage) {
                throw CmsException::invalidInput('The selected CMS page does not exist.');
            }
            if ((new NavigationRouteCatalog())->isReservedPageSlug($page->slug())) {
                throw CmsException::invalidInput('The selected CMS page uses a reserved public address.');
            }
        }

        $parentId = $request->parentId();
        if ($parentId === null) {
            return;
        }
        if ($existing instanceof NavigationItem && $existing->id() === $parentId) {
            throw CmsException::invalidInput('A navigation item cannot be its own parent.');
        }

        $parent = $this->itemFrom($items, $parentId);
        if (
            !$parent->isGroup()
            || $parent->location() !== NavigationItem::MAIN
            || $request->location() !== NavigationItem::MAIN
        ) {
            throw CmsException::invalidInput('The selected dropdown group is invalid.');
        }
    }

    /** @param list<NavigationItem> $items */
    private function itemFrom(array $items, int $id): NavigationItem
    {
        foreach ($items as $item) {
            if ($item->id() === $id) {
                return $item;
            }
        }

        throw CmsException::navigationItemNotFound();
    }

    /** @param list<NavigationItem> $items */
    private function nextSortOrder(array $items, string $location, ?int $parentId): int
    {
        $maximum = 0;
        foreach ($items as $item) {
            if ($item->location() === $location && $item->parentId() === $parentId) {
                $maximum = max($maximum, $item->sortOrder());
            }
        }

        return $maximum + 10;
    }

    private function appendAudit(
        int $administratorId,
        string $eventType,
        NavigationItem $item,
        string $description,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        try {
            $metadata = json_encode([
                'location' => $item->location(),
                'label' => $item->label(),
                'link_type' => $item->linkType(),
                'route_key' => $item->routeKey(),
                'page_id' => $item->pageId(),
                'parent_id' => $item->parentId(),
                'sort_order' => $item->sortOrder(),
                'is_active' => $item->isActive(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $failure) {
            throw new RuntimeException('Navigation audit metadata could not be encoded.', previous: $failure);
        }

        $this->auditLogs->append(new AuditLogEntry(
            $administratorId,
            $eventType,
            'navigation_item',
            $item->id(),
            $description,
            $metadata,
            $ipAddress,
            $userAgent,
            $this->now(),
        ));
    }

    private function now(): DateTimeImmutable
    {
        $value = ($this->clock)();
        if (!$value instanceof DateTimeImmutable || $value->getOffset() !== 0) {
            throw CmsException::failure();
        }

        return $value;
    }

    private function assertAdministrator(int $administratorId): void
    {
        if ($administratorId <= 0) {
            throw CmsException::invalidInput('A valid Administrator actor is required.');
        }
    }

    /** @template T @param callable(): T $operation @return T */
    private function executeRead(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }

    /** @template T @param callable(): T $operation @return T */
    private function transact(callable $operation): mixed
    {
        $this->rejectExistingTransaction();
        $commitAttempted = false;

        try {
            $this->transaction->begin();
            $result = $operation();
            $commitAttempted = true;
            $this->transaction->commit();

            return $result;
        } catch (Throwable $failure) {
            $safeFailure = $failure instanceof CmsException ? $failure : CmsException::failure($failure);

            try {
                $active = $this->transaction->inProgress();
            } catch (Throwable $stateFailure) {
                throw CmsException::failure($stateFailure);
            }

            if ($active) {
                try {
                    $this->transaction->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw CmsException::failure($rollbackFailure);
                }
            }

            if ($commitAttempted) {
                throw CmsException::failure($safeFailure);
            }

            throw $safeFailure;
        }
    }

    private function rejectExistingTransaction(): void
    {
        try {
            if ($this->transaction->inProgress()) {
                throw CmsException::failure();
            }
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw CmsException::failure($failure);
        }
    }
}
