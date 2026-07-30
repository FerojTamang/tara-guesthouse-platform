<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 gallery item catalogue.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE gallery (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                gallery_category_id BIGINT UNSIGNED NULL,
                title VARCHAR(200) NOT NULL,
                caption TEXT NULL,
                file_path VARCHAR(500) NOT NULL,
                alt_text VARCHAR(500) NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL,
                is_published TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_gallery PRIMARY KEY (id),
                INDEX idx_gallery_category (gallery_category_id),
                INDEX idx_gallery_public_order (is_active, is_published, sort_order, id),
                INDEX idx_gallery_category_public_order (
                    gallery_category_id,
                    is_active,
                    is_published,
                    sort_order,
                    id
                ),
                INDEX idx_gallery_admin_order (sort_order, id),
                CONSTRAINT fk_gallery_category FOREIGN KEY (gallery_category_id)
                    REFERENCES gallery_categories (id)
                    ON UPDATE RESTRICT
                    ON DELETE SET NULL,
                CONSTRAINT chk_gallery_title CHECK (
                    BINARY title = BINARY TRIM(title)
                    AND CHAR_LENGTH(title) > 0
                ),
                CONSTRAINT chk_gallery_caption CHECK (
                    caption IS NULL
                    OR (
                        BINARY caption = BINARY TRIM(caption)
                        AND CHAR_LENGTH(caption) > 0
                    )
                ),
                CONSTRAINT chk_gallery_file_path CHECK (
                    BINARY file_path REGEXP '^[A-Za-z0-9][A-Za-z0-9._-]*(/[A-Za-z0-9][A-Za-z0-9._-]*)*$'
                ),
                CONSTRAINT chk_gallery_alt_text CHECK (
                    BINARY alt_text = BINARY TRIM(alt_text)
                    AND CHAR_LENGTH(alt_text) > 0
                ),
                CONSTRAINT chk_gallery_is_active CHECK (is_active IN (0, 1)),
                CONSTRAINT chk_gallery_is_published CHECK (is_published IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes gallery items before their categories.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE gallery');
    }
};
