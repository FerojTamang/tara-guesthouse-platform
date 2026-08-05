<?php

declare(strict_types=1);

use App\Core\Migration;

return new class extends Migration {
    /** Adds controlled inquiry statuses and retained outbound reply history. */
    public function up(\PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            ALTER TABLE contact_messages
                DROP CONSTRAINT chk_contact_messages_status
            SQL);

        $connection->exec(<<<'SQL'
            UPDATE contact_messages
            SET status = 'new'
            WHERE status IS NULL
            SQL);

        $connection->exec(<<<'SQL'
            ALTER TABLE contact_messages
                MODIFY status VARCHAR(40) NOT NULL DEFAULT 'new',
                ADD CONSTRAINT chk_contact_messages_status CHECK (
                    BINARY status IN ('new', 'in_progress', 'replied', 'closed')
                )
            SQL);

        $connection->exec(<<<'SQL'
            CREATE TABLE contact_message_replies (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                contact_message_id BIGINT UNSIGNED NOT NULL,
                reply_body TEXT NOT NULL,
                sent_by BIGINT UNSIGNED NOT NULL,
                delivery_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                sent_at DATETIME NULL,
                failure_code VARCHAR(80) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT pk_contact_message_replies PRIMARY KEY (id),
                INDEX idx_contact_replies_message_created (contact_message_id, created_at, id),
                INDEX idx_contact_replies_delivery_created (delivery_status, created_at, id),
                INDEX idx_contact_replies_sent_by (sent_by),
                CONSTRAINT fk_contact_replies_message FOREIGN KEY (contact_message_id)
                    REFERENCES contact_messages (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT fk_contact_replies_sent_by FOREIGN KEY (sent_by)
                    REFERENCES users (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT chk_contact_replies_body CHECK (
                    BINARY reply_body = BINARY TRIM(reply_body)
                    AND CHAR_LENGTH(reply_body) BETWEEN 1 AND 5000
                ),
                CONSTRAINT chk_contact_replies_delivery_status CHECK (
                    BINARY delivery_status IN ('pending', 'sent', 'failed')
                ),
                CONSTRAINT chk_contact_replies_delivery_state CHECK (
                    (delivery_status = 'pending' AND sent_at IS NULL AND failure_code IS NULL)
                    OR
                    (delivery_status = 'sent' AND sent_at IS NOT NULL AND failure_code IS NULL)
                    OR
                    (delivery_status = 'failed' AND sent_at IS NULL AND failure_code IS NOT NULL)
                ),
                CONSTRAINT chk_contact_replies_failure_code CHECK (
                    failure_code IS NULL OR (
                        BINARY failure_code = BINARY TRIM(failure_code)
                        AND BINARY failure_code REGEXP '^[a-z][a-z0-9_]*$'
                        AND CHAR_LENGTH(failure_code) BETWEEN 1 AND 80
                    )
                ),
                CONSTRAINT chk_contact_replies_timestamps CHECK (
                    updated_at >= created_at
                    AND (sent_at IS NULL OR sent_at >= created_at)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** Removes reply history and restores the original policy-pending status column. */
    public function down(\PDO $connection): void
    {
        $connection->exec('DROP TABLE contact_message_replies');

        $connection->exec(<<<'SQL'
            ALTER TABLE contact_messages
                DROP CONSTRAINT chk_contact_messages_status,
                MODIFY status VARCHAR(40) NULL DEFAULT NULL,
                ADD CONSTRAINT chk_contact_messages_status CHECK (
                    status IS NULL OR (
                        BINARY status = BINARY TRIM(status)
                        AND BINARY status REGEXP '^[a-z][a-z0-9_]*$'
                    )
                )
            SQL);
    }
};
