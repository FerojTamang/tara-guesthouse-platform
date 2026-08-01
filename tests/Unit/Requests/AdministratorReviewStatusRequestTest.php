<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Exceptions\ReviewException;
use App\Models\Review;
use App\Models\ReviewModerationPolicy;
use App\Requests\AdministratorReviewStatusRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministratorReviewStatusRequestTest extends TestCase
{
    private ReviewModerationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ReviewModerationPolicy(1000, true);
    }

    public function testApprovalAllowsOmittedOrNormalizedOptionalNote(): void
    {
        $withoutNote = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_APPROVED,
            null,
            false,
            $this->policy,
        );
        self::assertSame(Review::STATUS_APPROVED, $withoutNote->targetStatus());
        self::assertNull($withoutNote->moderationNote());

        $withNote = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_APPROVED,
            "  Verified against the checked-out stay.\r\nApproved.  ",
            true,
            $this->policy,
        );
        self::assertSame(
            "Verified against the checked-out stay.\nApproved.",
            $withNote->moderationNote(),
        );

        $blank = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_APPROVED,
            '   ',
            true,
            $this->policy,
        );
        self::assertNull($blank->moderationNote());
    }

    public function testRejectionRequiresNormalizedNote(): void
    {
        $request = AdministratorReviewStatusRequest::fromInput(
            Review::STATUS_REJECTED,
            "  The comment contains unsupported personal details.\r\nReject. ",
            true,
            $this->policy,
        );

        self::assertSame(Review::STATUS_REJECTED, $request->targetStatus());
        self::assertSame(
            "The comment contains unsupported personal details.\nReject.",
            $request->moderationNote(),
        );

        foreach ([[null, false], [null, true], ['', true], ['   ', true]] as [$value, $provided]) {
            try {
                AdministratorReviewStatusRequest::fromInput(
                    Review::STATUS_REJECTED,
                    $value,
                    $provided,
                    $this->policy,
                );
                self::fail('A rejection without a moderation note was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isValidationFailure());
                self::assertSame(
                    'A moderation note is required when rejecting a review.',
                    $exception->getMessage(),
                );
            }
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidStatuses(): iterable
    {
        yield 'pending' => [Review::STATUS_PENDING];
        yield 'unknown' => ['published'];
        yield 'uppercase' => ['APPROVED'];
        yield 'whitespace' => [' approved'];
        yield 'empty' => [''];
        yield 'integer' => [1];
        yield 'null' => [null];
    }

    #[DataProvider('invalidStatuses')]
    public function testOnlyApprovedAndRejectedTargetsAreAccepted(mixed $status): void
    {
        try {
            AdministratorReviewStatusRequest::fromInput(
                $status,
                null,
                false,
                $this->policy,
            );
            self::fail('An invalid review moderation target was accepted.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame('Review moderation action is invalid.', $exception->getMessage());
        }
    }

    public function testMalformedOrOversizedNotesFailSafely(): void
    {
        foreach ([123, ['note'], "bad\x00note", "\u{200B}hidden"] as $value) {
            try {
                AdministratorReviewStatusRequest::fromInput(
                    Review::STATUS_APPROVED,
                    $value,
                    true,
                    $this->policy,
                );
                self::fail('Malformed moderation note was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isValidationFailure());
                self::assertSame('Moderation note is invalid.', $exception->getMessage());
            }
        }

        try {
            AdministratorReviewStatusRequest::fromInput(
                Review::STATUS_APPROVED,
                str_repeat('x', 1001),
                true,
                $this->policy,
            );
            self::fail('Oversized moderation note was accepted.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isValidationFailure());
            self::assertSame(
                'Moderation note must not exceed 1000 characters.',
                $exception->getMessage(),
            );
        }
    }
}
