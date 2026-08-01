<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates the Version 1 structured CMS pages table. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE pages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL,
                content LONGTEXT NOT NULL,
                meta_title VARCHAR(255) NULL,
                meta_description VARCHAR(500) NULL,
                status VARCHAR(20) NOT NULL,
                published_at DATETIME NULL,
                created_by BIGINT UNSIGNED NOT NULL,
                updated_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_pages PRIMARY KEY (id),
                CONSTRAINT uq_pages_slug UNIQUE (slug),
                INDEX idx_pages_public_slug (status, slug, id),
                INDEX idx_pages_admin_order (status, title, id),
                INDEX idx_pages_created_by (created_by),
                INDEX idx_pages_updated_by (updated_by),
                CONSTRAINT fk_pages_created_by FOREIGN KEY (created_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_pages_updated_by FOREIGN KEY (updated_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_pages_title CHECK (
                    BINARY title = BINARY TRIM(title)
                    AND CHAR_LENGTH(title) > 0
                ),
                CONSTRAINT chk_pages_slug CHECK (
                    BINARY slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'
                ),
                CONSTRAINT chk_pages_content CHECK (
                    BINARY content = BINARY TRIM(content)
                    AND CHAR_LENGTH(content) > 0
                ),
                CONSTRAINT chk_pages_meta_title CHECK (
                    meta_title IS NULL OR (
                        BINARY meta_title = BINARY TRIM(meta_title)
                        AND CHAR_LENGTH(meta_title) > 0
                    )
                ),
                CONSTRAINT chk_pages_meta_description CHECK (
                    meta_description IS NULL OR (
                        BINARY meta_description = BINARY TRIM(meta_description)
                        AND CHAR_LENGTH(meta_description) > 0
                    )
                ),
                CONSTRAINT chk_pages_status CHECK (status IN ('draft', 'published', 'archived')),
                CONSTRAINT chk_pages_published_at CHECK (
                    status <> 'published' OR published_at IS NOT NULL
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** Removes structured CMS pages. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE pages');
    }
};
