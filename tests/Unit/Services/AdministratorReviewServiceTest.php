<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ReviewException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Models\ReviewModerationPolicy;
use App\Repositories\AdministratorReviewRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Requests\AdministratorReviewStatusRequest;
use App\Services\AdministratorReviewService;
use App\Services\ReviewTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdministratorReviewServiceTest extends TestCase
{
    private AdministratorReviewRepositoryFake $reviews;
    private AdministratorReviewBookingRepositoryFake $bookings;
    private AdministratorReviewInvitationRepositoryFake $invitations;
    private AdministratorReviewAuditRepositoryFake $audit;
    private AdministratorReviewTransactionFake $transaction;
    private AdministratorReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviews = new AdministratorReviewRepositoryFake();
        $this->bookings = new AdministratorReviewBookingRepositoryFake(self::booking());
        $this->invitations = new AdministratorReviewInvitationRepositoryFake(self::invitation());
        $this->audit = new AdministratorReviewAuditRepositoryFake();
        $this->transaction = new AdministratorReviewTransactionFake();
        $this->service = new AdministratorReviewService(
            $this->reviews,
            $this->bookings,
            $this->invitations,
            $this->audit,
            $this->transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-02 02:00:00 UTC'),
        );
    }

    public function testListAndGetReturnExactVerifiedStayContext(): void
    {
        $pending = self::review();
        $approved = self::review(
            Review::STATUS_APPROVED,
            2,
            new DateTimeImmutable('2026-08-02 01:00:00 UTC'),
            null,
            12,
        );
        $this->reviews->items = [$pending->id() => $pending, $approved->id() => $approved];

        self::assertSame([$pending, $approved], $this->service->list());
        self::assertSame([$pending], $this->service->list(Review::STATUS_PENDING));
        $record = $this->service->get($pending->id());
        self::assertSame($pending, $record->review());
        self::assertTrue($record->isVerifiedStay());
        self::assertTrue($record->canModerate());
    }

    public function testApprovalCommitsAndWritesSafeAuditEvidence(): void
    {
        $this->reviews->items[11] = self::review();
        $request = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_APPROVED,
            'Internal approval note that must not enter audit metadata.',
            true,
            new ReviewModerationPolicy(1000, true),
        );

        $review = $this->service->transition(
            11,
            $request,
            91,
            '203.0.113.10',
            "  Review\tModerator  ",
        );

        self::assertSame(Review::STATUS_APPROVED, $review->status());
        self::assertSame(91, $review->moderatedBy());
        self::assertSame(
            'Internal approval note that must not enter audit metadata.',
            $review->moderationNote(),
        );
        self::assertSame(['begin', 'commit'], $this->transaction->operations);
        self::assertCount(1, $this->audit->entries);
        $entry = $this->audit->entries[0];
        self::assertSame('review_approved', $entry->eventType());
        self::assertSame('review', $entry->entityType());
        self::assertSame(11, $entry->entityId());
        self::assertSame('203.0.113.10', $entry->ipAddress());
        self::assertSame('Review Moderator', $entry->userAgent());
        self::assertSame([
            'booking_id' => 23,
            'review_invitation_id' => 7,
            'from_status' => 'pending',
            'to_status' => 'approved',
        ], json_decode((string) $entry->metadataJson(), true, 16, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('Internal approval note', (string) $entry->metadataJson());
        self::assertStringNotContainsString('Guest comment', (string) $entry->metadataJson());
    }

    public function testRejectionPersistsRequiredNoteAndExactAuditType(): void
    {
        $this->reviews->items[11] = self::review();
        $request = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_REJECTED,
            'The review includes unsupported private information.',
            true,
            new ReviewModerationPolicy(1000, true),
        );

        $review = $this->service->transition(11, $request, 91, null, null);

        self::assertSame(Review::STATUS_REJECTED, $review->status());
        self::assertSame(
            'The review includes unsupported private information.',
            $review->moderationNote(),
        );
        self::assertSame('review_rejected', $this->audit->entries[0]->eventType());
    }

    public function testNonPendingAndInvalidVerifiedStayContextRollBackAsConflict(): void
    {
        $this->reviews->items[11] = self::review(
            Review::STATUS_APPROVED,
            91,
            new DateTimeImmutable('2026-08-02 01:00:00 UTC'),
            null,
        );

        try {
            $this->service->transition(11, self::approval(), 91, null, null);
            self::fail('A completed review was moderated again.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('This review can no longer be moderated.', $exception->getMessage());
        }
        self::assertSame(['begin', 'rollback'], $this->transaction->operations);

        $this->transaction->operations = [];
        $this->reviews->items[11] = self::review();
        $this->bookings->booking = self::booking(Booking::STATUS_CONFIRMED);

        try {
            $this->service->transition(11, self::approval(), 91, null, null);
            self::fail('A review without checked-out eligibility was moderated.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isConflict());
        }
        self::assertSame(['begin', 'rollback'], $this->transaction->operations);
    }

    public function testNotFoundValidationAndExistingTransactionsFailSafely(): void
    {
        try {
            $this->service->get(999);
            self::fail('Missing review was returned.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isNotFound());
        }

        foreach (['unknown', ' pending'] as $filter) {
            try {
                $this->service->list($filter);
                self::fail('Invalid review status filter was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isValidationFailure());
            }
        }

        $this->transaction->active = true;
        try {
            $this->service->transition(11, self::approval(), 91, null, null);
            self::fail('Nested review moderation transaction was accepted.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertNull($exception->getPrevious());
        }
        self::assertSame([], $this->transaction->operations);
    }

    public function testUnexpectedModerationPersistenceResultRollsBackAsConflict(): void
    {
        $this->reviews->items[11] = self::review();
        $this->reviews->dropModerationNote = true;
        $request = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_APPROVED,
            'This note must be persisted exactly.',
            true,
            new ReviewModerationPolicy(1000, true),
        );

        try {
            $this->service->transition(11, $request, 91, null, null);
            self::fail('An inconsistent moderation persistence result was committed.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(['begin', 'rollback'], $this->transaction->operations);
        self::assertSame([], $this->audit->entries);
    }

    public function testAuditFailureRollsBackAndSanitizesOriginalFailure(): void
    {
        $this->reviews->items[11] = self::review();
        $this->audit->failure = new RuntimeException(
            'sensitive moderation note and database details',
        );

        try {
            $this->service->transition(11, self::approval(), 91, null, null);
            self::fail('Audit failure did not roll back moderation.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        self::assertSame(['begin', 'rollback'], $this->transaction->operations);
    }

    private static function approval(): AdministratorReviewStatusRequest
    {
        return AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_APPROVED,
            null,
            false,
            new ReviewModerationPolicy(1000, true),
        );
    }

    private static function review(
        string $status = Review::STATUS_PENDING,
        ?int $moderatedBy = null,
        ?DateTimeImmutable $moderatedAt = null,
        ?string $note = null,
        int $id = 11,
    ): Review {
        $created = new DateTimeImmutable('2026-08-02 00:30:00 UTC');

        return new Review(
            $id,
            23,
            7,
            'Verified Guest',
            5,
            'Warm stay',
            'Guest comment that must not enter audit metadata.',
            $status,
            $moderatedBy,
            $moderatedAt,
            $note,
            $created,
            $moderatedAt ?? $created,
        );
    }

    private static function booking(string $status = Booking::STATUS_CHECKED_OUT): Booking
    {
        return new Booking(
            23,
            'TGH-REVIEW-000023',
            null,
            'Verified Guest',
            'verified@example.test',
            '+9779800000000',
            new DateTimeImmutable('2026-07-20 00:00:00 UTC'),
            new DateTimeImmutable('2026-07-22 00:00:00 UTC'),
            2,
            0,
            Booking::CURRENCY_NPR,
            '5000.00',
            '10000.00',
            $status,
            null,
            new DateTimeImmutable('2026-07-01 00:00:00 UTC'),
            'v1',
            new DateTimeImmutable('2026-07-02 00:00:00 UTC'),
            new DateTimeImmutable('2026-07-01 01:00:00 UTC'),
            $status === Booking::STATUS_CONFIRMED
                ? null
                : new DateTimeImmutable('2026-07-20 06:00:00 UTC'),
            $status === Booking::STATUS_CHECKED_OUT
                ? new DateTimeImmutable('2026-07-22 06:00:00 UTC')
                : null,
            null,
            null,
            null,
            null,
            new DateTimeImmutable('2026-07-01 00:00:00 UTC'),
            new DateTimeImmutable('2026-07-22 06:00:00 UTC'),
            null,
        );
    }

    private static function invitation(): ReviewInvitation
    {
        return new ReviewInvitation(
            7,
            23,
            hash('sha256', 'unit-review-token'),
            new DateTimeImmutable('2026-08-08 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-02 00:30:00 UTC'),
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        );
    }
}

final class AdministratorReviewRepositoryFake implements AdministratorReviewRepository
{
    /** @var array<int, Review> */
    public array $items = [];
    public bool $dropModerationNote = false;

    public function findAll(?string $status = null): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Review $review): bool => $status === null || $review->status() === $status,
        ));
    }

    public function findById(int $id): ?Review
    {
        return $this->items[$id] ?? null;
    }

    public function lockById(int $id): ?Review
    {
        return $this->findById($id);
    }

    public function transitionPending(
        int $reviewId,
        string $targetStatus,
        int $administratorId,
        ?string $moderationNote,
        DateTimeImmutable $moderatedAt,
    ): ?Review {
        $current = $this->items[$reviewId] ?? null;

        if (!$current instanceof Review || $current->status() !== Review::STATUS_PENDING) {
            return null;
        }

        return $this->items[$reviewId] = new Review(
            $current->id(),
            $current->bookingId(),
            $current->reviewInvitationId(),
            $current->guestNameSnapshot(),
            $current->rating(),
            $current->title(),
            $current->comment(),
            $targetStatus,
            $administratorId,
            $moderatedAt,
            $this->dropModerationNote ? null : $moderationNote,
            $current->createdAt(),
            $moderatedAt,
        );
    }
}

final class AdministratorReviewBookingRepositoryFake implements BookingCatalogRepository
{
    public function __construct(public Booking $booking)
    {
    }

    public function findAll(?string $status = null): array
    {
        return [$this->booking];
    }

    public function findById(int $id): ?Booking
    {
        return $id === $this->booking->id() ? $this->booking : null;
    }

    public function findByReference(string $bookingReference): ?Booking
    {
        return $bookingReference === $this->booking->bookingReference() ? $this->booking : null;
    }

    public function findRoomByBookingId(int $bookingId): ?BookingRoom
    {
        return null;
    }

    public function findEventsByBookingId(int $bookingId): array
    {
        return [];
    }
}

final class AdministratorReviewInvitationRepositoryFake implements ReviewInvitationRepository
{
    public function __construct(public ReviewInvitation $invitation)
    {
    }

    public function create(
        int $bookingId,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): ReviewInvitation {
        throw new RuntimeException('Not supported.');
    }

    public function findById(int $id): ?ReviewInvitation
    {
        return $id === $this->invitation->id() ? $this->invitation : null;
    }

    public function findByBookingId(int $bookingId): ?ReviewInvitation
    {
        return $bookingId === $this->invitation->bookingId() ? $this->invitation : null;
    }

    public function findByTokenHash(string $tokenHash): ?ReviewInvitation
    {
        return null;
    }

    public function lockByTokenHash(string $tokenHash): ?ReviewInvitation
    {
        return null;
    }

    public function markUsed(int $invitationId, DateTimeImmutable $usedAt): ?ReviewInvitation
    {
        return null;
    }
}

final class AdministratorReviewAuditRepositoryFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public ?RuntimeException $failure = null;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->failure instanceof RuntimeException) {
            throw $this->failure;
        }

        $this->entries[] = $entry;
    }
}

final class AdministratorReviewTransactionFake implements ReviewTransaction
{
    public bool $active = false;

    /** @var list<string> */
    public array $operations = [];

    public function begin(): void
    {
        if ($this->active) {
            throw new RuntimeException('Transaction already active.');
        }

        $this->active = true;
        $this->operations[] = 'begin';
    }

    public function commit(): void
    {
        if (!$this->active) {
            throw new RuntimeException('No active transaction.');
        }

        $this->active = false;
        $this->operations[] = 'commit';
    }

    public function rollBack(): void
    {
        if (!$this->active) {
            throw new RuntimeException('No active transaction.');
        }

        $this->active = false;
        $this->operations[] = 'rollback';
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
