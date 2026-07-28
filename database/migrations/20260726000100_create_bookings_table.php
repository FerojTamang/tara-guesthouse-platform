<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates the Version 1 booking record and Guest contact snapshot.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE bookings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_reference VARCHAR(30) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                guest_full_name VARCHAR(100) NOT NULL,
                guest_email VARCHAR(150) NOT NULL,
                guest_phone VARCHAR(20) NOT NULL,
                check_in DATE NOT NULL,
                check_out DATE NOT NULL,
                adults INT NOT NULL,
                children INT NOT NULL,
                currency CHAR(3) NOT NULL,
                room_amount DECIMAL(12,2) NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL,
                booking_status VARCHAR(20) NOT NULL,
                special_request TEXT NULL,
                terms_accepted_at DATETIME NOT NULL,
                policy_version VARCHAR(50) NULL,
                confirmation_deadline_at DATETIME NOT NULL,
                confirmed_at DATETIME NULL,
                checked_in_at DATETIME NULL,
                checked_out_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                cancellation_reason TEXT NULL,
                rejected_at DATETIME NULL,
                rejection_reason TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                CONSTRAINT pk_bookings PRIMARY KEY (id),
                CONSTRAINT uq_bookings_booking_reference UNIQUE (booking_reference),
                INDEX idx_bookings_user (user_id),
                INDEX idx_bookings_guest_email (guest_email),
                INDEX idx_bookings_date_range (check_in, check_out),
                INDEX idx_bookings_status_deadline (booking_status, confirmation_deadline_at),
                CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users (id)
                    ON UPDATE RESTRICT ON DELETE SET NULL,
                CONSTRAINT chk_bookings_date_range CHECK (check_out > check_in),
                CONSTRAINT chk_bookings_adults CHECK (adults >= 1),
                CONSTRAINT chk_bookings_children CHECK (children >= 0),
                CONSTRAINT chk_bookings_currency CHECK (BINARY currency = 'NPR'),
                CONSTRAINT chk_bookings_room_amount CHECK (room_amount > 0),
                CONSTRAINT chk_bookings_total_amount CHECK (total_amount > 0),
                CONSTRAINT chk_bookings_status CHECK (
                    BINARY booking_status IN (
                        'pending',
                        'confirmed',
                        'checked_in',
                        'checked_out',
                        'cancelled',
                        'rejected'
                    )
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes bookings after booking events and room snapshots are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE bookings');
    }
};
