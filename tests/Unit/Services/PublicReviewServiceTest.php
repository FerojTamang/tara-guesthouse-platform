<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ReviewException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Repositories\ReviewRepository;
use App\Requests\ReviewSubmissionRequest;
use App\Services\PublicReviewService;
use App\Services\ReviewTokenHasher;
use App\Services\ReviewTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicReviewServiceTest extends TestCase
{
    public function testApprovedProjectionContainsOnlySafeDisplayFields(): void
    {
        $reviews = $this->createMock(ReviewRepository::class);
        $reviews->expects(self::once())->method('findAllApproved')->willReturn([
            $this->review(Review::STATUS_APPROVED, 11),
        ]);
        $service = $this->service(reviews: $reviews);

        self::assertSame([[
            'guest_name' => 'Verified Stay Guest',
            'rating' => 5,
            'title' => 'A warm stay',
            'comment' => 'The room was clean and the team was welcoming.',
            'created_at' => '2026-08-01T00:30:00+00:00',
        ]], $service->approved());
    }

    public function testItCreatesOnePendingReviewConsumesInvitationAndAuditsWithoutSecrets(): void
    {
        $token = str_repeat('ab', 32);
        $now = new DateTimeImmutable('2026-08-02 00:00:00 UTC');
        $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
        $invitation = $this->invitation($booking->id(), hash('sha256', $token));
        $used = new ReviewInvitation(
            $invitation->id(),
            $invitation->bookingId(),
            $invitation->tokenHash(),
            $invitation->expiresAt(),
            $now,
            $invitation->createdAt(),
        );
        $created = $this->review(Review::STATUS_PENDING, $invitation->id(), $booking->id(), $now);
        $bookings = $this->createMock(BookingCatalogRepository::class);
        $bookings->expects(self::once())->method('findById')->with($booking->id())->willReturn($booking);
        $invitations = $this->createMock(ReviewInvitationRepository::class);
        $invitations->expects(self::once())->method('lockByTokenHash')->with(hash('sha256', $token))->willReturn($invitation);
        $invitations->expects(self::once())->method('markUsed')->with($invitation->id(), $now)->willReturn($used);
        $reviews = $this->createMock(ReviewRepository::class);
        $reviews->expects(self::once())->method('findByBookingId')->with($booking->id())->willReturn(null);
        $reviews->expects(self::once())->method('findByInvitationId')->with($invitation->id())->willReturn(null);
        $reviews->expects(self::once())->method('createPending')->with(
            $booking->id(),
            $invitation->id(),
            $booking->guestFullName(),
            5,
            'A warm stay',
            'The room was clean and the team was welcoming.',
            $now,
        )->willReturn($created);
        $audit = $this->createMock(AuditLogWriteRepository::class);
        $audit->expects(self::once())->method('append')->willReturnCallback(
            static function (AuditLogEntry $entry) use ($token, $created, $booking, $invitation): void {
                self::assertNull($entry->userId());
                self::assertSame('review_submitted', $entry->eventType());
                self::assertSame('review', $entry->entityType());
                self::assertSame($created->id(), $entry->entityId());
                self::assertSame([
                    'booking_id' => $booking->id(),
                    'review_invitation_id' => $invitation->id(),
                    'review_status' => 'pending',
                ], json_decode((string) $entry->metadataJson(), true));
                self::assertStringNotContainsString($token, (string) $entry->metadataJson());
                self::assertStringNotContainsString('A warm stay', (string) $entry->metadataJson());
                self::assertSame('203.0.113.50', $entry->ipAddress());
                self::assertSame('Public Review Test', $entry->userAgent());
            },
        );
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::once())->method('inProgress')->willReturn(false);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::once())->method('commit');
        $transaction->expects(self::never())->method('rollBack');
        $service = new PublicReviewService(
            $bookings,
            $invitations,
            $reviews,
            $audit,
            $transaction,
            new ReviewTokenHasher(),
            static fn (): DateTimeImmutable => $now,
        );

        $result = $service->submit(
            $token,
            ReviewSubmissionRequest::fromInput(5, 'A warm stay', 'The room was clean and the team was welcoming.'),
            '203.0.113.50',
            'Public Review Test',
        );

        self::assertSame($created, $result);
        self::assertSame(Review::STATUS_PENDING, $result->status());
    }

    public function testInvalidExpiredUsedAndIneligibleInvitationsUseOneGenericFailure(): void
    {
        $now = new DateTimeImmutable('2026-08-08 00:00:00 UTC');
        $token = str_repeat('cd', 32);

        foreach (['invalid-token', 'missing', 'expired', 'used', 'not-checked-out', 'existing-review'] as $case) {
            $bookings = $this->createStub(BookingCatalogRepository::class);
            $invitations = $this->createStub(ReviewInvitationRepository::class);
            $reviews = $this->createStub(ReviewRepository::class);
            $invitation = $this->invitation(23, hash('sha256', $token));

            if ($case === 'missing') {
                $invitations->method('findByTokenHash')->willReturn(null);
            } elseif ($case === 'expired') {
                $invitations->method('findByTokenHash')->willReturn($invitation);
            } elseif ($case === 'used') {
                $invitations->method('findByTokenHash')->willReturn(new ReviewInvitation(
                    $invitation->id(),
                    $invitation->bookingId(),
                    $invitation->tokenHash(),
                    $invitation->expiresAt(),
                    new DateTimeImmutable('2026-08-07 00:00:00 UTC'),
                    $invitation->createdAt(),
                ));
            } else {
                $invitations->method('findByTokenHash')->willReturn($invitation);
                $bookings->method('findById')->willReturn(
                    $this->booking($case === 'not-checked-out' ? Booking::STATUS_CONFIRMED : Booking::STATUS_CHECKED_OUT),
                );
                if ($case === 'existing-review') {
                    $reviews->method('findByBookingId')->willReturn($this->review(Review::STATUS_PENDING, 7));
                }
            }

            $service = $this->service(
                bookings: $bookings,
                invitations: $invitations,
                reviews: $reviews,
                clock: static fn (): DateTimeImmutable => $case === 'expired'
                    ? $now
                    : new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
            );

            try {
                $service->assertInvitationAvailable($case === 'invalid-token' ? 'bad' : $token);
                self::fail('Unavailable invitation was accepted: ' . $case);
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isUnavailable(), $case);
                self::assertSame('This review invitation is unavailable.', $exception->getMessage());
                self::assertStringNotContainsString($token, $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testConcurrentReviewConflictBecomesGenericUnavailableAndRollsBack(): void
    {
        $token = str_repeat('de', 32);
        $now = new DateTimeImmutable('2026-08-02 00:00:00 UTC');
        $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
        $invitation = $this->invitation($booking->id(), hash('sha256', $token));
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findById')->willReturn($booking);
        $invitations = $this->createMock(ReviewInvitationRepository::class);
        $invitations->method('lockByTokenHash')->willReturn($invitation);
        $invitations->expects(self::never())->method('markUsed');
        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findByBookingId')->willReturn(null);
        $reviews->method('findByInvitationId')->willReturn(null);
        $reviews->method('createPending')->willThrowException(
            ReviewException::reviewConflict(new RuntimeException('sensitive database conflict')),
        );
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::exactly(2))->method('inProgress')->willReturnOnConsecutiveCalls(false, true);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::never())->method('commit');
        $transaction->expects(self::once())->method('rollBack');
        $service = new PublicReviewService(
            $bookings,
            $invitations,
            $reviews,
            $this->createStub(AuditLogWriteRepository::class),
            $transaction,
            new ReviewTokenHasher(),
            static fn (): DateTimeImmutable => $now,
        );

        try {
            $service->submit(
                $token,
                ReviewSubmissionRequest::fromInput(5, null, 'A valid review comment.'),
                null,
                null,
            );
            self::fail('A concurrent review conflict was not rejected.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isUnavailable());
            self::assertSame('This review invitation is unavailable.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString($token, $exception->getMessage());
            self::assertStringNotContainsString('database conflict', $exception->getMessage());
        }
    }

    public function testAuditFailureRollsBackAndSanitizesTokenAndGuestText(): void
    {
        $token = str_repeat('ef', 32);
        $now = new DateTimeImmutable('2026-08-02 00:00:00 UTC');
        $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
        $invitation = $this->invitation($booking->id(), hash('sha256', $token));
        $invitations = $this->createStub(ReviewInvitationRepository::class);
        $invitations->method('lockByTokenHash')->willReturn($invitation);
        $invitations->method('markUsed')->willReturn(new ReviewInvitation(
            $invitation->id(),
            $invitation->bookingId(),
            $invitation->tokenHash(),
            $invitation->expiresAt(),
            $now,
            $invitation->createdAt(),
        ));
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findById')->willReturn($booking);
        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findByBookingId')->willReturn(null);
        $reviews->method('findByInvitationId')->willReturn(null);
        $reviews->method('createPending')->willReturn($this->review(Review::STATUS_PENDING, $invitation->id(), $booking->id(), $now));
        $audit = $this->createStub(AuditLogWriteRepository::class);
        $audit->method('append')->willThrowException(new RuntimeException($token . ' sensitive comment'));
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::exactly(2))->method('inProgress')->willReturnOnConsecutiveCalls(false, true);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::never())->method('commit');
        $transaction->expects(self::once())->method('rollBack');
        $service = new PublicReviewService(
            $bookings,
            $invitations,
            $reviews,
            $audit,
            $transaction,
            new ReviewTokenHasher(),
            static fn (): DateTimeImmutable => $now,
        );

        try {
            $service->submit(
                $token,
                ReviewSubmissionRequest::fromInput(5, null, 'sensitive comment'),
                null,
                null,
            );
            self::fail('An audit failure committed a review.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Review workflow operation failed.', $exception->getMessage());
            self::assertStringNotContainsString($token, $exception->getMessage());
            self::assertStringNotContainsString('sensitive comment', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    private function service(
        ?BookingCatalogRepository $bookings = null,
        ?ReviewInvitationRepository $invitations = null,
        ?ReviewRepository $reviews = null,
        ?callable $clock = null,
    ): PublicReviewService {
        return new PublicReviewService(
            $bookings ?? $this->createStub(BookingCatalogRepository::class),
            $invitations ?? $this->createStub(ReviewInvitationRepository::class),
            $reviews ?? $this->createStub(ReviewRepository::class),
            $this->createStub(AuditLogWriteRepository::class),
            $this->createStub(ReviewTransaction::class),
            new ReviewTokenHasher(),
            $clock,
        );
    }

    private function invitation(int $bookingId, string $hash): ReviewInvitation
    {
        return new ReviewInvitation(
            7,
            $bookingId,
            $hash,
            new DateTimeImmutable('2026-08-08 00:00:00 UTC'),
            null,
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        );
    }

    private function review(
        string $status,
        int $invitationId,
        int $bookingId = 23,
        ?DateTimeImmutable $createdAt = null,
    ): Review {
        $createdAt ??= new DateTimeImmutable('2026-08-01 00:30:00 UTC');
        $moderatedAt = $status === Review::STATUS_PENDING
            ? null
            : new DateTimeImmutable('2026-08-01 01:00:00 UTC');

        return new Review(
            11,
            $bookingId,
            $invitationId,
            'Verified Stay Guest',
            5,
            'A warm stay',
            'The room was clean and the team was welcoming.',
            $status,
            $status === Review::STATUS_PENDING ? null : 3,
            $moderatedAt,
            null,
            $createdAt,
            $moderatedAt ?? $createdAt,
        );
    }

    private function booking(string $status): Booking
    {
        return new Booking(
            23,
            'TGH-REVIEW-000023',
            null,
            'Verified Stay Guest',
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
            $status === Booking::STATUS_PENDING ? null : new DateTimeImmutable('2026-07-01 01:00:00 UTC'),
            in_array($status, [Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT], true)
                ? new DateTimeImmutable('2026-07-20 06:00:00 UTC')
                : null,
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
}
