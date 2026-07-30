<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 activity catalogue.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE activities (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL,
                description TEXT NOT NULL,
                price DECIMAL(12,2) NOT NULL,
                is_active TINYINT(1) NOT NULL,
                is_published TINYINT(1) NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_activities PRIMARY KEY (id),
                CONSTRAINT uq_activities_slug UNIQUE (slug),
                INDEX idx_activities_public_order (
                    is_active,
                    is_published,
                    sort_order,
                    id
                ),
                INDEX idx_activities_admin_order (sort_order, id),
                CONSTRAINT chk_activities_title CHECK (
                    BINARY title = BINARY TRIM(title)
                    AND CHAR_LENGTH(title) > 0
                ),
                CONSTRAINT chk_activities_slug CHECK (
                    BINARY slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'
                ),
                CONSTRAINT chk_activities_description CHECK (
                    BINARY description = BINARY TRIM(description)
                    AND CHAR_LENGTH(description) > 0
                ),
                CONSTRAINT chk_activities_price CHECK (price >= 0),
                CONSTRAINT chk_activities_is_active CHECK (is_active IN (0, 1)),
                CONSTRAINT chk_activities_is_published CHECK (is_published IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes the independent activity catalogue.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE activities');
    }
};
