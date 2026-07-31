<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates retained verified-stay reviews and moderation evidence. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE reviews (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NOT NULL,
                review_invitation_id BIGINT UNSIGNED NOT NULL,
                guest_name_snapshot VARCHAR(100) NOT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                title VARCHAR(150) NULL,
                comment TEXT NOT NULL,
                review_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                moderated_by BIGINT UNSIGNED NULL,
                moderated_at DATETIME NULL,
                moderation_note TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT pk_reviews PRIMARY KEY (id),
                CONSTRAINT uq_reviews_booking UNIQUE (booking_id),
                CONSTRAINT uq_reviews_invitation UNIQUE (review_invitation_id),
                INDEX idx_reviews_public (review_status, created_at, id),
                INDEX idx_reviews_moderation (review_status, updated_at, id),
                INDEX idx_reviews_moderated_by (moderated_by),
                CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id)
                    REFERENCES bookings (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_reviews_invitation_booking FOREIGN KEY (review_invitation_id, booking_id)
                    REFERENCES review_invitations (id, booking_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_reviews_moderated_by FOREIGN KEY (moderated_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_reviews_guest_name CHECK (
                    BINARY guest_name_snapshot = BINARY TRIM(guest_name_snapshot)
                    AND CHAR_LENGTH(guest_name_snapshot) BETWEEN 1 AND 100
                ),
                CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
                CONSTRAINT chk_reviews_title CHECK (
                    title IS NULL OR (
                        BINARY title = BINARY TRIM(title)
                        AND CHAR_LENGTH(title) BETWEEN 1 AND 150
                    )
                ),
                CONSTRAINT chk_reviews_comment CHECK (
                    BINARY comment = BINARY TRIM(comment)
                    AND CHAR_LENGTH(comment) > 0
                ),
                CONSTRAINT chk_reviews_status CHECK (
                    BINARY review_status IN ('pending', 'approved', 'rejected')
                ),
                CONSTRAINT chk_reviews_moderation CHECK (
                    (review_status = 'pending'
                        AND moderated_by IS NULL
                        AND moderated_at IS NULL
                        AND moderation_note IS NULL)
                    OR
                    (review_status IN ('approved', 'rejected')
                        AND moderated_by IS NOT NULL
                        AND moderated_at IS NOT NULL
                        AND moderated_at >= created_at
                        AND updated_at >= moderated_at)
                ),
                CONSTRAINT chk_reviews_timestamps CHECK (updated_at >= created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** Removes reviews before their invitations. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE reviews');
    }
};
