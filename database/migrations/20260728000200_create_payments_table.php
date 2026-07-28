<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates retained manual payment attempts for Version 1 bookings.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE payments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NOT NULL,
                payment_method_id BIGINT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                transaction_reference VARCHAR(100) NULL,
                proof_path VARCHAR(255) NULL,
                guest_notes TEXT NULL,
                payment_status VARCHAR(20) NOT NULL,
                submitted_at DATETIME NULL,
                verified_at DATETIME NULL,
                verified_by BIGINT UNSIGNED NULL,
                rejected_at DATETIME NULL,
                rejected_by BIGINT UNSIGNED NULL,
                verification_note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_payments PRIMARY KEY (id),
                INDEX idx_payments_booking_created (booking_id, created_at, id),
                INDEX idx_payments_method (payment_method_id),
                INDEX idx_payments_status_created (payment_status, created_at, id),
                INDEX idx_payments_verified_by (verified_by),
                INDEX idx_payments_rejected_by (rejected_by),
                CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_payments_verified_by FOREIGN KEY (verified_by) REFERENCES users (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_payments_rejected_by FOREIGN KEY (rejected_by) REFERENCES users (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_payments_currency CHECK (
                    BINARY currency = 'NPR'
                ),
                CONSTRAINT chk_payments_amount CHECK (
                    amount > 0
                ),
                CONSTRAINT chk_payments_proof_path CHECK (
                    proof_path IS NULL
                    OR (
                        proof_path <> ''
                        AND LEFT(proof_path, 1) <> '/'
                        AND LEFT(proof_path, 1) <> CHAR(92)
                        AND LOCATE(CHAR(92), proof_path) = 0
                        AND LOCATE('//', proof_path) = 0
                        AND LOCATE('/../', CONCAT('/', proof_path, '/')) = 0
                        AND LOCATE('/./', CONCAT('/', proof_path, '/')) = 0
                    )
                ),
                CONSTRAINT chk_payments_status CHECK (
                    BINARY payment_status IN (
                        'pending',
                        'submitted',
                        'verified',
                        'rejected',
                        'refunded'
                    )
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes payment attempts before payment methods or bookings are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE payments');
    }
};
