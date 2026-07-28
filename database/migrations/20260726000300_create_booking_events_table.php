<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates append-only booking event and status-transition history.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE booking_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                from_status VARCHAR(20) NULL,
                to_status VARCHAR(20) NULL,
                performed_by BIGINT UNSIGNED NULL,
                reason TEXT NULL,
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pk_booking_events PRIMARY KEY (id),
                INDEX idx_booking_events_booking_created (booking_id, created_at, id),
                INDEX idx_booking_events_actor (performed_by),
                INDEX idx_booking_events_type (event_type),
                CONSTRAINT fk_booking_events_booking FOREIGN KEY (booking_id) REFERENCES bookings (id)
                    ON UPDATE RESTRICT ON DELETE CASCADE,
                CONSTRAINT fk_booking_events_performer FOREIGN KEY (performed_by) REFERENCES users (id)
                    ON UPDATE RESTRICT ON DELETE SET NULL,
                CONSTRAINT chk_booking_events_event_type CHECK (CHAR_LENGTH(TRIM(event_type)) > 0),
                CONSTRAINT chk_booking_events_from_status CHECK (
                    from_status IS NULL
                    OR BINARY from_status IN (
                        'pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'rejected'
                    )
                ),
                CONSTRAINT chk_booking_events_to_status CHECK (
                    to_status IS NULL
                    OR BINARY to_status IN (
                        'pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'rejected'
                    )
                ),
                CONSTRAINT chk_booking_events_metadata_json CHECK (
                    metadata_json IS NULL OR JSON_VALID(metadata_json)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes booking event history before bookings are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE booking_events');
    }
};
