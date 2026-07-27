<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates append-only operational audit history for Administrator actions.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(80) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT UNSIGNED NULL,
                description VARCHAR(255) NOT NULL,
                metadata_json LONGTEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_audit_logs PRIMARY KEY (id),
                INDEX idx_audit_logs_user (user_id),
                INDEX idx_audit_logs_event_created (event_type, created_at, id),
                INDEX idx_audit_logs_entity_created (entity_type, entity_id, created_at, id),
                INDEX idx_audit_logs_created (created_at, id),
                CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_audit_logs_event_type CHECK (
                    CHAR_LENGTH(TRIM(event_type)) > 0
                ),
                CONSTRAINT chk_audit_logs_entity_type CHECK (
                    CHAR_LENGTH(TRIM(entity_type)) > 0
                ),
                CONSTRAINT chk_audit_logs_description CHECK (
                    CHAR_LENGTH(TRIM(description)) > 0
                ),
                CONSTRAINT chk_audit_logs_metadata_json CHECK (
                    metadata_json IS NULL OR JSON_VALID(metadata_json)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes audit history before the referenced users table is reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE audit_logs');
    }
};
