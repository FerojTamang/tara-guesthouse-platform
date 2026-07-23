<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Adds database-enforced room-image path, ordering, and primary invariants.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE room_images
                ADD COLUMN primary_room_id BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN is_primary = 1 THEN room_id
                            ELSE NULL
                        END
                    ) VIRTUAL,
                ADD UNIQUE INDEX uq_room_images_file_path (file_path),
                ADD INDEX idx_room_images_room_order (room_id, sort_order, id),
                ADD UNIQUE INDEX uq_room_images_primary_room (primary_room_id)
            SQL);
    }

    /**
     * Removes only the follow-up room-image persistence constraints.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE room_images
                DROP INDEX uq_room_images_primary_room,
                ADD INDEX fk_room_images_room (room_id),
                DROP INDEX idx_room_images_room_order,
                DROP INDEX uq_room_images_file_path,
                DROP COLUMN primary_room_id
            SQL);
    }
};
