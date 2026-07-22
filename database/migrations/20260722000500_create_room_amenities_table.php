<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the many-to-many room and amenity assignment table.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE room_amenities (
                room_id BIGINT UNSIGNED NOT NULL,
                amenity_id BIGINT UNSIGNED NOT NULL,
                CONSTRAINT pk_room_amenities PRIMARY KEY (room_id, amenity_id),
                CONSTRAINT fk_room_amenities_room FOREIGN KEY (room_id) REFERENCES rooms (id)
                    ON UPDATE RESTRICT ON DELETE CASCADE,
                CONSTRAINT fk_room_amenities_amenity FOREIGN KEY (amenity_id) REFERENCES amenities (id)
                    ON UPDATE RESTRICT ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes room and amenity assignments first in the room rollback chain.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE room_amenities');
    }
};
