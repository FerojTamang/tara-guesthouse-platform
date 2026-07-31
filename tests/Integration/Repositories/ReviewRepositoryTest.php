<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Exceptions\ReviewException;
use App\Models\Review;
use App\Repositories\PdoReviewRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ReviewFixture;

#[Group('database')]
final class ReviewRepositoryTest extends DatabaseTestCase
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

    public function testCreatePendingRequiresTransactionAndMapsApprovedFields(): void
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId);
        $createdAt = new DateTimeImmutable('2026-08-01 00:30:00 UTC');

        try {
            $this->repository->createPending(
                $bookingId,
                $invitationId,
                'Verified Stay Guest',
                5,
                null,
                'The stay was excellent and the room was clean.',
                $createdAt,
            );
            self::fail('A review write outside a transaction was accepted.');
        } catch (DatabaseException $exception) {
            self::assertSame('The review write failed.', $exception->getMessage());
        }

        self::assertTrue($this->connection->beginTransaction());
        $review = $this->repository->createPending(
            $bookingId,
            $invitationId,
            'Verified Stay Guest',
            5,
            null,
            'The stay was excellent and the room was clean.',
            $createdAt,
        );
        self::assertTrue($this->connection->commit());

        self::assertInstanceOf(Review::class, $review);
        self::assertSame(Review::STATUS_PENDING, $review->status());
        self::assertNull($review->title());
        self::assertNull($review->moderatedBy());
        self::assertSame($review->id(), $this->repository->findByBookingId($bookingId)?->id());
        self::assertSame($review->id(), $this->repository->findByInvitationId($invitationId)?->id());
        self::assertNull($this->repository->findById(0));
    }

    public function testOneReviewPerBookingAndInvitationIsEnforced(): void
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId);
        $createdAt = new DateTimeImmutable('2026-08-01 00:30:00 UTC');
        self::assertTrue($this->connection->beginTransaction());
        $this->repository->createPending(
            $bookingId,
            $invitationId,
            'Verified Stay Guest',
            4,
            'Comfortable stay',
            'The room was comfortable and the location was convenient.',
            $createdAt,
        );
        self::assertTrue($this->connection->commit());

        self::assertTrue($this->connection->beginTransaction());
        try {
            $this->repository->createPending(
                $bookingId,
                $invitationId,
                'Verified Stay Guest',
                4,
                null,
                'A duplicate review must not be accepted.',
                $createdAt,
            );
            self::fail('A duplicate review was accepted.');
        } catch (ReviewException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        } finally {
            if ($this->connection->inTransaction()) {
                self::assertTrue($this->connection->rollBack());
            }
        }
    }

    public function testLookupFailuresAreSanitized(): void
    {
        $this->connection->exec('DROP TABLE reviews');

        try {
            $this->repository->findById(1);
            self::fail('Expected a sanitized review lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The review lookup failed.', $exception->getMessage());
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }
}
