<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Creates retained public contact messages without an unapproved workflow default. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE contact_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL,
                phone VARCHAR(20) NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(40) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT pk_contact_messages PRIMARY KEY (id),
                INDEX idx_contact_messages_created (created_at, id),
                INDEX idx_contact_messages_status_created (status, created_at, id),
                INDEX idx_contact_messages_email_created (email, created_at, id),
                CONSTRAINT chk_contact_messages_name CHECK (
                    BINARY name = BINARY TRIM(name)
                    AND CHAR_LENGTH(name) BETWEEN 2 AND 100
                ),
                CONSTRAINT chk_contact_messages_email CHECK (
                    BINARY email = BINARY TRIM(email)
                    AND CHAR_LENGTH(email) BETWEEN 3 AND 150
                ),
                CONSTRAINT chk_contact_messages_phone CHECK (
                    phone IS NULL OR (
                        BINARY phone = BINARY TRIM(phone)
                        AND CHAR_LENGTH(phone) BETWEEN 1 AND 20
                    )
                ),
                CONSTRAINT chk_contact_messages_subject CHECK (
                    BINARY subject = BINARY TRIM(subject)
                    AND CHAR_LENGTH(subject) BETWEEN 3 AND 200
                ),
                CONSTRAINT chk_contact_messages_message CHECK (
                    BINARY message = BINARY TRIM(message)
                    AND CHAR_LENGTH(message) BETWEEN 10 AND 5000
                ),
                CONSTRAINT chk_contact_messages_status CHECK (
                    status IS NULL OR (
                        BINARY status = BINARY TRIM(status)
                        AND BINARY status REGEXP '^[a-z][a-z0-9_]*$'
                    )
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** Removes contact messages during controlled migration rollback. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE contact_messages');
    }
};
