<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\NavigationItem;
use PDO;
use RuntimeException;
use Throwable;

/** Provides strict PDO persistence for public navigation configuration. */
final class PdoNavigationRepository implements NavigationRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT id, location, label, link_type, route_key, page_id, parent_id,
               sort_order, is_active, created_by, updated_by, created_at, updated_at
        FROM navigation_items
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?NavigationItem
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(self::SELECT . ' WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    public function findAll(): array
    {
        return $this->listing(
            self::SELECT . ' ORDER BY location ASC, parent_id IS NOT NULL ASC, parent_id ASC, sort_order ASC, id ASC',
        );
    }

    public function findAllActive(): array
    {
        return $this->listing(
            self::SELECT . ' WHERE is_active = 1 ORDER BY location ASC, parent_id IS NOT NULL ASC, parent_id ASC, sort_order ASC, id ASC',
        );
    }

    public function create(
        string $location,
        string $label,
        string $linkType,
        ?string $routeKey,
        ?int $pageId,
        ?int $parentId,
        int $sortOrder,
        int $actorId,
    ): NavigationItem {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO navigation_items '
                . '(location, label, link_type, route_key, page_id, parent_id, sort_order, is_active, created_by, updated_by) '
                . 'VALUES (:location, :label, :link_type, :route_key, :page_id, :parent_id, :sort_order, 1, :created_by, :updated_by)',
            );
            $this->bindMutation(
                $statement,
                $location,
                $label,
                $linkType,
                $routeKey,
                $pageId,
                $parentId,
                $sortOrder,
                $actorId,
            );
            $statement->execute();

            $item = $this->findById($this->generatedIdentifier());
            if (!$item instanceof NavigationItem) {
                throw new RuntimeException('The created navigation item could not be loaded.');
            }

            return $item;
        } catch (Throwable $failure) {
            throw new DatabaseException('The navigation item write failed.', previous: $failure);
        }
    }

    public function update(
        int $id,
        string $location,
        string $label,
        string $linkType,
        ?string $routeKey,
        ?int $pageId,
        ?int $parentId,
        int $sortOrder,
        int $actorId,
    ): ?NavigationItem {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE navigation_items SET location = :location, label = :label, link_type = :link_type, '
                . 'route_key = :route_key, page_id = :page_id, parent_id = :parent_id, '
                . 'sort_order = :sort_order, updated_by = :updated_by WHERE id = :id',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $this->bindMutation(
                $statement,
                $location,
                $label,
                $linkType,
                $routeKey,
                $pageId,
                $parentId,
                $sortOrder,
                $actorId,
                false,
            );
            $statement->execute();

            return $this->findById($id);
        } catch (Throwable $failure) {
            throw new DatabaseException('The navigation item write failed.', previous: $failure);
        }
    }

    public function setActive(int $id, bool $active, int $actorId): ?NavigationItem
    {
        if ($id <= 0 || $actorId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE navigation_items SET is_active = :is_active, updated_by = :updated_by WHERE id = :id',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->bindValue('is_active', $active ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('updated_by', $actorId, PDO::PARAM_INT);
            $statement->execute();

            return $this->findById($id);
        } catch (Throwable $failure) {
            throw new DatabaseException('The navigation item state update failed.', previous: $failure);
        }
    }

    public function setSortOrder(int $id, int $sortOrder, int $actorId): bool
    {
        if ($id <= 0 || $sortOrder <= 0 || $actorId <= 0) {
            return false;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE navigation_items SET sort_order = :sort_order, updated_by = :updated_by WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'sort_order' => $sortOrder,
                'updated_by' => $actorId,
            ]);

            return $statement->rowCount() === 1;
        } catch (Throwable $failure) {
            throw new DatabaseException('The navigation order update failed.', previous: $failure);
        }
    }

    /** @return list<NavigationItem> */
    private function listing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The navigation records could not be mapped.');
            }

            $items = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The navigation records could not be mapped.');
                }
                $items[] = $this->map($row);
            }

            return $items;
        } catch (Throwable $failure) {
            throw new DatabaseException('The navigation listing failed.', previous: $failure);
        }
    }

    /** @param array<string, int> $parameters */
    private function lookup(string $sql, array $parameters): ?NavigationItem
    {
        try {
            $statement = $this->connection->prepare($sql);
            foreach ($parameters as $name => $value) {
                $statement->bindValue($name, $value, PDO::PARAM_INT);
            }
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }
            if (!is_array($row)) {
                throw new RuntimeException('The navigation record could not be mapped.');
            }

            return $this->map($row);
        } catch (Throwable $failure) {
            throw new DatabaseException('The navigation lookup failed.', previous: $failure);
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): NavigationItem
    {
        return new NavigationItem(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'location'),
            $this->string($row, 'label'),
            $this->string($row, 'link_type'),
            $this->nullableString($row, 'route_key'),
            $this->nullablePositiveInteger($row, 'page_id'),
            $this->nullablePositiveInteger($row, 'parent_id'),
            $this->positiveInteger($row, 'sort_order'),
            $this->boolean($row, 'is_active'),
            $this->nullablePositiveInteger($row, 'created_by'),
            $this->nullablePositiveInteger($row, 'updated_by'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    private function bindMutation(
        \PDOStatement $statement,
        string $location,
        string $label,
        string $linkType,
        ?string $routeKey,
        ?int $pageId,
        ?int $parentId,
        int $sortOrder,
        int $actorId,
        bool $bindCreated = true,
    ): void {
        $statement->bindValue('location', $location, PDO::PARAM_STR);
        $statement->bindValue('label', $label, PDO::PARAM_STR);
        $statement->bindValue('link_type', $linkType, PDO::PARAM_STR);
        $statement->bindValue('route_key', $routeKey, $routeKey === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue('page_id', $pageId, $pageId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue('parent_id', $parentId, $parentId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue('sort_order', $sortOrder, PDO::PARAM_INT);
        if ($bindCreated) {
            $statement->bindValue('created_by', $actorId, PDO::PARAM_INT);
        }
        $statement->bindValue('updated_by', $actorId, PDO::PARAM_INT);
    }

    /** @param array<string, mixed> $row */
    private function nullablePositiveInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->positiveInteger($row, $key);
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();
        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated navigation identifier is invalid.');
        }
        $integer = filter_var($identifier, FILTER_VALIDATE_INT);
        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated navigation identifier is invalid.');
        }
        return $integer;
    }
}
