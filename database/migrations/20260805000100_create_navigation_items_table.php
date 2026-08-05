<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates ordered Administrator-managed public navigation items. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE navigation_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                location VARCHAR(20) NOT NULL,
                label VARCHAR(80) NOT NULL,
                link_type VARCHAR(20) NOT NULL,
                route_key VARCHAR(40) NULL,
                page_id BIGINT UNSIGNED NULL,
                parent_id BIGINT UNSIGNED NULL,
                sort_order INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_navigation_items PRIMARY KEY (id),
                INDEX idx_navigation_items_admin (location, parent_id, sort_order, id),
                INDEX idx_navigation_items_public (location, is_active, parent_id, sort_order, id),
                INDEX idx_navigation_items_page (page_id, is_active, id),
                INDEX idx_navigation_items_parent (parent_id, sort_order, id),
                INDEX idx_navigation_items_created_by (created_by),
                INDEX idx_navigation_items_updated_by (updated_by),
                CONSTRAINT fk_navigation_items_page FOREIGN KEY (page_id)
                    REFERENCES pages (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_navigation_items_parent FOREIGN KEY (parent_id)
                    REFERENCES navigation_items (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_navigation_items_created_by FOREIGN KEY (created_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_navigation_items_updated_by FOREIGN KEY (updated_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_navigation_items_location CHECK (
                    location IN ('main', 'footer')
                ),
                CONSTRAINT chk_navigation_items_label CHECK (
                    BINARY label = BINARY TRIM(label)
                    AND CHAR_LENGTH(label) > 0
                ),
                CONSTRAINT chk_navigation_items_link_type CHECK (
                    link_type IN ('route', 'page', 'group')
                ),
                CONSTRAINT chk_navigation_items_route_key CHECK (
                    route_key IS NULL OR route_key IN (
                        'home', 'rooms', 'activities', 'gallery',
                        'reviews', 'availability', 'contact'
                    )
                ),
                CONSTRAINT chk_navigation_items_target CHECK (
                    (link_type = 'route' AND route_key IS NOT NULL AND page_id IS NULL)
                    OR (link_type = 'page' AND route_key IS NULL AND page_id IS NOT NULL)
                    OR (link_type = 'group' AND route_key IS NULL AND page_id IS NULL)
                ),
                CONSTRAINT chk_navigation_items_group_scope CHECK (
                    link_type <> 'group' OR (location = 'main' AND parent_id IS NULL)
                ),
                CONSTRAINT chk_navigation_items_footer_scope CHECK (
                    location <> 'footer' OR parent_id IS NULL
                ),
                CONSTRAINT chk_navigation_items_sort_order CHECK (sort_order > 0),
                CONSTRAINT chk_navigation_items_active CHECK (is_active IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $connection->exec(<<<'SQL'
            INSERT INTO navigation_items (
                location, label, link_type, route_key, page_id, parent_id,
                sort_order, is_active, created_by, updated_by
            ) VALUES
                ('main', 'Home', 'route', 'home', NULL, NULL, 10, 1, NULL, NULL),
                ('main', 'Rooms', 'route', 'rooms', NULL, NULL, 20, 1, NULL, NULL),
                ('main', 'Activities', 'route', 'activities', NULL, NULL, 30, 1, NULL, NULL),
                ('main', 'Gallery', 'route', 'gallery', NULL, NULL, 40, 1, NULL, NULL),
                ('main', 'Reviews', 'route', 'reviews', NULL, NULL, 50, 1, NULL, NULL),
                ('main', 'Availability', 'route', 'availability', NULL, NULL, 60, 1, NULL, NULL),
                ('main', 'Contact', 'route', 'contact', NULL, NULL, 70, 1, NULL, NULL)
            SQL);

        $connection->exec(<<<'SQL'
            INSERT INTO navigation_items (
                location, label, link_type, route_key, page_id, parent_id,
                sort_order, is_active, created_by, updated_by
            )
            SELECT
                'main', title, 'page', NULL, id, NULL,
                1000 + id, 1, NULL, NULL
            FROM pages
            WHERE status = 'published'
              AND slug NOT IN (
                  'activities', 'admin', 'api', 'availability', 'booking', 'contact',
                  'gallery', 'review', 'reviews', 'rooms'
              )
            ORDER BY title, id
            SQL);
    }

    /** Removes navigation configuration. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE navigation_items');
    }
};
