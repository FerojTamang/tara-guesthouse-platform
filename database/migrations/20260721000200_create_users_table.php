<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 Administrator users table.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                role_id BIGINT UNSIGNED NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                username VARCHAR(50) NOT NULL,
                email VARCHAR(150) NOT NULL,
                phone VARCHAR(20) NULL,
                password VARCHAR(255) NOT NULL,
                profile_image VARCHAR(255) NULL,
                last_login_at DATETIME NULL,
                account_status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                CONSTRAINT pk_users PRIMARY KEY (id),
                CONSTRAINT uq_users_username UNIQUE (username),
                CONSTRAINT uq_users_email UNIQUE (email),
                CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_users_account_status CHECK (
                    BINARY account_status IN ('active', 'inactive', 'locked')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes the users table before the roles migration is reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE users');
    }
};
