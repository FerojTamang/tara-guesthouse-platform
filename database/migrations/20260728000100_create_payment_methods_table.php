<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates configurable Version 1 manual payment methods.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE payment_methods (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                instructions TEXT NULL,
                account_reference VARCHAR(255) NULL,
                qr_image_path VARCHAR(255) NULL,
                requires_proof TINYINT(1) NOT NULL,
                is_active TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_payment_methods PRIMARY KEY (id),
                INDEX idx_payment_methods_active_name (is_active, name, id),
                CONSTRAINT chk_payment_methods_name CHECK (
                    CHAR_LENGTH(TRIM(name)) > 0
                ),
                CONSTRAINT chk_payment_methods_qr_image_path CHECK (
                    qr_image_path IS NULL
                    OR (
                        qr_image_path <> ''
                        AND LEFT(qr_image_path, 1) <> '/'
                        AND LEFT(qr_image_path, 1) <> CHAR(92)
                        AND LOCATE(CHAR(92), qr_image_path) = 0
                        AND LOCATE('//', qr_image_path) = 0
                        AND LOCATE('/../', CONCAT('/', qr_image_path, '/')) = 0
                        AND LOCATE('/./', CONCAT('/', qr_image_path, '/')) = 0
                    )
                ),
                CONSTRAINT chk_payment_methods_requires_proof CHECK (
                    requires_proof IN (0, 1)
                ),
                CONSTRAINT chk_payment_methods_is_active CHECK (
                    is_active IN (0, 1)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes payment methods after dependent payment attempts are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE payment_methods');
    }
};
