<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ReviewException;
use App\Models\AuditLogEntry;
use App\Models\Booking;
use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationPolicy;
use App\Repositories\AuditLogWriteRepository;
use App\Repositories\BookingCatalogRepository;
use App\Repositories\ReviewInvitationRepository;
use App\Repositories\ReviewRepository;
use App\Services\ReviewInvitationService;
use App\Services\ReviewTokenHasher;
use App\Services\ReviewTransaction;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReviewInvitationServiceTest extends TestCase
{
    public function testIssuesHashedInvitationAndSafeAuditInsideOneTransaction(): void
    {
        $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
        $rawToken = str_repeat('ab', 32);
        $hash = hash('sha256', $rawToken);
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $expiresAt = new DateTimeImmutable('2026-08-08 00:00:00 UTC');
        $stored = new ReviewInvitation(7, $booking->id(), $hash, $expiresAt, null, $createdAt);

        $bookings = $this->createMock(BookingCatalogRepository::class);
        $bookings->expects(self::once())->method('findById')->with($booking->id())->willReturn($booking);
        $reviews = $this->createMock(ReviewRepository::class);
        $reviews->expects(self::once())->method('findByBookingId')->with($booking->id())->willReturn(null);
        $invitations = $this->createMock(ReviewInvitationRepository::class);
        $invitations->expects(self::once())->method('findByBookingId')->with($booking->id())->willReturn(null);
        $invitations->expects(self::once())->method('create')->with(
            $booking->id(),
            $hash,
            $expiresAt,
            $createdAt,
        )->willReturn($stored);
        $audit = $this->createMock(AuditLogWriteRepository::class);
        $audit->expects(self::once())->method('append')->willReturnCallback(
            static function (AuditLogEntry $entry) use ($rawToken, $booking, $expiresAt): void {
                self::assertSame(9, $entry->userId());
                self::assertSame('review_invitation_created', $entry->eventType());
                self::assertSame('review_invitation', $entry->entityType());
                self::assertSame(7, $entry->entityId());
                self::assertStringNotContainsString($rawToken, (string) $entry->metadataJson());
                self::assertSame([
                    'booking_id' => $booking->id(),
                    'expires_at' => $expiresAt->format(DATE_ATOM),
                ], json_decode((string) $entry->metadataJson(), true));
                self::assertSame('127.0.0.1', $entry->ipAddress());
                self::assertSame('Review test agent', $entry->userAgent());
            },
        );
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::once())->method('inProgress')->willReturn(false);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::once())->method('commit');
        $transaction->expects(self::never())->method('rollBack');
        $tokens = new ReviewTokenHasher(static fn (int $length): string => str_repeat("\xAB", $length));
        $service = new ReviewInvitationService(
            $bookings,
            $invitations,
            $reviews,
            $audit,
            $transaction,
            $tokens,
            new ReviewInvitationPolicy(604800),
            static fn (): DateTimeImmutable => $createdAt,
        );

        $issue = $service->issueForBooking($booking->id(), 9, '127.0.0.1', 'Review test agent');

        self::assertSame($stored, $issue->invitation());
        self::assertSame($rawToken, $issue->token());
        self::assertNotSame($stored->tokenHash(), $issue->token());
    }

    public function testNonCheckedOutBookingRollsBackWithoutGeneratingToken(): void
    {
        $booking = $this->booking(Booking::STATUS_CONFIRMED);
        $bookings = $this->createMock(BookingCatalogRepository::class);
        $bookings->expects(self::once())->method('findById')->willReturn($booking);
        $invitations = $this->createMock(ReviewInvitationRepository::class);
        $invitations->expects(self::never())->method('findByBookingId');
        $invitations->expects(self::never())->method('create');
        $reviews = $this->createMock(ReviewRepository::class);
        $reviews->expects(self::never())->method('findByBookingId');
        $audit = $this->createMock(AuditLogWriteRepository::class);
        $audit->expects(self::never())->method('append');
        $transaction = $this->rollbackTransaction();
        $tokens = new ReviewTokenHasher(static fn (int $length): never => throw new RuntimeException('token must not be generated'));
        $service = new ReviewInvitationService(
            $bookings,
            $invitations,
            $reviews,
            $audit,
            $transaction,
            $tokens,
            new ReviewInvitationPolicy(3600),
        );

        try {
            $service->issueForBooking($booking->id(), 9, null, null);
            self::fail('A non-checked-out booking received an invitation.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isEligibilityFailure());
        }
    }

    public function testExistingInvitationOrReviewFailsBeforeSecretGeneration(): void
    {
        foreach (['invitation', 'review'] as $existing) {
            $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
            $bookings = $this->createMock(BookingCatalogRepository::class);
            $bookings->expects(self::once())->method('findById')->willReturn($booking);
            $reviews = $this->createMock(ReviewRepository::class);
            $invitations = $this->createMock(ReviewInvitationRepository::class);
            $audit = $this->createMock(AuditLogWriteRepository::class);
            $audit->expects(self::never())->method('append');

            if ($existing === 'review') {
                $review = new \App\Models\Review(
                    8,
                    $booking->id(),
                    4,
                    'Verified Stay Guest',
                    5,
                    null,
                    'An existing verified-stay review.',
                    \App\Models\Review::STATUS_PENDING,
                    null,
                    null,
                    null,
                    new DateTimeImmutable('2026-08-01 00:30:00 UTC'),
                    new DateTimeImmutable('2026-08-01 00:30:00 UTC'),
                );
                $reviews->expects(self::once())->method('findByBookingId')->willReturn($review);
                $invitations->expects(self::never())->method('findByBookingId');
            } else {
                $reviews->expects(self::once())->method('findByBookingId')->willReturn(null);
                $invitations->expects(self::once())->method('findByBookingId')->willReturn(
                    new ReviewInvitation(
                        4,
                        $booking->id(),
                        hash('sha256', 'existing'),
                        new DateTimeImmutable('2026-08-08 00:00:00 UTC'),
                        null,
                        new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
                    ),
                );
            }

            $invitations->expects(self::never())->method('create');
            $service = new ReviewInvitationService(
                $bookings,
                $invitations,
                $reviews,
                $audit,
                $this->rollbackTransaction(),
                new ReviewTokenHasher(static fn (int $length): never => throw new RuntimeException('secret generated')),
                new ReviewInvitationPolicy(3600),
            );

            try {
                $service->issueForBooking($booking->id(), 9, null, null);
                self::fail('Duplicate verified-stay state was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isConflict());
            }
        }
    }

    public function testCurrentTransactionCreatesInvitationWithoutNestedTransaction(): void
    {
        $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $rawToken = str_repeat('34', 32);
        $hash = hash('sha256', $rawToken);
        $stored = new ReviewInvitation(
            12,
            $booking->id(),
            $hash,
            new DateTimeImmutable('2026-08-01 01:00:00 UTC'),
            null,
            $createdAt,
        );
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findById')->willReturn($booking);
        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findByBookingId')->willReturn(null);
        $invitations = $this->createMock(ReviewInvitationRepository::class);
        $invitations->expects(self::once())->method('findByBookingId')->willReturn(null);
        $invitations->expects(self::once())->method('create')->willReturn($stored);
        $audit = $this->createMock(AuditLogWriteRepository::class);
        $audit->expects(self::once())->method('append');
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::once())->method('inProgress')->willReturn(true);
        $transaction->expects(self::never())->method('begin');
        $transaction->expects(self::never())->method('commit');
        $transaction->expects(self::never())->method('rollBack');
        $service = new ReviewInvitationService(
            $bookings,
            $invitations,
            $reviews,
            $audit,
            $transaction,
            new ReviewTokenHasher(static fn (int $length): string => str_repeat("\x34", $length)),
            new ReviewInvitationPolicy(3600),
            static fn (): DateTimeImmutable => $createdAt,
        );

        $issued = $service->issueForBookingIfAbsentInCurrentTransaction(
            $booking->id(),
            9,
            null,
            null,
        );

        self::assertInstanceOf(\App\Models\IssuedReviewInvitation::class, $issued);
        self::assertSame($rawToken, $issued->token());
        self::assertSame($stored, $issued->invitation());
    }

    public function testCurrentTransactionTreatsExistingInvitationAsIdempotentNoOp(): void
    {
        $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
        $existing = new ReviewInvitation(
            14,
            $booking->id(),
            hash('sha256', 'existing'),
            new DateTimeImmutable('2026-08-08 00:00:00 UTC'),
            null,
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        );
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findById')->willReturn($booking);
        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findByBookingId')->willReturn(null);
        $invitations = $this->createMock(ReviewInvitationRepository::class);
        $invitations->expects(self::once())->method('findByBookingId')->willReturn($existing);
        $invitations->expects(self::never())->method('create');
        $audit = $this->createMock(AuditLogWriteRepository::class);
        $audit->expects(self::never())->method('append');
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::once())->method('inProgress')->willReturn(true);
        $service = new ReviewInvitationService(
            $bookings,
            $invitations,
            $reviews,
            $audit,
            $transaction,
            new ReviewTokenHasher(static fn (int $length): never => throw new RuntimeException('token generated')),
            new ReviewInvitationPolicy(3600),
        );

        self::assertNull($service->issueForBookingIfAbsentInCurrentTransaction(
            $booking->id(),
            9,
            null,
            null,
        ));
    }

    public function testCurrentTransactionEntryPointFailsClosedWithoutCallerTransaction(): void
    {
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::once())->method('inProgress')->willReturn(false);
        $service = new ReviewInvitationService(
            $this->createStub(BookingCatalogRepository::class),
            $this->createStub(ReviewInvitationRepository::class),
            $this->createStub(ReviewRepository::class),
            $this->createStub(AuditLogWriteRepository::class),
            $transaction,
            new ReviewTokenHasher(),
            new ReviewInvitationPolicy(3600),
        );

        try {
            $service->issueForBookingIfAbsentInCurrentTransaction(23, 9, null, null);
            self::fail('A missing caller transaction was accepted.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
        }
    }

    public function testAuditFailureRollsBackAndDoesNotExposeRawToken(): void
    {
        $booking = $this->booking(Booking::STATUS_CHECKED_OUT);
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $rawToken = str_repeat('cd', 32);
        $hash = hash('sha256', $rawToken);
        $bookings = $this->createStub(BookingCatalogRepository::class);
        $bookings->method('findById')->willReturn($booking);
        $reviews = $this->createStub(ReviewRepository::class);
        $reviews->method('findByBookingId')->willReturn(null);
        $invitations = $this->createMock(ReviewInvitationRepository::class);
        $invitations->method('findByBookingId')->willReturn(null);
        $invitations->expects(self::once())->method('create')->willReturn(new ReviewInvitation(
            7,
            $booking->id(),
            $hash,
            new DateTimeImmutable('2026-08-01 01:00:00 UTC'),
            null,
            $createdAt,
        ));
        $audit = $this->createMock(AuditLogWriteRepository::class);
        $audit->expects(self::once())->method('append')->willThrowException(new RuntimeException($rawToken));
        $service = new ReviewInvitationService(
            $bookings,
            $invitations,
            $reviews,
            $audit,
            $this->rollbackTransaction(),
            new ReviewTokenHasher(static fn (int $length): string => str_repeat("\xCD", $length)),
            new ReviewInvitationPolicy(3600),
            static fn (): DateTimeImmutable => $createdAt,
        );

        try {
            $service->issueForBooking($booking->id(), 9, null, null);
            self::fail('An audit failure did not abort invitation creation.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertStringNotContainsString($rawToken, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    private function rollbackTransaction(): ReviewTransaction
    {
        $transaction = $this->createMock(ReviewTransaction::class);
        $transaction->expects(self::exactly(2))->method('inProgress')->willReturnOnConsecutiveCalls(false, true);
        $transaction->expects(self::once())->method('begin');
        $transaction->expects(self::never())->method('commit');
        $transaction->expects(self::once())->method('rollBack');

        return $transaction;
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
