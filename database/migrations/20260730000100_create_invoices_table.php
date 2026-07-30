<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates one immutable Version 1 invoice snapshot per eligible booking.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE invoices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id BIGINT UNSIGNED NOT NULL,
                invoice_number VARCHAR(50) NOT NULL,
                currency CHAR(3) NOT NULL,
                subtotal DECIMAL(12,2) NOT NULL,
                tax_amount DECIMAL(12,2) NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL,
                issued_at DATETIME NOT NULL,
                created_by BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_invoices PRIMARY KEY (id),
                CONSTRAINT uq_invoices_booking UNIQUE (booking_id),
                CONSTRAINT uq_invoices_number UNIQUE (invoice_number),
                INDEX idx_invoices_issued (issued_at, id),
                INDEX idx_invoices_created_by (created_by),
                CONSTRAINT fk_invoices_booking FOREIGN KEY (booking_id) REFERENCES bookings (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by) REFERENCES users (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_invoices_number CHECK (
                    invoice_number REGEXP '^TGH-INV-[0-9]{4}-[0-9]{6}$'
                    OR invoice_number REGEXP '^TMP-[0-9a-f]{32}$'
                ),
                CONSTRAINT chk_invoices_currency CHECK (BINARY currency = 'NPR'),
                CONSTRAINT chk_invoices_subtotal CHECK (subtotal > 0),
                CONSTRAINT chk_invoices_tax CHECK (tax_amount = 0.00),
                CONSTRAINT chk_invoices_total CHECK (
                    total_amount > 0 AND total_amount = subtotal + tax_amount
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes invoices after invoice items are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE invoices');
    }
};
