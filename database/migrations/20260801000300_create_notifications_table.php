<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates retained Administrator-owned in-application notifications. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                related_type VARCHAR(50) NULL,
                related_id BIGINT UNSIGNED NULL,
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_notifications PRIMARY KEY (id),
                INDEX idx_notifications_user_created (user_id, created_at, id),
                INDEX idx_notifications_user_unread (user_id, read_at, created_at, id),
                CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_notifications_type CHECK (
                    BINARY type = BINARY TRIM(type)
                    AND CHAR_LENGTH(type) BETWEEN 1 AND 50
                    AND BINARY type REGEXP '^[a-z][a-z0-9_]*$'
                ),
                CONSTRAINT chk_notifications_title CHECK (
                    BINARY title = BINARY TRIM(title)
                    AND CHAR_LENGTH(title) BETWEEN 1 AND 150
                ),
                CONSTRAINT chk_notifications_message CHECK (
                    BINARY message = BINARY TRIM(message)
                    AND CHAR_LENGTH(message) > 0
                ),
                CONSTRAINT chk_notifications_related_type CHECK (
                    related_type IS NULL OR (
                        BINARY related_type = BINARY TRIM(related_type)
                        AND CHAR_LENGTH(related_type) BETWEEN 1 AND 50
                        AND BINARY related_type REGEXP '^[a-z][a-z0-9_]*$'
                    )
                ),
                CONSTRAINT chk_notifications_related_id CHECK (
                    related_id IS NULL OR related_id > 0
                ),
                CONSTRAINT chk_notifications_read_time CHECK (
                    read_at IS NULL OR read_at >= created_at
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** Removes notifications before Administrator users during controlled rollback. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE notifications');
    }
};
