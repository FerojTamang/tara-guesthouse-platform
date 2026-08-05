<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Allows the Navigation Manager to target the public News and Notices indexes. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE navigation_items
                DROP CONSTRAINT chk_navigation_items_route_key,
                ADD CONSTRAINT chk_navigation_items_route_key CHECK (
                    route_key IS NULL OR route_key IN (
                        'home', 'rooms', 'activities', 'gallery',
                        'news', 'notices', 'reviews', 'availability', 'contact'
                    )
                )
            SQL);
    }

    /** Restores the original route allowlist without leaving invalid route rows behind. */
    public function down(\PDO $connection): void
    {
        $connection->exec(
            "DELETE FROM navigation_items WHERE route_key IN ('news', 'notices')",
        );
        $connection->exec(<<<'SQL'
            ALTER TABLE navigation_items
                DROP CONSTRAINT chk_navigation_items_route_key,
                ADD CONSTRAINT chk_navigation_items_route_key CHECK (
                    route_key IS NULL OR route_key IN (
                        'home', 'rooms', 'activities', 'gallery',
                        'reviews', 'availability', 'contact'
                    )
                )
            SQL);
    }
};
