<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Adds pinned-content support to the professional publication profile. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE page_content_profiles
                ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured,
                ADD INDEX idx_page_content_publishing (
                    content_type, is_pinned, is_featured, publish_at, expires_at, page_id
                ),
                ADD CONSTRAINT chk_page_content_pinned CHECK (is_pinned IN (0, 1))
            SQL);
    }

    /** Removes only the advanced publishing additions. */
    public function down(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE page_content_profiles
                DROP CONSTRAINT chk_page_content_pinned,
                DROP INDEX idx_page_content_publishing,
                DROP COLUMN is_pinned
            SQL);
    }
};
