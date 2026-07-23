<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 physical rooms table.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE rooms (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                room_type_id BIGINT UNSIGNED NOT NULL,
                room_number VARCHAR(30) NOT NULL,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                description TEXT NULL,
                price_per_night DECIMAL(12,2) NOT NULL,
                capacity_adults INT NOT NULL,
                capacity_children INT NOT NULL,
                operational_status VARCHAR(20) NOT NULL,
                is_published TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                CONSTRAINT pk_rooms PRIMARY KEY (id),
                CONSTRAINT uq_rooms_room_number UNIQUE (room_number),
                CONSTRAINT uq_rooms_slug UNIQUE (slug),
                CONSTRAINT fk_rooms_room_type FOREIGN KEY (room_type_id) REFERENCES room_types (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_rooms_price_per_night CHECK (price_per_night > 0),
                CONSTRAINT chk_rooms_capacity_adults CHECK (capacity_adults >= 1),
                CONSTRAINT chk_rooms_capacity_children CHECK (capacity_children >= 0),
                CONSTRAINT chk_rooms_operational_status CHECK (
                    BINARY operational_status IN ('active', 'maintenance', 'inactive')
                ),
                CONSTRAINT chk_rooms_is_published CHECK (is_published IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes rooms after images and amenity assignments are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE rooms');
    }
};
