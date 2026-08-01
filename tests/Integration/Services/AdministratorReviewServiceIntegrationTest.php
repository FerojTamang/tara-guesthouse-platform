<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Core\MigrationRunner;
use App\Exceptions\ReviewException;
use App\Models\AuditLogEntry;
use App\Models\Review;
use App\Models\ReviewModerationPolicy;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingRepository;
use App\Repositories\PdoAuditLogRepository;
use App\Repositories\PdoReviewInvitationRepository;
use App\Repositories\PdoReviewRepository;
use App\Requests\AdministratorReviewStatusRequest;
use App\Services\AdministratorReviewService;
use App\Services\PdoReviewTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ReviewFixture;

#[Group('database')]
final class AdministratorReviewServiceIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
    }

    public function testApprovalPersistsModeratorAndSafeAuditAtomically(): void
    {
        $administratorId = ReviewFixture::insertAdministrator($this->connection);
        [$bookingId, $invitationId, $reviewId] = $this->pendingReview();
        $service = $this->service(new DateTimeImmutable('2026-08-02 02:00:00 UTC'));

        $review = $service->transition(
            $reviewId,
            AdministratorReviewStatusRequest::fromInput(
                Review::STATUS_APPROVED,
                'Approval note retained on review only.',
                true,
                new ReviewModerationPolicy(1000, true),
            ),
            $administratorId,
            '127.0.0.1',
            'Integration moderation agent',
        );

        self::assertSame(Review::STATUS_APPROVED, $review->status());
        $row = $this->connection->query(
            'SELECT review_status, moderated_by, moderated_at, moderation_note '
            . 'FROM reviews WHERE id = ' . $reviewId,
        )->fetch();
        self::assertIsArray($row);
        self::assertSame('approved', $row['review_status']);
        self::assertSame((string) $administratorId, (string) $row['moderated_by']);
        self::assertSame('2026-08-02 02:00:00', $row['moderated_at']);
        self::assertSame('Approval note retained on review only.', $row['moderation_note']);

        $audit = $this->connection->query(
            "SELECT event_type, metadata_json FROM audit_logs "
            . "WHERE event_type = 'review_approved' AND entity_id = " . $reviewId . ' LIMIT 1',
        )->fetch();
        self::assertIsArray($audit);
        self::assertSame('review_approved', $audit['event_type']);
        $metadata = json_decode((string) $audit['metadata_json'], true, 16, JSON_THROW_ON_ERROR);
        self::assertSame($bookingId, $metadata['booking_id']);
        self::assertSame($invitationId, $metadata['review_invitation_id']);
        self::assertSame('pending', $metadata['from_status']);
        self::assertSame('approved', $metadata['to_status']);
        self::assertStringNotContainsString('Approval note', (string) $audit['metadata_json']);
        self::assertSame(
            [$reviewId],
            array_map(
                static fn (Review $approved): int => $approved->id(),
                (new PdoReviewRepository($this->connection))->findAllApproved(),
            ),
        );
        self::assertFalse($this->connection->inTransaction());
    }

    public function testCompletedReviewCannotBeModeratedAgain(): void
    {
        $administratorId = ReviewFixture::insertAdministrator($this->connection);
        [, , $reviewId] = $this->pendingReview();
        $service = $this->service(new DateTimeImmutable('2026-08-02 02:00:00 UTC'));
        $request = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_REJECTED,
            'Rejected for approved policy reasons.',
            true,
            new ReviewModerationPolicy(1000, true),
        );

        $service->transition($reviewId, $request, $administratorId, null, null);

        try {
            $service->transition($reviewId, $request, $administratorId, null, null);
            self::fail('A completed review was moderated again.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertSame('rejected', (string) $this->connection->query(
            'SELECT review_status FROM reviews WHERE id = ' . $reviewId,
        )->fetchColumn());
        self::assertFalse($this->connection->inTransaction());
    }

    public function testAuditFailureAfterReviewUpdateRollsBackReviewState(): void
    {
        $administratorId = ReviewFixture::insertAdministrator($this->connection);
        [, , $reviewId] = $this->pendingReview();
        $service = $this->service(
            new DateTimeImmutable('2026-08-02 02:00:00 UTC'),
            new FailingAdministratorReviewAuditRepository(),
        );

        try {
            $service->transition(
                $reviewId,
                AdministratorReviewStatusRequest::fromInput(
                    Review::STATUS_APPROVED,
                    null,
                    false,
                    new ReviewModerationPolicy(1000, true),
                ),
                $administratorId,
                null,
                null,
            );
            self::fail('Audit failure did not roll back review moderation.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        $row = $this->connection->query(
            'SELECT review_status, moderated_by, moderated_at, moderation_note '
            . 'FROM reviews WHERE id = ' . $reviewId,
        )->fetch();
        self::assertIsArray($row);
        self::assertSame('pending', $row['review_status']);
        self::assertNull($row['moderated_by']);
        self::assertNull($row['moderated_at']);
        self::assertNull($row['moderation_note']);
        self::assertFalse($this->connection->inTransaction());
    }

    /** @return array{int, int, int} */
    private function pendingReview(): array
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId, [
            'used_at' => '2026-08-01 00:30:00',
        ]);
        $reviewId = ReviewFixture::insertReview($this->connection, $bookingId, $invitationId);

        return [$bookingId, $invitationId, $reviewId];
    }

    private function service(
        DateTimeImmutable $clock,
        ?AuditLogWriteRepository $auditLogs = null,
    ): AdministratorReviewService {
        return new AdministratorReviewService(
            new PdoReviewRepository($this->connection),
            new BookingRepository($this->connection),
            new PdoReviewInvitationRepository($this->connection),
            $auditLogs ?? new PdoAuditLogRepository($this->connection),
            new PdoReviewTransaction($this->connection),
            static fn (): DateTimeImmutable => $clock,
        );
    }
}

final class FailingAdministratorReviewAuditRepository implements AuditLogWriteRepository
{
    public function append(AuditLogEntry $entry): void
    {
        throw new \RuntimeException('sensitive audit storage failure');
    }
}
