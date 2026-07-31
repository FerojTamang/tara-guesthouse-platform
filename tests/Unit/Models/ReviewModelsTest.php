<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\IssuedReviewInvitation;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Models\ReviewInvitationPolicy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewModelsTest extends TestCase
{
    public function testInvitationAndIssueExposeOnlyValidatedState(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $invitation = new ReviewInvitation(
            1,
            2,
            hash('sha256', str_repeat('a', 64)),
            new DateTimeImmutable('2026-08-08 00:00:00 UTC'),
            null,
            $createdAt,
        );
        $token = str_repeat('a', 64);
        $issue = new IssuedReviewInvitation($invitation, $token);

        self::assertSame($invitation, $issue->invitation());
        self::assertSame($token, $issue->token());
        self::assertFalse($invitation->isUsed());
        self::assertFalse($invitation->isExpiredAt(new DateTimeImmutable('2026-08-07 23:59:59 UTC')));
        self::assertTrue($invitation->isExpiredAt(new DateTimeImmutable('2026-08-08 00:00:00 UTC')));
    }

    public function testPolicyCalculatesConfiguredExpiryWithoutDefaulting(): void
    {
        $policy = new ReviewInvitationPolicy(7200);
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');

        self::assertSame(7200, $policy->lifetimeSeconds());
        self::assertSame('2026-08-01 02:00:00', $policy->expiresAt($createdAt)->format('Y-m-d H:i:s'));
    }

    public function testPendingAndModeratedReviewsValidateState(): void
    {
        $pending = $this->review();
        $approved = $this->review([
            'status' => Review::STATUS_APPROVED,
            'moderatedBy' => 9,
            'moderatedAt' => new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
            'moderationNote' => null,
            'updatedAt' => new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
        ]);

        self::assertFalse($pending->isPublic());
        self::assertTrue($approved->isPublic());
        self::assertSame(5, $approved->rating());
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function invalidModels(): iterable
    {
        yield 'invitation raw token' => [static fn () => new ReviewInvitation(
            1,
            1,
            'raw-token',
            new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
            null,
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        )];
        yield 'invitation expired at creation' => [static fn () => new ReviewInvitation(
            1,
            1,
            hash('sha256', 'token'),
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
            null,
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        )];
        yield 'invalid issue token' => [static fn () => new IssuedReviewInvitation(
            new ReviewInvitation(
                1,
                1,
                hash('sha256', 'token'),
                new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
                null,
                new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
            ),
            'not-hex',
        )];
        yield 'zero lifetime' => [static fn () => new ReviewInvitationPolicy(0)];
        yield 'pending moderation evidence' => [static fn () => new Review(
            1,
            1,
            1,
            'Guest',
            5,
            null,
            'A valid review comment.',
            Review::STATUS_PENDING,
            2,
            new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
            null,
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
        )];
        yield 'approved without moderator' => [static fn () => new Review(
            1,
            1,
            1,
            'Guest',
            5,
            null,
            'A valid review comment.',
            Review::STATUS_APPROVED,
            null,
            null,
            null,
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
            new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        )];
    }

    #[DataProvider('invalidModels')]
    public function testInvalidStateIsRejected(callable $factory): void
    {
        $this->expectException(InvalidArgumentException::class);
        $factory();
    }

    /** @param array<string, mixed> $overrides */
    private function review(array $overrides = []): Review
    {
        $values = array_replace([
            'id' => 1,
            'bookingId' => 2,
            'invitationId' => 3,
            'guestName' => 'Verified Guest',
            'rating' => 5,
            'title' => 'A warm stay',
            'comment' => 'The room was clean and the team was welcoming.',
            'status' => Review::STATUS_PENDING,
            'moderatedBy' => null,
            'moderatedAt' => null,
            'moderationNote' => null,
            'createdAt' => new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
            'updatedAt' => new DateTimeImmutable('2026-08-01 00:00:00 UTC'),
        ], $overrides);

        return new Review(
            $values['id'],
            $values['bookingId'],
            $values['invitationId'],
            $values['guestName'],
            $values['rating'],
            $values['title'],
            $values['comment'],
            $values['status'],
            $values['moderatedBy'],
            $values['moderatedAt'],
            $values['moderationNote'],
            $values['createdAt'],
            $values['updatedAt'],
        );
    }
}
