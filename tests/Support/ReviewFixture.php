<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/** Inserts deterministic verified-stay review fixtures. */
final class ReviewFixture
{
    /** @param array<string, int|string|null> $overrides */
    public static function insertBooking(PDO $connection, array $overrides = []): int
    {
        $suffix = substr(hash('sha256', serialize($overrides) . microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
        $values = array_replace([
            'booking_reference' => 'TGH-REV-' . strtoupper($suffix),
            'user_id' => null,
            'guest_full_name' => 'Verified Stay Guest',
            'guest_email' => 'verified-' . $suffix . '@example.test',
            'guest_phone' => '+9779800000000',
            'check_in' => '2026-07-20',
            'check_out' => '2026-07-22',
            'adults' => 2,
            'children' => 0,
            'currency' => 'NPR',
            'room_amount' => '5000.00',
            'total_amount' => '10000.00',
            'booking_status' => 'checked_out',
            'special_request' => null,
            'terms_accepted_at' => '2026-07-01 00:00:00',
            'policy_version' => 'v1',
            'confirmation_deadline_at' => '2026-07-02 00:00:00',
            'confirmed_at' => '2026-07-01 01:00:00',
            'checked_in_at' => '2026-07-20 06:00:00',
            'checked_out_at' => '2026-07-22 06:00:00',
            'created_at' => '2026-07-01 00:00:00',
            'updated_at' => '2026-07-22 06:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO bookings ('
            . 'booking_reference, user_id, guest_full_name, guest_email, guest_phone, check_in, check_out, '
            . 'adults, children, currency, room_amount, total_amount, booking_status, special_request, '
            . 'terms_accepted_at, policy_version, confirmation_deadline_at, confirmed_at, checked_in_at, '
            . 'checked_out_at, created_at, updated_at'
            . ') VALUES ('
            . ':booking_reference, :user_id, :guest_full_name, :guest_email, :guest_phone, :check_in, :check_out, '
            . ':adults, :children, :currency, :room_amount, :total_amount, :booking_status, :special_request, '
            . ':terms_accepted_at, :policy_version, :confirmation_deadline_at, :confirmed_at, :checked_in_at, '
            . ':checked_out_at, :created_at, :updated_at)'
        );
        $statement->execute($values);

        return (int) $connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    public static function insertInvitation(PDO $connection, int $bookingId, array $overrides = []): int
    {
        $values = array_replace([
            'booking_id' => $bookingId,
            'token_hash' => hash('sha256', 'fixture-token-' . $bookingId),
            'expires_at' => '2026-08-08 00:00:00',
            'used_at' => null,
            'created_at' => '2026-08-01 00:00:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO review_invitations '
            . '(booking_id, token_hash, expires_at, used_at, created_at) '
            . 'VALUES (:booking_id, :token_hash, :expires_at, :used_at, :created_at)'
        );
        $statement->execute($values);

        return (int) $connection->lastInsertId();
    }

    /** @param array<string, int|string|null> $overrides */
    public static function insertReview(
        PDO $connection,
        int $bookingId,
        int $invitationId,
        array $overrides = [],
    ): int {
        $values = array_replace([
            'booking_id' => $bookingId,
            'review_invitation_id' => $invitationId,
            'guest_name_snapshot' => 'Verified Stay Guest',
            'rating' => 5,
            'title' => 'A warm stay',
            'comment' => 'The room was clean and the team was welcoming.',
            'review_status' => 'pending',
            'moderated_by' => null,
            'moderated_at' => null,
            'moderation_note' => null,
            'created_at' => '2026-08-01 00:30:00',
            'updated_at' => '2026-08-01 00:30:00',
        ], $overrides);
        $statement = $connection->prepare(
            'INSERT INTO reviews ('
            . 'booking_id, review_invitation_id, guest_name_snapshot, rating, title, comment, review_status, '
            . 'moderated_by, moderated_at, moderation_note, created_at, updated_at'
            . ') VALUES ('
            . ':booking_id, :review_invitation_id, :guest_name_snapshot, :rating, :title, :comment, :review_status, '
            . ':moderated_by, :moderated_at, :moderation_note, :created_at, :updated_at)'
        );
        $statement->execute($values);

        return (int) $connection->lastInsertId();
    }

    public static function insertAdministrator(PDO $connection): int
    {
        $connection->exec(
            "INSERT INTO roles (role_name, description) VALUES ('Administrator', 'Review fixture') "
            . "ON DUPLICATE KEY UPDATE role_name = VALUES(role_name)"
        );
        $roleId = (int) $connection->query(
            "SELECT id FROM roles WHERE role_name = 'Administrator' LIMIT 1"
        )->fetchColumn();
        $suffix = bin2hex(random_bytes(4));
        $statement = $connection->prepare(
            'INSERT INTO users '
            . '(role_id, full_name, username, email, password, account_status) '
            . "VALUES (:role_id, 'Review Moderator', :username, :email, :password, 'active')"
        );
        $statement->execute([
            'role_id' => $roleId,
            'username' => 'review.mod.' . $suffix,
            'email' => 'review.mod.' . $suffix . '@example.test',
            'password' => password_hash('Review-Test-Password-123!', PASSWORD_DEFAULT),
        ]);

        return (int) $connection->lastInsertId();
    }
}
