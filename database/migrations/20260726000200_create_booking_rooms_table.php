<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the one-room Version 1 booking assignment and price snapshot.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE booking_rooms (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NOT NULL,
                room_id BIGINT UNSIGNED NOT NULL,
                price_per_night DECIMAL(12,2) NOT NULL,
                nights INT NOT NULL,
                subtotal DECIMAL(12,2) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_booking_rooms PRIMARY KEY (id),
                CONSTRAINT uq_booking_rooms_booking UNIQUE (booking_id),
                INDEX idx_booking_rooms_room_booking (room_id, booking_id),
                CONSTRAINT fk_booking_rooms_booking FOREIGN KEY (booking_id) REFERENCES bookings (id)
                    ON UPDATE RESTRICT ON DELETE CASCADE,
                CONSTRAINT fk_booking_rooms_room FOREIGN KEY (room_id) REFERENCES rooms (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_booking_rooms_price CHECK (price_per_night > 0),
                CONSTRAINT chk_booking_rooms_nights CHECK (nights >= 1),
                CONSTRAINT chk_booking_rooms_subtotal CHECK (subtotal > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes booking room snapshots before bookings are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE booking_rooms');
    }
};
