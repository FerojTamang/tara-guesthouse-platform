<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 roles table.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE roles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                role_name VARCHAR(50) NOT NULL,
                description VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_roles PRIMARY KEY (id),
                CONSTRAINT uq_roles_role_name UNIQUE (role_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes only the roles table after dependent migrations are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE roles');
    }
};
