<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 gallery category catalogue.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE gallery_categories (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                slug VARCHAR(200) NOT NULL,
                is_active TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_gallery_categories PRIMARY KEY (id),
                CONSTRAINT uq_gallery_categories_name UNIQUE (name),
                CONSTRAINT uq_gallery_categories_slug UNIQUE (slug),
                INDEX idx_gallery_categories_active_order (is_active, name, id),
                INDEX idx_gallery_categories_admin_order (name, id),
                CONSTRAINT chk_gallery_categories_name CHECK (
                    BINARY name = BINARY TRIM(name)
                    AND CHAR_LENGTH(name) > 0
                ),
                CONSTRAINT chk_gallery_categories_slug CHECK (
                    BINARY slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'
                ),
                CONSTRAINT chk_gallery_categories_is_active CHECK (is_active IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes the independent gallery category catalogue.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE gallery_categories');
    }
};
