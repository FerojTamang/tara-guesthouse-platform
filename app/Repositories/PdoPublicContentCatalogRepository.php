<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Models\CmsPage;
use App\Models\ContentMedia;
use App\Models\PageContentProfile;
use App\Models\PublishedContentItem;
use PDO;
use Throwable;

/** Provides one joined public query for news and notice collections. */
final class PdoPublicContentCatalogRepository implements PublicContentCatalogRepository
{
    use MapsRepositoryRows;

    private const SELECT = <<<'SQL'
        SELECT
            p.id AS page_id,
            p.title AS page_title,
            p.slug AS page_slug,
            p.content AS page_content,
            p.meta_title AS page_meta_title,
            p.meta_description AS page_meta_description,
            p.status AS page_status,
            p.published_at AS page_published_at,
            p.created_by AS page_created_by,
            p.updated_by AS page_updated_by,
            p.created_at AS page_created_at,
            p.updated_at AS page_updated_at,
            pcp.content_type AS profile_content_type,
            pcp.summary AS profile_summary,
            pcp.notice_style AS profile_notice_style,
            pcp.publish_at AS profile_publish_at,
            pcp.expires_at AS profile_expires_at,
            pcp.is_featured AS profile_is_featured,
            pcp.is_pinned AS profile_is_pinned,
            pcp.featured_media_id AS profile_featured_media_id,
            pcp.social_title AS profile_social_title,
            pcp.social_description AS profile_social_description,
            pcp.social_media_id AS profile_social_media_id,
            pcp.seo_noindex AS profile_seo_noindex,
            pcp.updated_by AS profile_updated_by,
            pcp.created_at AS profile_created_at,
            pcp.updated_at AS profile_updated_at,
            fm.id AS media_id,
            fm.storage_path AS media_storage_path,
            fm.original_name AS media_original_name,
            fm.mime_type AS media_mime_type,
            fm.file_extension AS media_file_extension,
            fm.file_size_bytes AS media_file_size_bytes,
            fm.width_pixels AS media_width_pixels,
            fm.height_pixels AS media_height_pixels,
            fm.checksum_sha256 AS media_checksum_sha256,
            fm.title AS media_title,
            fm.alt_text AS media_alt_text,
            fm.caption AS media_caption,
            fm.uploaded_by AS media_uploaded_by,
            fm.created_at AS media_created_at,
            fm.updated_at AS media_updated_at,
            fm.deleted_at AS media_deleted_at
        FROM pages p
        INNER JOIN page_content_profiles pcp ON pcp.page_id = p.id
        LEFT JOIN content_media fm
            ON fm.id = pcp.featured_media_id
            AND fm.deleted_at IS NULL
        WHERE p.status = 'published'
          AND p.published_at <= UTC_TIMESTAMP()
          AND pcp.content_type = :content_type
          AND (pcp.expires_at IS NULL OR pcp.expires_at > UTC_TIMESTAMP())
        ORDER BY
            pcp.is_pinned DESC,
            pcp.is_featured DESC,
            p.published_at DESC,
            p.id DESC
        SQL;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function findAllPublishedByType(string $contentType): array
    {
        if (!in_array($contentType, ['news', 'notice'], true)) {
            return [];
        }

        try {
            $statement = $this->connection->prepare(self::SELECT);
            $statement->execute(['content_type' => $contentType]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                throw new \RuntimeException('The public content records could not be read.');
            }

            $items = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \RuntimeException('The public content record could not be read.');
                }
                $items[] = $this->map($row);
            }

            return $items;
        } catch (Throwable) {
            throw new DatabaseException('The public content listing failed.');
        }
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): PublishedContentItem
    {
        $page = new CmsPage(
            $this->positiveInteger($row, 'page_id'),
            $this->string($row, 'page_title'),
            $this->string($row, 'page_slug'),
            $this->string($row, 'page_content'),
            $this->nullableString($row, 'page_meta_title'),
            $this->nullableString($row, 'page_meta_description'),
            $this->string($row, 'page_status'),
            $this->nullableDateTime($row, 'page_published_at'),
            $this->positiveInteger($row, 'page_created_by'),
            $this->positiveInteger($row, 'page_updated_by'),
            $this->dateTime($row, 'page_created_at'),
            $this->dateTime($row, 'page_updated_at'),
        );

        $featuredMediaId = ($row['profile_featured_media_id'] ?? null) === null
            ? null
            : $this->positiveInteger($row, 'profile_featured_media_id');
        $socialMediaId = ($row['profile_social_media_id'] ?? null) === null
            ? null
            : $this->positiveInteger($row, 'profile_social_media_id');
        $profile = new PageContentProfile(
            $page->id(),
            $this->string($row, 'profile_content_type'),
            $this->nullableString($row, 'profile_summary'),
            $this->nullableString($row, 'profile_notice_style'),
            $this->nullableDateTime($row, 'profile_publish_at'),
            $this->nullableDateTime($row, 'profile_expires_at'),
            $this->boolean($row, 'profile_is_featured'),
            $this->boolean($row, 'profile_is_pinned'),
            $featuredMediaId,
            $this->nullableString($row, 'profile_social_title'),
            $this->nullableString($row, 'profile_social_description'),
            $socialMediaId,
            $this->boolean($row, 'profile_seo_noindex'),
            $this->positiveInteger($row, 'profile_updated_by'),
            $this->dateTime($row, 'profile_created_at'),
            $this->dateTime($row, 'profile_updated_at'),
        );

        $media = null;
        if (($row['media_id'] ?? null) !== null) {
            $media = new ContentMedia(
                $this->positiveInteger($row, 'media_id'),
                $this->string($row, 'media_storage_path'),
                $this->string($row, 'media_original_name'),
                $this->string($row, 'media_mime_type'),
                $this->string($row, 'media_file_extension'),
                $this->positiveInteger($row, 'media_file_size_bytes'),
                $this->positiveInteger($row, 'media_width_pixels'),
                $this->positiveInteger($row, 'media_height_pixels'),
                $this->string($row, 'media_checksum_sha256'),
                $this->nullableString($row, 'media_title'),
                $this->string($row, 'media_alt_text'),
                $this->nullableString($row, 'media_caption'),
                $this->positiveInteger($row, 'media_uploaded_by'),
                $this->dateTime($row, 'media_created_at'),
                $this->dateTime($row, 'media_updated_at'),
                $this->nullableDateTime($row, 'media_deleted_at'),
                0,
            );
        }

        return new PublishedContentItem($page, $profile, $media);
    }
}
