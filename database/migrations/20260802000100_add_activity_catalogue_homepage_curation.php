<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE activities
                ADD COLUMN summary VARCHAR(320) NULL AFTER slug,
                ADD COLUMN featured_image_path VARCHAR(500) NULL AFTER description,
                MODIFY COLUMN price DECIMAL(12,2) NULL,
                ADD COLUMN price_unit VARCHAR(40) NULL AFTER price,
                ADD COLUMN duration_label VARCHAR(80) NULL AFTER price_unit,
                ADD COLUMN location VARCHAR(120) NULL AFTER duration_label,
                ADD COLUMN is_homepage_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published,
                ADD COLUMN homepage_sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_homepage_featured,
                ADD CONSTRAINT chk_activities_summary CHECK (
                    summary IS NULL OR (
                        BINARY summary = BINARY TRIM(summary)
                        AND CHAR_LENGTH(summary) > 0
                        AND CHAR_LENGTH(summary) <= 320
                    )
                ),
                ADD CONSTRAINT chk_activities_featured_image_path CHECK (
                    featured_image_path IS NULL
                    OR BINARY featured_image_path REGEXP '^gallery/[a-f0-9]{64}\\.(jpg|png|webp)$'
                ),
                ADD CONSTRAINT chk_activities_price_unit CHECK (
                    price_unit IS NULL OR (
                        BINARY price_unit = BINARY TRIM(price_unit)
                        AND CHAR_LENGTH(price_unit) > 0
                    )
                ),
                ADD CONSTRAINT chk_activities_duration_label CHECK (
                    duration_label IS NULL OR (
                        BINARY duration_label = BINARY TRIM(duration_label)
                        AND CHAR_LENGTH(duration_label) > 0
                    )
                ),
                ADD CONSTRAINT chk_activities_location CHECK (
                    location IS NULL OR (
                        BINARY location = BINARY TRIM(location)
                        AND CHAR_LENGTH(location) > 0
                    )
                ),
                ADD CONSTRAINT chk_activities_homepage_featured CHECK (
                    is_homepage_featured IN (0, 1)
                ),
                ADD INDEX idx_activities_homepage_order (
                    is_active,
                    is_published,
                    is_homepage_featured,
                    homepage_sort_order,
                    sort_order,
                    id
                )
            SQL);

        $connection->exec(<<<'SQL'
            ALTER TABLE gallery
                ADD COLUMN is_homepage_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published,
                ADD COLUMN homepage_sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_homepage_featured,
                ADD CONSTRAINT chk_gallery_homepage_featured CHECK (
                    is_homepage_featured IN (0, 1)
                ),
                ADD INDEX idx_gallery_homepage_order (
                    is_active,
                    is_published,
                    is_homepage_featured,
                    homepage_sort_order,
                    sort_order,
                    id
                )
            SQL);

        $connection->exec(<<<'SQL'
            UPDATE activities
            SET summary = LEFT(TRIM(description), 320),
                is_homepage_featured = CASE WHEN is_published = 1 THEN 1 ELSE 0 END,
                homepage_sort_order = sort_order
            SQL);
        $connection->exec(<<<'SQL'
            UPDATE gallery
            SET is_homepage_featured = CASE WHEN is_published = 1 THEN 1 ELSE 0 END,
                homepage_sort_order = sort_order
            SQL);
    }

    public function down(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            UPDATE activities SET price = 0.00 WHERE price IS NULL
            SQL);
        $connection->exec(<<<'SQL'
            ALTER TABLE gallery
                DROP INDEX idx_gallery_homepage_order,
                DROP CONSTRAINT chk_gallery_homepage_featured,
                DROP COLUMN homepage_sort_order,
                DROP COLUMN is_homepage_featured
            SQL);
        $connection->exec(<<<'SQL'
            ALTER TABLE activities
                DROP INDEX idx_activities_homepage_order,
                DROP CONSTRAINT chk_activities_homepage_featured,
                DROP CONSTRAINT chk_activities_location,
                DROP CONSTRAINT chk_activities_duration_label,
                DROP CONSTRAINT chk_activities_price_unit,
                DROP CONSTRAINT chk_activities_featured_image_path,
                DROP CONSTRAINT chk_activities_summary,
                DROP COLUMN homepage_sort_order,
                DROP COLUMN is_homepage_featured,
                DROP COLUMN location,
                DROP COLUMN duration_label,
                DROP COLUMN price_unit,
                MODIFY COLUMN price DECIMAL(12,2) NOT NULL,
                DROP COLUMN featured_image_path,
                DROP COLUMN summary
            SQL);
    }
};
