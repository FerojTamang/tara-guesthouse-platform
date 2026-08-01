<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\NotificationFixture;

#[Group('database')]
final class NotificationMigrationTest extends DatabaseTestCase
{
    private const APPLICATION_MIGRATIONS = [
        '20260721000100_create_roles_table',
        '20260721000200_create_users_table',
        '20260722000100_create_room_types_table',
        '20260722000200_create_rooms_table',
        '20260722000300_create_room_images_table',
        '20260722000400_create_amenities_table',
        '20260722000500_create_room_amenities_table',
        '20260723000100_add_room_image_persistence_constraints',
        '20260726000100_create_bookings_table',
        '20260726000200_create_booking_rooms_table',
        '20260726000300_create_booking_events_table',
        '20260727000100_create_audit_logs_table',
        '20260728000100_create_payment_methods_table',
        '20260728000200_create_payments_table',
        '20260730000100_create_invoices_table',
        '20260730000200_create_invoice_items_table',
        '20260730000300_create_activities_table',
        '20260730000400_create_gallery_categories_table',
        '20260730000500_create_gallery_table',
        '20260731000100_create_settings_table',
        '20260731000200_create_pages_table',
        '20260731000300_create_contact_messages_table',
        '20260801000100_create_review_invitations_table',
        '20260801000200_create_reviews_table',
        '20260801000300_create_notifications_table',
        '20260801000400_add_operational_query_indexes',
    ];

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        );
    }

    public function testMigrationExposesApprovedAdministratorNotificationContract(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame([
            'id', 'user_id', 'type', 'title', 'message', 'related_type', 'related_id',
            'read_at', 'created_at',
        ], $this->columns('notifications'));
        self::assertSame(
            ['user_id', 'created_at', 'id'],
            $this->indexColumns('notifications', 'idx_notifications_user_created'),
        );
        self::assertSame(
            ['user_id', 'read_at', 'created_at', 'id'],
            $this->indexColumns('notifications', 'idx_notifications_user_unread'),
        );
        self::assertSame(['RESTRICT', 'RESTRICT'], $this->referentialRules('fk_notifications_user'));
        self::assertSame('InnoDB', $this->tableOption('notifications', 'ENGINE'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('notifications', 'TABLE_COLLATION'));
    }

    public function testConstraintsRetainOwnershipContentAndReadHistory(): void
    {
        $this->runner->migrate();
        $userId = NotificationFixture::insertAdministrator($this->connection);
        $notificationId = NotificationFixture::insert($this->connection, $userId);
        self::assertGreaterThan(0, $notificationId);

        foreach ([
            ['type' => 'Contact Message'],
            ['type' => 'contact-message'],
            ['title' => '   '],
            ['message' => '   '],
            ['message' => str_repeat('é', 32_768)],
            ['related_type' => 'Contact'],
            ['related_id' => 0],
            ['read_at' => '2026-08-01 05:59:59'],
        ] as $overrides) {
            $this->assertPdoFailure(
                fn () => NotificationFixture::insert($this->connection, $userId, $overrides),
            );
        }

        $this->assertPdoFailure(
            fn () => $this->connection->exec('DELETE FROM users WHERE id = ' . $userId),
        );
        self::assertSame(1, (int) $this->connection->query(
            'SELECT COUNT(*) FROM notifications WHERE id = ' . $notificationId,
        )->fetchColumn());
    }

    public function testRollbackRemovesNotificationsBeforeAdministratorUsers(): void
    {
        $this->runner->migrate();
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->tableCount('notifications'));
        self::assertSame(0, $this->tableCount('users'));
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
        );
        $statement->execute(['table_name' => $table]);
        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $statement->fetchAll());
    }

    /** @return list<string> */
    private function indexColumns(string $table, string $index): array
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX',
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);
        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $statement->fetchAll());
    }

    /** @return list<string> */
    private function referentialRules(string $constraint): array
    {
        $statement = $this->connection->prepare(
            'SELECT UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :constraint_name',
        );
        $statement->execute(['constraint_name' => $constraint]);
        $row = $statement->fetch();
        self::assertIsArray($row);
        return [(string) $row['UPDATE_RULE'], (string) $row['DELETE_RULE']];
    }

    private function tableOption(string $table, string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT ' . $column . ' FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);
        return (string) $statement->fetchColumn();
    }

    private function tableCount(string $table): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);
        return (int) $statement->fetchColumn();
    }

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid notification data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }
}
