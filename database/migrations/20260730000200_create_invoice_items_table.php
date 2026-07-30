<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /**
     * Creates immutable fixed-precision invoice line-item snapshots.
     */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE invoice_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                invoice_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                unit_price DECIMAL(12,2) NOT NULL,
                line_total DECIMAL(12,2) NOT NULL,
                CONSTRAINT pk_invoice_items PRIMARY KEY (id),
                INDEX idx_invoice_items_invoice (invoice_id, id),
                CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_invoice_items_description CHECK (
                    CHAR_LENGTH(TRIM(description)) > 0
                ),
                CONSTRAINT chk_invoice_items_quantity CHECK (quantity > 0),
                CONSTRAINT chk_invoice_items_unit_price CHECK (unit_price > 0),
                CONSTRAINT chk_invoice_items_line_total CHECK (
                    line_total > 0 AND line_total = quantity * unit_price
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /**
     * Removes invoice items before invoices are reversed.
     */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE invoice_items');
    }
};
