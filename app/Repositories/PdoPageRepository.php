<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\CmsException;
use App\Exceptions\DatabaseException;
use App\Models\CmsPage;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/** Provides PDO persistence for structured CMS pages. */
final class PdoPageRepository implements PageRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT id, title, slug, content, meta_title, meta_description, status, published_at,
               created_by, updated_by, created_at, updated_at
        FROM pages
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?CmsPage
    {
        return $id <= 0 ? null : $this->lookup(self::SELECT . ' WHERE id = :lookup LIMIT 1', $id, PDO::PARAM_INT);
    }

    public function findBySlug(string $slug): ?CmsPage
    {
        return $slug === '' ? null : $this->lookup(self::SELECT . ' WHERE slug = :lookup LIMIT 1', $slug, PDO::PARAM_STR);
    }

    public function findPublishedBySlug(string $slug): ?CmsPage
    {
        return $slug === '' ? null : $this->lookup(
            self::SELECT . " WHERE slug = :lookup AND status = 'published' LIMIT 1",
            $slug,
            PDO::PARAM_STR,
        );
    }

    public function findAll(): array
    {
        return $this->listing(self::SELECT . ' ORDER BY title ASC, id ASC');
    }

    public function findAllPublished(): array
    {
        return $this->listing(
            self::SELECT . " WHERE status = 'published' ORDER BY title ASC, id ASC",
        );
    }

    public function createDraft(
        string $title,
        string $slug,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        int $actorId,
    ): CmsPage {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO pages (title, slug, content, meta_title, meta_description, status, published_at, created_by, updated_by) '
                . "VALUES (:title, :slug, :content, :meta_title, :meta_description, 'draft', NULL, :created_by, :updated_by)",
            );
            $statement->execute([
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $page = $this->findById($this->generatedIdentifier());
            if (!$page instanceof CmsPage) {
                throw new RuntimeException('The created page could not be loaded.');
            }
            return $page;
        } catch (PDOException $exception) {
            $this->throwWriteFailure($exception);
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The page write failed.');
        }
    }

    public function updateContent(
        int $id,
        string $title,
        string $slug,
        string $content,
        ?string $metaTitle,
        ?string $metaDescription,
        int $actorId,
    ): ?CmsPage {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE pages SET title = :title, slug = :slug, content = :content, '
                . 'meta_title = :meta_title, meta_description = :meta_description, updated_by = :updated_by '
                . 'WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'updated_by' => $actorId,
            ]);

            return $this->findById($id);
        } catch (PDOException $exception) {
            $this->throwWriteFailure($exception);
        } catch (CmsException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new DatabaseException('The page write failed.');
        }
    }

    public function transitionStatus(
        int $id,
        string $expectedStatus,
        string $newStatus,
        ?DateTimeImmutable $publishedAt,
        int $actorId,
    ): ?CmsPage {
        if ($id <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(
                'UPDATE pages SET status = :new_status, published_at = :published_at, updated_by = :updated_by '
                . 'WHERE id = :id AND status = :expected_status',
            );
            $statement->bindValue('id', $id, PDO::PARAM_INT);
            $statement->bindValue('expected_status', $expectedStatus, PDO::PARAM_STR);
            $statement->bindValue('new_status', $newStatus, PDO::PARAM_STR);
            $statement->bindValue('updated_by', $actorId, PDO::PARAM_INT);
            $statement->bindValue(
                'published_at',
                $publishedAt?->format('Y-m-d H:i:s'),
                $publishedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR,
            );
            $statement->execute();

            if ($statement->rowCount() !== 1) {
                return null;
            }

            return $this->findById($id);
        } catch (Throwable) {
            throw new DatabaseException('The page status update failed.');
        }
    }

    /** @return list<CmsPage> */
    private function listing(string $sql): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute();
            $rows = $statement->fetchAll();

            if (!is_array($rows)) {
                throw new RuntimeException('The page records could not be mapped.');
            }

            $pages = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('The page records could not be mapped.');
                }
                $pages[] = $this->map($row);
            }

            return $pages;
        } catch (Throwable) {
            throw new DatabaseException('The page listing failed.');
        }
    }

    private function lookup(string $sql, int|string $value, int $type): ?CmsPage
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
                throw new RuntimeException('The page record could not be mapped.');
            }
            return $this->map($row);
        } catch (Throwable) {
            throw new DatabaseException('The page lookup failed.');
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): CmsPage
    {
        return new CmsPage(
            $this->positiveInteger($row, 'id'),
            $this->string($row, 'title'),
            $this->string($row, 'slug'),
            $this->string($row, 'content'),
            $this->nullableString($row, 'meta_title'),
            $this->nullableString($row, 'meta_description'),
            $this->string($row, 'status'),
            $this->nullableDateTime($row, 'published_at'),
            $this->positiveInteger($row, 'created_by'),
            $this->positiveInteger($row, 'updated_by'),
            $this->dateTime($row, 'created_at'),
            $this->dateTime($row, 'updated_at'),
        );
    }

    private function generatedIdentifier(): int
    {
        $identifier = $this->connection->lastInsertId();
        if (preg_match('/^[1-9][0-9]*$/D', $identifier) !== 1) {
            throw new RuntimeException('The generated page identifier is invalid.');
        }
        $integer = filter_var($identifier, FILTER_VALIDATE_INT);
        if (!is_int($integer) || $integer <= 0) {
            throw new RuntimeException('The generated page identifier is invalid.');
        }
        return $integer;
    }

    private function throwWriteFailure(PDOException $exception): never
    {
        $message = strtolower($exception->getMessage());
        if ($exception->getCode() === '23000' && str_contains($message, 'uq_pages_slug')) {
            throw CmsException::duplicatePageSlug($exception);
        }
        throw new DatabaseException('The page write failed.');
    }
}
