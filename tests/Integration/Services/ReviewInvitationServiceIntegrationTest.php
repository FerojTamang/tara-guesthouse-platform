<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\ReviewException;
use App\Models\ReviewInvitationPolicy;
use App\Repositories\BookingRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoReviewInvitationRepository;
use App\Repositories\PdoReviewRepository;
use App\Services\PdoReviewTransaction;
use App\Services\ReviewInvitationService;
use App\Services\ReviewTokenHasher;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ReviewFixture;

#[Group('database')]
final class ReviewInvitationServiceIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
    }

    public function testCheckedOutBookingCreatesHashedInvitationAndSafeAuditAtomically(): void
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $administratorId = ReviewFixture::insertAdministrator($this->connection);
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $rawToken = str_repeat('ef', 32);
        $service = $this->service($createdAt, "\xEF");

        $issue = $service->issueForBooking(
            $bookingId,
            $administratorId,
            '127.0.0.1',
            'Integration review agent',
        );

        self::assertSame($rawToken, $issue->token());
        self::assertSame(hash('sha256', $rawToken), $issue->invitation()->tokenHash());
        self::assertSame('2026-08-01 01:00:00', $issue->invitation()->expiresAt()->format('Y-m-d H:i:s'));
        $row = $this->connection->query(
            'SELECT token_hash, expires_at, used_at FROM review_invitations WHERE booking_id = ' . $bookingId,
        )->fetch();
        self::assertIsArray($row);
        self::assertSame(hash('sha256', $rawToken), $row['token_hash']);
        self::assertNotSame($rawToken, $row['token_hash']);
        self::assertNull($row['used_at']);

        $audit = $this->connection->query(
            "SELECT event_type, entity_type, entity_id, metadata_json FROM audit_logs "
            . "WHERE event_type = 'review_invitation_created' LIMIT 1",
        )->fetch();
        self::assertIsArray($audit);
        self::assertSame('review_invitation', $audit['entity_type']);
        self::assertSame((string) $issue->invitation()->id(), (string) $audit['entity_id']);
        self::assertStringNotContainsString($rawToken, (string) $audit['metadata_json']);
        self::assertSame($bookingId, json_decode((string) $audit['metadata_json'], true)['booking_id']);
    }

    public function testNonCheckedOutBookingAndDuplicateInvitationRollBackSafely(): void
    {
        $administratorId = ReviewFixture::insertAdministrator($this->connection);
        $confirmedBooking = ReviewFixture::insertBooking($this->connection, [
            'booking_status' => 'confirmed',
            'checked_in_at' => null,
            'checked_out_at' => null,
            'updated_at' => '2026-07-01 01:00:00',
        ]);
        $service = $this->service(new DateTimeImmutable('2026-08-01 00:00:00 UTC'), "\x11");

        try {
            $service->issueForBooking($confirmedBooking, $administratorId, null, null);
            self::fail('A non-checked-out booking received an invitation.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isEligibilityFailure());
        }
        self::assertSame('0', (string) $this->connection->query(
            'SELECT COUNT(*) FROM review_invitations WHERE booking_id = ' . $confirmedBooking,
        )->fetchColumn());

        $checkedOutBooking = ReviewFixture::insertBooking($this->connection);
        ReviewFixture::insertInvitation($this->connection, $checkedOutBooking);

        try {
            $service->issueForBooking($checkedOutBooking, $administratorId, null, null);
            self::fail('A duplicate invitation was created.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isConflict());
        }
        self::assertSame('1', (string) $this->connection->query(
            'SELECT COUNT(*) FROM review_invitations WHERE booking_id = ' . $checkedOutBooking,
        )->fetchColumn());
    }

    public function testAuditForeignKeyFailureRollsBackInvitation(): void
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $service = $this->service(new DateTimeImmutable('2026-08-01 00:00:00 UTC'), "\x22");

        try {
            $service->issueForBooking($bookingId, 999999, null, null);
            self::fail('An audit failure did not roll back the invitation.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
        }

        self::assertSame('0', (string) $this->connection->query(
            'SELECT COUNT(*) FROM review_invitations WHERE booking_id = ' . $bookingId,
        )->fetchColumn());
        self::assertFalse($this->connection->inTransaction());
    }

    private function service(DateTimeImmutable $createdAt, string $byte): ReviewInvitationService
    {
        return new ReviewInvitationService(
            new BookingRepository($this->connection),
            new PdoReviewInvitationRepository($this->connection),
            new PdoReviewRepository($this->connection),
            new PdoAuditLogRepository($this->connection),
            new PdoReviewTransaction($this->connection),
            new ReviewTokenHasher(static fn (int $length): string => str_repeat($byte, $length)),
            new ReviewInvitationPolicy(3600),
            static fn (): DateTimeImmutable => $createdAt,
        );
    }
}
