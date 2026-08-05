<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\ContentMediaException;
use App\Models\PageContentProfile;
use DateTimeImmutable;
use PDO;
use Throwable;

/** Provides PDO persistence for page metadata and block-media usage. */
final class PdoPageContentProfileRepository implements PageContentProfileRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT page_id, content_type, summary, notice_style, publish_at, expires_at,
               is_featured, is_pinned, featured_media_id, social_title, social_description,
               social_media_id, seo_noindex, updated_by, created_at, updated_at
        FROM page_content_profiles
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByPageId(int $pageId): ?PageContentProfile
    {
        if ($pageId <= 0) {
            return null;
        }

        try {
            $statement = $this->connection->prepare(self::SELECT . ' WHERE page_id = :page_id LIMIT 1');
            $statement->execute(['page_id' => $pageId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $this->map($row) : null;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function upsertCore(int $pageId, string $contentType, ?string $summary, int $updatedBy): PageContentProfile
    {
        try {
            $statement = $this->connection->prepare(<<<'SQL'
                INSERT INTO page_content_profiles (
                    page_id, content_type, summary, notice_style, publish_at, expires_at,
                    is_featured, is_pinned, featured_media_id, social_title, social_description,
                    social_media_id, seo_noindex, updated_by
                ) VALUES (
                    :page_id, :content_type, :summary, NULL, NULL, NULL,
                    0, 0, NULL, NULL, NULL, NULL, 0, :updated_by
                )
                ON DUPLICATE KEY UPDATE
                    content_type = VALUES(content_type),
                    summary = VALUES(summary),
                    notice_style = CASE WHEN VALUES(content_type) = 'notice' THEN notice_style ELSE NULL END,
                    expires_at = CASE WHEN VALUES(content_type) = 'notice' THEN expires_at ELSE NULL END,
                    updated_by = VALUES(updated_by)
                SQL);
            $statement->execute([
                'page_id' => $pageId,
                'content_type' => $contentType,
                'summary' => $summary,
                'updated_by' => $updatedBy,
            ]);

            return $this->requiredProfile($pageId);
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function updatePublishing(
        int $pageId,
        ?string $noticeStyle,
        ?DateTimeImmutable $publishAt,
        ?DateTimeImmutable $expiresAt,
        bool $featured,
        bool $pinned,
        ?int $featuredMediaId,
        ?string $socialTitle,
        ?string $socialDescription,
        ?int $socialMediaId,
        bool $seoNoindex,
        int $updatedBy,
    ): PageContentProfile {
        try {
            $statement = $this->connection->prepare(<<<'SQL'
                UPDATE page_content_profiles
                SET notice_style = :notice_style,
                    publish_at = :publish_at,
                    expires_at = :expires_at,
                    is_featured = :is_featured,
                    is_pinned = :is_pinned,
                    featured_media_id = :featured_media_id,
                    social_title = :social_title,
                    social_description = :social_description,
                    social_media_id = :social_media_id,
                    seo_noindex = :seo_noindex,
                    updated_by = :updated_by
                WHERE page_id = :page_id
                SQL);
            $statement->bindValue('page_id', $pageId, PDO::PARAM_INT);
            $statement->bindValue('notice_style', $noticeStyle, $noticeStyle === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('publish_at', $publishAt?->format('Y-m-d H:i:s'), $publishAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('expires_at', $expiresAt?->format('Y-m-d H:i:s'), $expiresAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('is_featured', $featured ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('is_pinned', $pinned ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('featured_media_id', $featuredMediaId, $featuredMediaId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('social_title', $socialTitle, $socialTitle === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('social_description', $socialDescription, $socialDescription === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue('social_media_id', $socialMediaId, $socialMediaId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue('seo_noindex', $seoNoindex ? 1 : 0, PDO::PARAM_INT);
            $statement->bindValue('updated_by', $updatedBy, PDO::PARAM_INT);
            $statement->execute();

            if ($statement->rowCount() > 1) {
                throw ContentMediaException::failure();
            }

            return $this->requiredProfile($pageId);
        } catch (ContentMediaException $exception) {
            throw $exception;
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function updatePublication(int $pageId, DateTimeImmutable $publishAt, int $updatedBy): void
    {
        try {
            $statement = $this->connection->prepare(
                'UPDATE page_content_profiles SET publish_at = :publish_at, updated_by = :updated_by WHERE page_id = :page_id',
            );
            $statement->bindValue('page_id', $pageId, PDO::PARAM_INT);
            $statement->bindValue('updated_by', $updatedBy, PDO::PARAM_INT);
            $statement->bindValue(
                'publish_at',
                $publishAt->format('Y-m-d H:i:s'),
                PDO::PARAM_STR,
            );
            $statement->execute();
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    public function replaceBlockMedia(int $pageId, array $usages): void
    {
        try {
            $delete = $this->connection->prepare('DELETE FROM page_block_media WHERE page_id = :page_id');
            $delete->execute(['page_id' => $pageId]);

            if ($usages === []) {
                return;
            }

            $insert = $this->connection->prepare(
                'INSERT INTO page_block_media (page_id, media_id, block_key, sort_order) '
                . 'VALUES (:page_id, :media_id, :block_key, :sort_order)',
            );

            foreach ($usages as $usage) {
                $insert->execute([
                    'page_id' => $pageId,
                    'media_id' => $usage['media_id'],
                    'block_key' => $usage['block_key'],
                    'sort_order' => $usage['sort_order'],
                ]);
            }
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }

    private function requiredProfile(int $pageId): PageContentProfile
    {
        $profile = $this->findByPageId($pageId);
        if (!$profile instanceof PageContentProfile) {
            throw ContentMediaException::failure();
        }
        return $profile;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): PageContentProfile
    {
        try {
            $featuredMediaId = ($row['featured_media_id'] ?? null) === null
                ? null
                : $this->positiveInteger($row, 'featured_media_id');
            $socialMediaId = ($row['social_media_id'] ?? null) === null
                ? null
                : $this->positiveInteger($row, 'social_media_id');

            return new PageContentProfile(
                $this->positiveInteger($row, 'page_id'),
                $this->string($row, 'content_type'),
                $this->nullableString($row, 'summary'),
                $this->nullableString($row, 'notice_style'),
                $this->nullableDateTime($row, 'publish_at'),
                $this->nullableDateTime($row, 'expires_at'),
                $this->boolean($row, 'is_featured'),
                $this->boolean($row, 'is_pinned'),
                $featuredMediaId,
                $this->nullableString($row, 'social_title'),
                $this->nullableString($row, 'social_description'),
                $socialMediaId,
                $this->boolean($row, 'seo_noindex'),
                $this->positiveInteger($row, 'updated_by'),
                $this->dateTime($row, 'created_at'),
                $this->dateTime($row, 'updated_at'),
            );
        } catch (Throwable $failure) {
            throw ContentMediaException::failure($failure);
        }
    }
}
