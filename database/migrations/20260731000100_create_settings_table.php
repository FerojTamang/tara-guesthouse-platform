<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates the Version 1 allowlisted site settings table. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                setting_key VARCHAR(150) NOT NULL,
                setting_value LONGTEXT NOT NULL,
                value_type VARCHAR(30) NOT NULL,
                is_public TINYINT(1) NOT NULL,
                updated_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_settings PRIMARY KEY (id),
                CONSTRAINT uq_settings_key UNIQUE (setting_key),
                INDEX idx_settings_public_key (is_public, setting_key, id),
                INDEX idx_settings_updated_by (updated_by),
                CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_settings_key CHECK (
                    BINARY setting_key REGEXP '^[a-z][a-z0-9]*(\\.[a-z0-9]+)*$'
                ),
                CONSTRAINT chk_settings_value_type CHECK (
                    value_type IN ('string', 'integer', 'boolean', 'email', 'url', 'relative_path', 'json')
                ),
                CONSTRAINT chk_settings_is_public CHECK (is_public IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** Removes site settings. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE settings');
    }
};
