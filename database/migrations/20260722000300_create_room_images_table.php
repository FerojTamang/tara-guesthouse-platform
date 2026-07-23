<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates ordered room-image metadata.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE room_images (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                room_id BIGINT UNSIGNED NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                alt_text VARCHAR(255) NULL,
                sort_order INT NOT NULL,
                is_primary TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_room_images PRIMARY KEY (id),
                CONSTRAINT fk_room_images_room FOREIGN KEY (room_id) REFERENCES rooms (id)
                    ON UPDATE RESTRICT ON DELETE CASCADE,
                CONSTRAINT chk_room_images_file_path CHECK (
                    file_path <> ''
                    AND LEFT(file_path, 1) <> '/'
                    AND LEFT(file_path, 1) <> CHAR(92)
                    AND LOCATE(CHAR(92), file_path) = 0
                    AND LOCATE('//', file_path) = 0
                    AND LOCATE('/../', CONCAT('/', file_path, '/')) = 0
                    AND LOCATE('/./', CONCAT('/', file_path, '/')) = 0
                ),
                CONSTRAINT chk_room_images_sort_order CHECK (sort_order >= 0),
                CONSTRAINT chk_room_images_is_primary CHECK (is_primary IN (0, 1))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes room-image metadata before rooms are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE room_images');
    }
};
