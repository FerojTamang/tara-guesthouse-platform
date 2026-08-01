<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates single-use verified-stay review invitations. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE review_invitations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT pk_review_invitations PRIMARY KEY (id),
                CONSTRAINT uq_review_invitations_booking UNIQUE (booking_id),
                CONSTRAINT uq_review_invitations_token_hash UNIQUE (token_hash),
                CONSTRAINT uq_review_invitations_id_booking UNIQUE (id, booking_id),
                INDEX idx_review_invitations_expiry (expires_at, used_at, id),
                CONSTRAINT fk_review_invitations_booking FOREIGN KEY (booking_id)
                    REFERENCES bookings (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_review_invitations_token_hash CHECK (
                    CHAR_LENGTH(token_hash) = 64
                    AND BINARY token_hash REGEXP '^[a-f0-9]+$'
                ),
                CONSTRAINT chk_review_invitations_expiry CHECK (expires_at > created_at),
                CONSTRAINT chk_review_invitations_used_at CHECK (
                    used_at IS NULL OR (used_at >= created_at AND used_at < expires_at)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** Removes invitations after reviews are reversed. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE review_invitations');
    }
};
