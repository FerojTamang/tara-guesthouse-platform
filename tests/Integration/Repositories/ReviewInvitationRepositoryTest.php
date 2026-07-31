<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\DatabaseException;
use App\Exceptions\ReviewException;
use App\Models\ReviewInvitation;
use App\Repositories\PdoReviewInvitationRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\DatabaseTestCase;
use Tests\Support\ReviewFixture;

#[Group('database')]
final class ReviewInvitationRepositoryTest extends DatabaseTestCase
{
    private PdoReviewInvitationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->repository = new PdoReviewInvitationRepository($this->connection);
    }

    public function testCreateRequiresCallerTransactionAndStoresOnlyHash(): void
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $hash = hash('sha256', str_repeat('a', 64));
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $expiresAt = new DateTimeImmutable('2026-08-08 00:00:00 UTC');

        try {
            $this->repository->create($bookingId, $hash, $expiresAt, $createdAt);
            self::fail('An invitation write outside a transaction was accepted.');
        } catch (DatabaseException $exception) {
            self::assertSame('The review invitation write failed.', $exception->getMessage());
        }

        self::assertTrue($this->connection->beginTransaction());
        $invitation = $this->repository->create($bookingId, $hash, $expiresAt, $createdAt);
        self::assertTrue($this->connection->commit());

        self::assertInstanceOf(ReviewInvitation::class, $invitation);
        self::assertSame($bookingId, $invitation->bookingId());
        self::assertSame($hash, $invitation->tokenHash());
        self::assertNull($invitation->usedAt());
        self::assertSame($invitation->id(), $this->repository->findByBookingId($bookingId)?->id());
        self::assertSame($invitation->id(), $this->repository->findByTokenHash($hash)?->id());
        self::assertNull($this->repository->findByTokenHash('raw-token'));
    }

    public function testOnePerBookingAndUniqueHashConflictsAreSanitized(): void
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $otherBookingId = ReviewFixture::insertBooking($this->connection);
        $createdAt = new DateTimeImmutable('2026-08-01 00:00:00 UTC');
        $expiresAt = new DateTimeImmutable('2026-08-08 00:00:00 UTC');
        $hash = hash('sha256', str_repeat('b', 64));
        self::assertTrue($this->connection->beginTransaction());
        $this->repository->create($bookingId, $hash, $expiresAt, $createdAt);
        self::assertTrue($this->connection->commit());

        foreach ([
            [$bookingId, hash('sha256', str_repeat('c', 64))],
            [$otherBookingId, $hash],
        ] as [$candidateBooking, $candidateHash]) {
            self::assertTrue($this->connection->beginTransaction());
            try {
                $this->repository->create($candidateBooking, $candidateHash, $expiresAt, $createdAt);
                self::fail('A duplicate review invitation was accepted.');
            } catch (ReviewException $exception) {
                self::assertTrue($exception->isConflict());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            } finally {
                if ($this->connection->inTransaction()) {
                    self::assertTrue($this->connection->rollBack());
                }
            }
        }
    }

    public function testMarkUsedIsTransactionalSingleUseAndExpiryAware(): void
    {
        $bookingId = ReviewFixture::insertBooking($this->connection);
        $invitationId = ReviewFixture::insertInvitation($this->connection, $bookingId);

        try {
            $this->repository->markUsed($invitationId, new DateTimeImmutable('2026-08-02 00:00:00 UTC'));
            self::fail('An invitation was consumed outside a transaction.');
        } catch (DatabaseException $exception) {
            self::assertSame('The review invitation use update failed.', $exception->getMessage());
        }

        self::assertTrue($this->connection->beginTransaction());
        $used = $this->repository->markUsed($invitationId, new DateTimeImmutable('2026-08-02 00:00:00 UTC'));
        self::assertTrue($this->connection->commit());
        self::assertNotNull($used?->usedAt());

        self::assertTrue($this->connection->beginTransaction());
        self::assertNull($this->repository->markUsed($invitationId, new DateTimeImmutable('2026-08-03 00:00:00 UTC')));
        self::assertTrue($this->connection->rollBack());

        $expiredBooking = ReviewFixture::insertBooking($this->connection);
        $expiredId = ReviewFixture::insertInvitation($this->connection, $expiredBooking, [
            'expires_at' => '2026-08-02 00:00:00',
        ]);
        self::assertTrue($this->connection->beginTransaction());
        self::assertNull($this->repository->markUsed($expiredId, new DateTimeImmutable('2026-08-02 00:00:00 UTC')));
        self::assertTrue($this->connection->rollBack());
    }

    public function testLockRequiresTransactionAndLookupFailuresAreSanitized(): void
    {
        $hash = hash('sha256', 'missing');

        try {
            $this->repository->lockByTokenHash($hash);
            self::fail('A review invitation lock outside a transaction was accepted.');
        } catch (DatabaseException $exception) {
            self::assertSame('The review invitation lock failed.', $exception->getMessage());
        }

        $this->connection->exec('DROP TABLE reviews');
        $this->connection->exec('DROP TABLE review_invitations');

        try {
            $this->repository->findById(1);
            self::fail('Expected a sanitized invitation lookup failure.');
        } catch (DatabaseException $exception) {
            self::assertSame('The review invitation lookup failed.', $exception->getMessage());
        }
    }
}
