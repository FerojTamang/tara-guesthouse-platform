<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\MigrationRunner;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ReviewFixture;

#[Group('database')]
final class ReviewMigrationTest extends DatabaseTestCase
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

    public function testMigrationsExposeInvitationAndReviewContracts(): void
    {
        self::assertSame(self::APPLICATION_MIGRATIONS, $this->runner->migrate());
        self::assertSame([
            'id', 'booking_id', 'token_hash', 'expires_at', 'used_at', 'created_at',
        ], $this->columns('review_invitations'));
        self::assertSame([
            'id', 'booking_id', 'review_invitation_id', 'guest_name_snapshot', 'rating', 'title',
            'comment', 'review_status', 'moderated_by', 'moderated_at', 'moderation_note', 'created_at', 'updated_at',
        ], $this->columns('reviews'));
        self::assertSame(['booking_id'], $this->indexColumns('review_invitations', 'uq_review_invitations_booking'));
        self::assertSame(['token_hash'], $this->indexColumns('review_invitations', 'uq_review_invitations_token_hash'));
        self::assertSame(['id', 'booking_id'], $this->indexColumns('review_invitations', 'uq_review_invitations_id_booking'));
        self::assertSame(['expires_at', 'used_at', 'id'], $this->indexColumns('review_invitations', 'idx_review_invitations_expiry'));
        self::assertSame(['booking_id'], $this->indexColumns('reviews', 'uq_reviews_booking'));
        self::assertSame(['review_invitation_id'], $this->indexColumns('reviews', 'uq_reviews_invitation'));
        self::assertSame('pending', $this->columnDefault('reviews', 'review_status'));
        self::assertSame('InnoDB', $this->tableOption('reviews', 'ENGINE'));
        self::assertSame('utf8mb4_unicode_ci', $this->tableOption('review_invitations', 'TABLE_COLLATION'));
    }

    public function testInvitationConstraintsEnforceHashExpiryAndOnePerBooking(): void
    {
        $this->runner->migrate();
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId);
        self::assertGreaterThan(0, $invitationId);

        $this->assertPdoFailure(fn () => ReviewFixture::insertInvitation($this->connection, $bookingId, [
            'token_hash' => hash('sha256', 'different-token'),
        ]));
        $secondBooking = ReviewFixture::insertBooking($this->connection);
        $this->assertPdoFailure(fn () => ReviewFixture::insertInvitation($this->connection, $secondBooking, [
            'token_hash' => hash('sha256', 'fixture-token-' . $bookingId),
        ]));

        foreach ([
            ['token_hash' => 'raw-review-token'],
            ['token_hash' => strtoupper(hash('sha256', 'uppercase'))],
            ['expires_at' => '2026-08-01 00:00:00'],
            ['used_at' => '2026-07-31 23:59:59'],
            ['used_at' => '2026-08-08 00:00:00'],
        ] as $overrides) {
            $candidate = ReviewFixture::insertBooking($this->connection);
            $this->assertPdoFailure(fn () => ReviewFixture::insertInvitation($this->connection, $candidate, $overrides));
        }

        $this->assertPdoFailure(fn () => $this->connection->exec('DELETE FROM bookings WHERE id = ' . $bookingId));
    }

    public function testReviewConstraintsEnforceVerifiedStayUniquenessRatingAndModerationState(): void
    {
        $this->runner->migrate();
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId);
        $reviewId = ReviewFixture::insertReview($this->connection, $bookingId, $invitationId);
        self::assertGreaterThan(0, $reviewId);
        self::assertSame('pending', $this->connection->query(
            'SELECT review_status FROM reviews WHERE id = ' . $reviewId,
        )->fetchColumn());

        $this->assertPdoFailure(fn () => ReviewFixture::insertReview($this->connection, $bookingId, $invitationId));

        $mismatchedBooking = ReviewFixture::insertBooking($this->connection);
        $this->assertPdoFailure(fn () => ReviewFixture::insertReview(
            $this->connection,
            $mismatchedBooking,
            $invitationId,
        ));

        foreach ([
            ['rating' => 0],
            ['rating' => 6],
            ['guest_name_snapshot' => ' Guest'],
            ['title' => ''],
            ['comment' => ' Review'],
            ['review_status' => 'published'],
            ['review_status' => 'pending', 'moderated_by' => ReviewFixture::insertAdministrator($this->connection), 'moderated_at' => '2026-08-01 01:00:00'],
            ['review_status' => 'approved', 'moderated_by' => null, 'moderated_at' => null],
            ['review_status' => 'rejected', 'moderated_by' => null, 'moderated_at' => null],
            [
                'review_status' => 'approved',
                'moderated_by' => ReviewFixture::insertAdministrator($this->connection),
                'moderated_at' => '2026-07-31 23:59:59',
            ],
            ['updated_at' => '2026-07-31 23:59:59'],
        ] as $overrides) {
            $candidateBooking = ReviewFixture::insertBooking($this->connection);
            $candidateInvitation = ReviewFixture::insertInvitation($this->connection, $candidateBooking);
            $this->assertPdoFailure(fn () => ReviewFixture::insertReview(
                $this->connection,
                $candidateBooking,
                $candidateInvitation,
                $overrides,
            ));
        }
    }

    public function testRollbackRemovesReviewsBeforeInvitationsAndBookings(): void
    {
        $this->runner->migrate();
        self::assertSame(array_reverse(self::APPLICATION_MIGRATIONS), $this->runner->rollbackLastBatch());
        self::assertSame(0, $this->tableCount('reviews'));
        self::assertSame(0, $this->tableCount('review_invitations'));
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

    private function columnDefault(string $table, string $column): string
    {
        $statement = $this->connection->prepare(
            'SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
        );
        $statement->execute(['table_name' => $table, 'column_name' => $column]);
        return trim((string) $statement->fetchColumn(), "'");
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

    private function assertPdoFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('MariaDB unexpectedly accepted invalid review data.');
        } catch (PDOException) {
            self::assertTrue(true);
        }
    }

    private function tableCount(string $table): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
        );
        $statement->execute(['table_name' => $table]);
        return (int) $statement->fetchColumn();
    }
}
