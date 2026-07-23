<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 room-type catalogue.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE room_types (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                description TEXT NULL,
                base_price DECIMAL(12,2) NOT NULL,
                capacity_adults INT NOT NULL,
                capacity_children INT NOT NULL,
                is_active TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_room_types PRIMARY KEY (id),
                CONSTRAINT uq_room_types_slug UNIQUE (slug),
                CONSTRAINT chk_room_types_base_price CHECK (base_price > 0),
                CONSTRAINT chk_room_types_capacity_adults CHECK (capacity_adults >= 1),
                CONSTRAINT chk_room_types_capacity_children CHECK (capacity_children >= 0),
                CONSTRAINT chk_room_types_is_active CHECK (is_active IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes room types after every dependent room table is reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE room_types');
    }
};
