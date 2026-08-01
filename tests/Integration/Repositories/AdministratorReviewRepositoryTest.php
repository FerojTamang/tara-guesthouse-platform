<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Models\Review;
use App\Repositories\PdoReviewRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ReviewFixture;

#[Group('database')]
final class AdministratorReviewRepositoryTest extends DatabaseTestCase
{
    private PdoReviewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->repository = new PdoReviewRepository($this->connection);
    }

    public function testAdministratorListingFiltersStatusesAndUsesNewestFirstOrder(): void
    {
        $administratorId = ReviewFixture::insertAdministrator($this->connection);
        $ids = [];

        foreach ([
            ['pending', '2026-08-01 01:00:00'],
            ['approved', '2026-08-01 02:00:00'],
            ['rejected', '2026-08-01 03:00:00'],
        ] as [$status, $createdAt]) {
            $bookingId = ReviewFixture::insertBooking($this->connection);
            $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId, [
                'used_at' => $createdAt,
            ]);
            $ids[$status] = ReviewFixture::insertReview(
                $this->connection,
                $bookingId,
                $invitationId,
                [
                    'review_status' => $status,
                    'moderated_by' => $status === 'pending' ? null : $administratorId,
                    'moderated_at' => $status === 'pending' ? null : $createdAt,
                    'moderation_note' => $status === 'rejected' ? 'Rejected safely.' : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );
        }

        self::assertSame(
            [$ids['rejected'], $ids['approved'], $ids['pending']],
            array_map(static fn (Review $review): int => $review->id(), $this->repository->findAll()),
        );
        self::assertSame(
            [$ids['approved']],
            array_map(
                static fn (Review $review): int => $review->id(),
                $this->repository->findAll(Review::STATUS_APPROVED),
            ),
        );
    }

    public function testLockAndPendingTransitionRequireCallerTransactionAndAreSingleUse(): void
    {
        $administratorId = ReviewFixture::insertAdministrator($this->connection);
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId, [
            'used_at' => '2026-08-01 00:30:00',
        ]);
        $reviewId = ReviewFixture::insertReview($this->connection, $bookingId, $invitationId);

        foreach ([
            fn () => $this->repository->lockById($reviewId),
            fn () => $this->repository->transitionPending(
                $reviewId,
                Review::STATUS_REJECTED,
                $administratorId,
                'Unsafe content.',
                new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
            ),
        ] as $operation) {
            try {
                $operation();
                self::fail('Administrator review write operation outside a transaction was accepted.');
            } catch (DatabaseException $exception) {
                self::assertStringContainsString('review', strtolower($exception->getMessage()));
            }
        }

        self::assertTrue($this->connection->beginTransaction());
        $locked = $this->repository->lockById($reviewId);
        self::assertSame(Review::STATUS_PENDING, $locked?->status());
        $rejected = $this->repository->transitionPending(
            $reviewId,
            Review::STATUS_REJECTED,
            $administratorId,
            'Unsupported personal information.',
            new DateTimeImmutable('2026-08-02 00:00:00 UTC'),
        );
        self::assertInstanceOf(Review::class, $rejected);
        self::assertSame(Review::STATUS_REJECTED, $rejected->status());
        self::assertSame($administratorId, $rejected->moderatedBy());
        self::assertSame('Unsupported personal information.', $rejected->moderationNote());
        self::assertTrue($this->connection->commit());

        self::assertTrue($this->connection->beginTransaction());
        self::assertNull($this->repository->transitionPending(
            $reviewId,
            Review::STATUS_APPROVED,
            $administratorId,
            null,
            new DateTimeImmutable('2026-08-02 01:00:00 UTC'),
        ));
        self::assertTrue($this->connection->rollBack());
    }

    public function testAdministratorLookupFailuresAreSanitized(): void
    {
        $this->connection->exec('DROP TABLE reviews');

        foreach ([
            fn () => $this->repository->findAll(),
            function (): void {
                self::assertTrue($this->connection->beginTransaction());
                try {
                    $this->repository->lockById(1);
                } finally {
                    if ($this->connection->inTransaction()) {
                        $this->connection->rollBack();
                    }
                }
            },
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected sanitized Administrator review repository failure.');
            } catch (DatabaseException $exception) {
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }
    }
}
