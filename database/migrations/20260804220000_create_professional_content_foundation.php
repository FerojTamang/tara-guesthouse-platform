<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates reusable content media, page metadata, and block-media references. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE content_media (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                storage_path VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(50) NOT NULL,
                file_extension VARCHAR(10) NOT NULL,
                file_size_bytes INT UNSIGNED NOT NULL,
                width_pixels INT UNSIGNED NOT NULL,
                height_pixels INT UNSIGNED NOT NULL,
                checksum_sha256 CHAR(64) NOT NULL,
                title VARCHAR(200) NULL,
                alt_text VARCHAR(255) NOT NULL,
                caption VARCHAR(500) NULL,
                uploaded_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                active_checksum_sha256 CHAR(64) NULL,
                CONSTRAINT pk_content_media PRIMARY KEY (id),
                CONSTRAINT uq_content_media_storage_path UNIQUE (storage_path),
                CONSTRAINT uq_content_media_active_checksum UNIQUE (active_checksum_sha256),
                INDEX idx_content_media_active_created (deleted_at, created_at, id),
                INDEX idx_content_media_checksum (checksum_sha256, deleted_at, id),
                INDEX idx_content_media_uploaded_by (uploaded_by),
                CONSTRAINT fk_content_media_uploaded_by FOREIGN KEY (uploaded_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_content_media_storage_path CHECK (
                    BINARY storage_path REGEXP '^uploads/content/[a-f0-9]{64}\\.(jpg|png|webp)$'
                ),
                CONSTRAINT chk_content_media_original_name CHECK (
                    BINARY original_name = BINARY TRIM(original_name)
                    AND CHAR_LENGTH(original_name) > 0
                ),
                CONSTRAINT chk_content_media_mime_extension CHECK (
                    (mime_type = 'image/jpeg' AND file_extension = 'jpg')
                    OR (mime_type = 'image/png' AND file_extension = 'png')
                    OR (mime_type = 'image/webp' AND file_extension = 'webp')
                ),
                CONSTRAINT chk_content_media_dimensions CHECK (
                    file_size_bytes > 0 AND width_pixels > 0 AND height_pixels > 0
                ),
                CONSTRAINT chk_content_media_checksum CHECK (
                    BINARY checksum_sha256 REGEXP '^[a-f0-9]{64}$'
                ),
                CONSTRAINT chk_content_media_active_checksum CHECK (
                    (
                        deleted_at IS NULL
                        AND active_checksum_sha256 IS NOT NULL
                        AND active_checksum_sha256 = checksum_sha256
                    )
                    OR (
                        deleted_at IS NOT NULL
                        AND active_checksum_sha256 IS NULL
                    )
                ),
                CONSTRAINT chk_content_media_title CHECK (
                    title IS NULL OR (
                        BINARY title = BINARY TRIM(title)
                        AND CHAR_LENGTH(title) > 0
                    )
                ),
                CONSTRAINT chk_content_media_alt_text CHECK (
                    BINARY alt_text = BINARY TRIM(alt_text)
                    AND CHAR_LENGTH(alt_text) > 0
                ),
                CONSTRAINT chk_content_media_caption CHECK (
                    caption IS NULL OR (
                        BINARY caption = BINARY TRIM(caption)
                        AND CHAR_LENGTH(caption) > 0
                    )
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $connection->exec(<<<'SQL'
            CREATE TABLE page_content_profiles (
                page_id BIGINT UNSIGNED NOT NULL,
                content_type VARCHAR(20) NOT NULL,
                summary VARCHAR(500) NULL,
                notice_style VARCHAR(20) NULL,
                publish_at DATETIME NULL,
                expires_at DATETIME NULL,
                is_featured TINYINT(1) NOT NULL DEFAULT 0,
                featured_media_id BIGINT UNSIGNED NULL,
                social_title VARCHAR(255) NULL,
                social_description VARCHAR(500) NULL,
                social_media_id BIGINT UNSIGNED NULL,
                seo_noindex TINYINT(1) NOT NULL DEFAULT 0,
                updated_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_page_content_profiles PRIMARY KEY (page_id),
                INDEX idx_page_content_publication (
                    content_type, is_featured, publish_at, expires_at, page_id
                ),
                INDEX idx_page_content_featured_media (featured_media_id),
                INDEX idx_page_content_social_media (social_media_id),
                INDEX idx_page_content_updated_by (updated_by),
                CONSTRAINT fk_page_content_profile_page FOREIGN KEY (page_id)
                    REFERENCES pages (id) ON UPDATE RESTRICT ON DELETE CASCADE,
                CONSTRAINT fk_page_content_profile_featured_media FOREIGN KEY (featured_media_id)
                    REFERENCES content_media (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_page_content_profile_social_media FOREIGN KEY (social_media_id)
                    REFERENCES content_media (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_page_content_profile_updated_by FOREIGN KEY (updated_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_page_content_type CHECK (
                    content_type IN ('page', 'news', 'notice', 'policy')
                ),
                CONSTRAINT chk_page_content_summary CHECK (
                    summary IS NULL OR (
                        BINARY summary = BINARY TRIM(summary)
                        AND CHAR_LENGTH(summary) > 0
                    )
                ),
                CONSTRAINT chk_page_content_notice_style CHECK (
                    notice_style IS NULL OR notice_style IN ('information', 'important', 'warning', 'event')
                ),
                CONSTRAINT chk_page_content_notice_scope CHECK (
                    content_type = 'notice' OR notice_style IS NULL
                ),
                CONSTRAINT chk_page_content_expiry CHECK (
                    expires_at IS NULL OR publish_at IS NULL OR expires_at > publish_at
                ),
                CONSTRAINT chk_page_content_featured CHECK (is_featured IN (0, 1)),
                CONSTRAINT chk_page_content_noindex CHECK (seo_noindex IN (0, 1)),
                CONSTRAINT chk_page_content_social_title CHECK (
                    social_title IS NULL OR (
                        BINARY social_title = BINARY TRIM(social_title)
                        AND CHAR_LENGTH(social_title) > 0
                    )
                ),
                CONSTRAINT chk_page_content_social_description CHECK (
                    social_description IS NULL OR (
                        BINARY social_description = BINARY TRIM(social_description)
                        AND CHAR_LENGTH(social_description) > 0
                    )
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $connection->exec(<<<'SQL'
            CREATE TABLE page_block_media (
                page_id BIGINT UNSIGNED NOT NULL,
                media_id BIGINT UNSIGNED NOT NULL,
                block_key VARCHAR(64) NOT NULL,
                sort_order SMALLINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_page_block_media PRIMARY KEY (page_id, block_key),
                INDEX idx_page_block_media_asset (media_id, page_id),
                CONSTRAINT fk_page_block_media_page FOREIGN KEY (page_id)
                    REFERENCES pages (id) ON UPDATE RESTRICT ON DELETE CASCADE,
                CONSTRAINT fk_page_block_media_asset FOREIGN KEY (media_id)
                    REFERENCES content_media (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_page_block_media_key CHECK (
                    BINARY block_key REGEXP '^block-[1-9][0-9]{0,4}$'
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $connection->exec(<<<'SQL'
            INSERT INTO page_content_profiles (
                page_id, content_type, summary, notice_style, publish_at, expires_at,
                is_featured, featured_media_id, social_title, social_description,
                social_media_id, seo_noindex, updated_by
            )
            SELECT
                id, 'page', NULL, NULL, published_at, NULL,
                0, NULL, NULL, NULL, NULL, 0, updated_by
            FROM pages
            SQL);
    }

    /** Removes the professional content foundation in reverse dependency order. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE page_block_media');
        $connection->exec('DROP TABLE page_content_profiles');
        $connection->exec('DROP TABLE content_media');
    }
};
