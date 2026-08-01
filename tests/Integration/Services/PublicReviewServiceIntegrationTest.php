<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\ReviewException;
use App\Models\AuditLogEntry;
use App\Models\Review;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoReviewInvitationRepository;
use App\Repositories\PdoReviewRepository;
use App\Requests\ReviewSubmissionRequest;
use App\Services\PdoReviewTransaction;
use App\Services\PublicReviewService;
use App\Services\ReviewTokenHasher;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ReviewFixture;

#[Group('database')]
final class PublicReviewServiceIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
    }

    public function testVerifiedSubmissionCreatesOnePendingReviewConsumesInvitationAndWritesSafeAudit(): void
    {
        $rawToken = str_repeat('ab', 32);
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId, [
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => '2026-08-08 00:00:00',
        ]);
        $service = $this->service(new PdoAuditLogRepository($this->connection));
        $request = ReviewSubmissionRequest::fromInput(
            '5',
            'A warm stay',
            "The room was clean and the team was welcoming.\r\nThank you.",
        );

        $review = $service->submit(
            $rawToken,
            $request,
            '203.0.113.71',
            ' Public Review Integration Test ',
        );

        self::assertSame(Review::STATUS_PENDING, $review->status());
        self::assertSame($bookingId, $review->bookingId());
        self::assertSame($invitationId, $review->reviewInvitationId());
        self::assertSame("The room was clean and the team was welcoming.\nThank you.", $review->comment());
        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
        self::assertSame('2026-08-01 12:00:00', (string) $this->connection->query(
            'SELECT used_at FROM review_invitations WHERE id = ' . $invitationId,
        )->fetchColumn());

        $audit = $this->connection->query(
            "SELECT user_id, event_type, entity_type, entity_id, metadata_json, ip_address, user_agent "
            . "FROM audit_logs WHERE event_type = 'review_submitted' LIMIT 1",
        )->fetch();
        self::assertIsArray($audit);
        self::assertNull($audit['user_id']);
        self::assertSame('review', $audit['entity_type']);
        self::assertSame((string) $review->id(), (string) $audit['entity_id']);
        self::assertSame('203.0.113.71', $audit['ip_address']);
        self::assertSame('Public Review Integration Test', $audit['user_agent']);
        $metadata = json_decode((string) $audit['metadata_json'], true, 16, JSON_THROW_ON_ERROR);
        self::assertSame([
            'booking_id' => $bookingId,
            'review_invitation_id' => $invitationId,
            'review_status' => Review::STATUS_PENDING,
        ], $metadata);
        $encodedAudit = json_encode($audit, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($rawToken, $encodedAudit);
        self::assertStringNotContainsString($request->comment(), $encodedAudit);
        self::assertStringNotContainsString((string) $request->title(), $encodedAudit);

        try {
            $service->submit($rawToken, $request, null, null);
            self::fail('A consumed invitation was accepted a second time.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isUnavailable());
            self::assertSame('This review invitation is unavailable.', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->connection->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
        self::assertSame(1, (int) $this->connection->query(
            "SELECT COUNT(*) FROM audit_logs WHERE event_type = 'review_submitted'",
        )->fetchColumn());
        self::assertFalse($this->connection->inTransaction());
    }

    public function testAuditFailureRollsBackReviewAndInvitationConsumption(): void
    {
        $rawToken = str_repeat('cd', 32);
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId, [
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => '2026-08-08 00:00:00',
        ]);
        $service = $this->service(new PublicReviewFailingAuditRepository());

        try {
            $service->submit(
                $rawToken,
                ReviewSubmissionRequest::fromInput(4, null, 'The stay was comfortable and quiet.'),
                null,
                null,
            );
            self::fail('An audit failure did not roll back the review submission.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
        self::assertNull($this->connection->query(
            'SELECT used_at FROM review_invitations WHERE id = ' . $invitationId,
        )->fetchColumn());
        self::assertFalse($this->connection->inTransaction());
    }

    private function service(AuditLogWriteRepository $auditLogs): PublicReviewService
    {
        return new PublicReviewService(
            new BookingRepository($this->connection),
            new PdoReviewInvitationRepository($this->connection),
            new PdoReviewRepository($this->connection),
            $auditLogs,
            new PdoReviewTransaction($this->connection),
            new ReviewTokenHasher(),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-01 12:00:00 UTC'),
        );
    }
}

final class PublicReviewFailingAuditRepository implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new RuntimeException('sensitive review audit persistence failure');
    }
}
