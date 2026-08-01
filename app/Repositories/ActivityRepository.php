<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ActivityException;
use App\Exceptions\DatabaseException;
use App\Models\Activity;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Provides PDO persistence for Version 1 activities.
 */
final class ActivityRepository implements ActivityCatalogRepository
{
    use MapsRepositoryRows;

    private const SELECT_COLUMNS = <<<'SQL'
        SELECT
            id,
            title,
            slug,
            summary,
            description,
            featured_image_path,
            price,
            price_unit,
            duration_label,
            location,
            is_active,
            is_published,
            is_homepage_featured,
            homepage_sort_order,
            sort_order,
            created_at,
            updated_at
        FROM activities
        SQL;

    private const ORDER_BY = ' ORDER BY sort_order ASC, id ASC';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Activity
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS . ' WHERE id = :lookup LIMIT 1',
            $id,
            PDO::PARAM_INT,
        );
    }

    public function lockById(int $id): ?Activity
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS . ' WHERE id = :lookup LIMIT 1 FOR UPDATE',
            $id,
            PDO::PARAM_INT,
        );
    }

    public function findBySlug(string $slug): ?Activity
    {
        if ($slug === '') {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS . ' WHERE slug = :lookup LIMIT 1',
            $slug,
            PDO::PARAM_STR,
        );
    }

    public function findPublicById(int $id): ?Activity
    {
        if ($id <= 0) {
            return null;
        }

        return $this->lookup(
            self::SELECT_COLUMNS
                . ' WHERE id = :lookup AND is_active = 1 AND is_published = 1 LIMIT 1',
            $id,
            PDO::PARAM_INT,
        );
    }

    /**
     * @return list<Activity>
     */
    public function findAll(): array
    {
        return $this->listing(self::SELECT_COLUMNS . self::ORDER_BY);
    }

    /**
     * @return list<Activity>
     */
    public function findAllPublic(): array
    {
        return $this->listing(
            self::SELECT_COLUMNS
            . ' WHERE is_active = 1 AND is_published = 1'
            . self::ORDER_BY,
        );
    }

    public function findHomepageActivities(int $limit): array
    {
        if ($limit < 1 || $limit > 4) {
            throw ActivityException::invalidInput('Homepage activity limit is invalid.');
        }

        return $this->listing(
            self::SELECT_COLUMNS
                . ' WHERE is_active = 1 AND is_published = 1 AND is_homepage_featured = 1'
                . ' ORDER BY homepage_sort_order ASC, sort_order ASC, id ASC LIMIT :limit',
            $limit,
        );
    }

    public function create(
        string $title,
        string $slug,
        string $description,
        ?string $price,
        bool $active,
        bool $published,
        int $sortOrder,
        ?string $summary = null,
        ?string $featuredImagePath = null,
        ?string $priceUnit = null,
        ?string $durationLabel = null,
        ?string $location = null,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ): Activity {
        $this->assertExactPrice($price);

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO activities ('
                . 'title, slug, summary, description, featured_image_path, price, price_unit, '
                . 'duration_label, location, is_active, is_published, is_homepage_featured, '
                . 'homepage_sort_order, sort_order'
                . ') VALUES ('
                . ':title, :slug, :summary, :description, :featured_image_path, :price, :price_unit, '
                . ':duration_label, :location, :is_active, :is_published, :is_homepage_featured, '
                . ':homepage_sort_order, :sort_order'
                . ')',
            );
            $this->bindActivityWrite($statement, [
                'title' => $title, 'slug' => $slug, 'summary' => $summary ?? mb_substr($description, 0, 320),
                'description' => $description, 'featured_image_path' => $featuredImagePath,
                'price' => $price, 'price_unit' => $priceUnit, 'duration_label' => $durationLabel,
                'location' => $location, 'is_active' => $active ? 1 : 0,
                'is_published' => $published ? 1 : 0,
                'is_homepage_featured' => $homepageFeatured ? 1 : 0,
                'homepage_sort_order' => $homepageSortOrder, 'sort_order' => $sortOrder,
            ]);
            $statement->execute();

            $activity = $this->findById($this->generatedIdentifier());

            if (!$activity instanceof Activity) {
                throw new RuntimeException('The created activity could not be loaded.');
            }

            return $activity;
        } catch (PDOException $exception) {
            $this->throwWriteFailure($exception);
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The activity write failed.');
        }
    }

    public function update(
        int $id,
        string $title,
        string $slug,
        string $description,
        ?string $price,
        int $sortOrder,
        ?string $summary = null,
        ?string $featuredImagePath = null,
        ?string $priceUnit = null,
        ?string $durationLabel = null,
        ?string $location = null,
        bool $homepageFeatured = false,
        int $homepageSortOrder = 0,
    ): ?Activity {
        if ($id <= 0) {
            return null;
        }

        $this->assertExactPrice($price);

        try {
            $statement = $this->connection->prepare(
                'UPDATE activities SET '
                . 'title = :title, slug = :slug, summary = :summary, description = :description, '
                . 'featured_image_path = :featured_image_path, price = :price, price_unit = :price_unit, '
                . 'duration_label = :duration_label, location = :location, '
                . 'is_homepage_featured = :is_homepage_featured, '
                . 'homepage_sort_order = :homepage_sort_order, sort_order = :sort_order '
                . 'WHERE id = :id',
            );
            $this->bindActivityWrite($statement, [
                'id' => $id, 'title' => $title, 'slug' => $slug,
                'summary' => $summary ?? mb_substr($description, 0, 320), 'description' => $description,
                'featured_image_path' => $featuredImagePath, 'price' => $price,
                'price_unit' => $priceUnit, 'duration_label' => $durationLabel, 'location' => $location,
                'is_homepage_featured' => $homepageFeatured ? 1 : 0,
                'homepage_sort_order' => $homepageSortOrder, 'sort_order' => $sortOrder,
            ]);
            $statement->execute();

            return $this->findById($id);
        } catch (PDOException $exception) {
            $this->throwWriteFailure($exception);
        } catch (ActivityException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The activity write failed.');
        }
    }

    public function setActive(int $id, bool $active): ?Activity
    {
        return $this->setBooleanState($id, 'is_active', $active);
    }

    public function setPublished(int $id, bool $published): ?Activity
    {
        return $this->setBooleanState($id, 'is_published', $published);
    }

    public function deactivateAndUnpublish(int $id): ?Activity
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE activities SET is_active = 0, is_published = 0 WHERE id = :id',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->execute();

            return $this->findById($id);
        } catch (Throwable) {
            throw new DatabaseException('The activity state update failed.');
        }
    }

    private function lookup(string $sql, int|string $value, int $type): ?Activity
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->bindValue('lookup', $value, $type);
            $statement->execute();
            $row = $statement->fetch();

            if ($row === false) {
                return null;
            }

            if (!is_array($row)) {
                throw new RuntimeException('The activity record could not be mapped.');
            }

            return $this->mapActivity($row);
        } catch (Throwable) {
            throw new DatabaseException('The activity lookup failed.');
        }
    }

    /**
     * @return list<Activity>
     */
    private function listing(string $sql, ?int $limit = null): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            if ($limit !== null) {
                $statement->bindValue('limit', $limit, PDO::PARAM_INT);
            }
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The activity records could not be mapped.');
            }

            $activities = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The activity records could not be mapped.');
                }

                $activities[] = $this->mapActivity($row);
            }

            return $activities;
        } catch (Throwable) {
            throw new DatabaseException('The activity listing failed.');
        }
    }

    private function setBooleanState(int $id, string $column, bool $value): ?Activity
    {
        if ($id <= 0) {
            return null;
        }

        if (!in_array($column, ['is_active', 'is_published'], true)) {
            throw new DatabaseException('The activity state update failed.');
        }

        try {
            $statement = $this->connection->prepare(
                sprintf('UPDATE activities SET %s = :state WHERE id = :id', $column),
            );
            $statement->execute([
                'id' => $id,
                'state' => $value ? 1 : 0,
            ]);

            return $this->findById($id);
        } catch (Throwable) {
            throw new DatabaseException('The activity state update failed.');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapActivity(array $row): Activity
    {
        return new Activity(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'title'),
            $this->string($row, 'slug'),
            $this->string($row, 'description'),
            $this->nullableString($row, 'price'),
            $this->boolean($row, 'is_active'),
            $this->boolean($row, 'is_published'),
            $this->nonNegativeInteger($row, 'sort_order'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
            $this->nullableString($row, 'summary'),
            $this->nullableString($row, 'featured_image_path'),
            $this->nullableString($row, 'price_unit'),
            $this->nullableString($row, 'duration_label'),
            $this->nullableString($row, 'location'),
            $this->boolean($row, 'is_homepage_featured'),
            $this->nonNegativeInteger($row, 'homepage_sort_order'),
        );
    }

    private function assertExactPrice(?string $price): void
    {
        if ($price !== null && preg_match('/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D', $price) !== 1) {
            throw ActivityException::invalidInput(
                'Activity price must be a non-negative NPR amount with exactly two decimal places.',
            );
        }
    }

    /** @param array<string, int|string|null> $values */
    private function bindActivityWrite(\PDOStatement $statement, array $values): void
    {
        foreach ($values as $key => $value) {
            $statement->bindValue(
                $key,
                $value,
                $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR),
            );
        }
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();

        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated activity identifier is invalid.');
        }

        $integer = filter_var($identifier, FILTER_VALIDATE_INT);

        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated activity identifier is invalid.');
        }

        return $integer;
    }

    private function throwWriteFailure(PDOException $exception): never
    {
        if (
            (string) $exception->getCode() === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062
        ) {
            throw ActivityException::duplicateSlug($exception);
        }

        throw new DatabaseException('The activity write failed.');
    }
}
