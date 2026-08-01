<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Adds indexes aligned with measured room and booking operational queries. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE rooms
                ADD INDEX idx_rooms_public_order (
                    deleted_at,
                    operational_status,
                    is_published,
                    room_number,
                    id
                )
            SQL);

        $connection->exec(<<<'SQL'
            ALTER TABLE bookings
                ADD INDEX idx_bookings_deleted_created (deleted_at, created_at, id),
                ADD INDEX idx_bookings_status_deleted_created (
                    booking_status,
                    deleted_at,
                    created_at,
                    id
                ),
                ADD INDEX idx_bookings_deleted_checkout (deleted_at, check_out)
            SQL);
    }

    /** Removes only the S5-007 operational query indexes. */
    public function down(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE bookings
                DROP INDEX idx_bookings_deleted_checkout,
                DROP INDEX idx_bookings_status_deleted_created,
                DROP INDEX idx_bookings_deleted_created
            SQL);

        $connection->exec(<<<'SQL'
            ALTER TABLE rooms
                DROP INDEX idx_rooms_public_order
            SQL);
    }
};
