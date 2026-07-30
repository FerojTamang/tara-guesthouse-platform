<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ActivityException;
use App\Models\Activity;
use App\Models\AuditLogEntry;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\AuditLogWriteRepository;
use App\Requests\ActivityContentRequest;
use App\Services\ActivityTransaction;
use App\Services\AdministratorActivityService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class AdministratorActivityServiceTest extends TestCase
{
    public function testCreateCommitsActivityAndAppendOnlyAuditTogether(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();
        $service = $this->service($repository, $audit, $transaction);

        $created = $service->create(
            $this->request(),
            true,
            false,
            91,
            '127.0.0.1',
            'PHPUnit',
        );

        self::assertSame('jungle-walk', $created->slug());
        self::assertTrue($created->isActive());
        self::assertFalse($created->isPublished());
        self::assertSame(1, $transaction->begins);
        self::assertSame(1, $transaction->commits);
        self::assertSame(0, $transaction->rollbacks);
        self::assertCount(1, $audit->entries);
        self::assertSame('activity_created', $audit->entries[0]->eventType());
        self::assertSame(91, $audit->entries[0]->userId());
        self::assertSame('activity', $audit->entries[0]->entityType());
        self::assertSame($created->id(), $audit->entries[0]->entityId());
        self::assertSame('127.0.0.1', $audit->entries[0]->ipAddress());
        self::assertStringContainsString('"is_active":true', $audit->entries[0]->metadataJson() ?? '');
    }

    public function testUpdateLocksRecordAndPreservesIndependentStates(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $repository->items[7] = $this->activity(active: false, published: true);
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();
        $service = $this->service($repository, $audit, $transaction);

        $updated = $service->update(
            7,
            ActivityContentRequest::fromInput(
                'Updated Walk',
                'updated-walk',
                'Updated activity description.',
                '3000.00',
                20,
            ),
            91,
            null,
            null,
        );

        self::assertSame([7], $repository->lockedIdentifiers);
        self::assertSame('updated-walk', $updated->slug());
        self::assertFalse($updated->isActive());
        self::assertTrue($updated->isPublished());
        self::assertSame('activity_updated', $audit->entries[0]->eventType());
        self::assertStringContainsString('"previous_slug":"jungle-walk"', $audit->entries[0]->metadataJson() ?? '');
    }

    public function testActiveAndPublicationChangesRemainIndependent(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $repository->items[7] = $this->activity(active: true, published: false);
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();
        $service = $this->service($repository, $audit, $transaction);

        $inactive = $service->setActive(7, false, 91, null, null);
        self::assertFalse($inactive->isActive());
        self::assertFalse($inactive->isPublished());
        self::assertSame(1, $repository->setActiveCalls);
        self::assertSame(0, $repository->setPublishedCalls);

        $published = $service->setPublished(7, true, 91, null, null);
        self::assertFalse($published->isActive());
        self::assertTrue($published->isPublished());
        self::assertSame(1, $repository->setActiveCalls);
        self::assertSame(1, $repository->setPublishedCalls);
        self::assertSame(
            ['activity_active_changed', 'activity_publication_changed'],
            array_map(
                static fn (AuditLogEntry $entry): string => $entry->eventType(),
                $audit->entries,
            ),
        );
    }

    public function testRepeatedStateRequestIsIdempotentAndDoesNotInventAuditEvidence(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $repository->items[7] = $this->activity(active: true, published: false);
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();

        $result = $this->service($repository, $audit, $transaction)
            ->setActive(7, true, 91, null, null);

        self::assertTrue($result->isActive());
        self::assertSame(0, $repository->setActiveCalls);
        self::assertSame([], $audit->entries);
        self::assertSame(1, $transaction->commits);
    }

    public function testRemovalDeactivatesAndUnpublishesWithoutDeleting(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $repository->items[7] = $this->activity(active: true, published: true);
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();

        $removed = $this->service($repository, $audit, $transaction)
            ->remove(7, 91, null, null);

        self::assertFalse($removed->isActive());
        self::assertFalse($removed->isPublished());
        self::assertArrayHasKey(7, $repository->items);
        self::assertSame(1, $repository->removeCalls);
        self::assertSame('activity_removed', $audit->entries[0]->eventType());
    }

    public function testDuplicateSlugConflictRollsBackWithoutAudit(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $repository->writeFailure = ActivityException::duplicateSlug();
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();

        try {
            $this->service($repository, $audit, $transaction)->create(
                $this->request(),
                true,
                true,
                91,
                null,
                null,
            );
            self::fail('Expected a duplicate slug conflict.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame('An activity with this slug already exists.', $exception->getMessage());
        }

        self::assertSame(0, $transaction->commits);
        self::assertSame(1, $transaction->rollbacks);
        self::assertSame([], $audit->entries);
    }

    public function testAuditFailureRollsBackTheActivityMutation(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $repository->items[7] = $this->activity();
        $audit = new AdministratorActivityAuditFake();
        $audit->failure = new RuntimeException('sensitive audit failure');
        $transaction = new AdministratorActivityTransactionFake();

        try {
            $this->service($repository, $audit, $transaction)->update(
                7,
                $this->request(),
                91,
                null,
                null,
            );
            self::fail('Expected a safe activity failure.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive audit failure', $exception->getMessage());
        }

        self::assertSame(0, $transaction->commits);
        self::assertSame(1, $transaction->rollbacks);
    }

    public function testMissingTargetRollsBackAsNotFound(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();

        try {
            $this->service($repository, $audit, $transaction)->remove(
                999,
                91,
                null,
                null,
            );
            self::fail('Expected a missing activity failure.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isNotFound());
        }

        self::assertSame(1, $transaction->rollbacks);
        self::assertSame([], $audit->entries);
    }

    public function testExistingTransactionFailsClosedBeforeRepositoryAccess(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $audit = new AdministratorActivityAuditFake();
        $transaction = new AdministratorActivityTransactionFake();
        $transaction->active = true;

        $this->expectException(ActivityException::class);
        $this->expectExceptionMessage('Activity catalogue operation failed.');

        $this->service($repository, $audit, $transaction)->create(
            $this->request(),
            true,
            true,
            91,
            null,
            null,
        );
    }

    public function testUnexpectedReadFailureIsSanitized(): void
    {
        $repository = new AdministratorActivityRepositoryFake();
        $repository->readFailure = new RuntimeException('sensitive SQL');

        try {
            $this->service(
                $repository,
                new AdministratorActivityAuditFake(),
                new AdministratorActivityTransactionFake(),
            )->listAll();
            self::fail('Expected a safe activity read failure.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('Activity catalogue operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive SQL', $exception->getMessage());
        }
    }

    private function service(
        AdministratorActivityRepositoryFake $repository,
        AdministratorActivityAuditFake $audit,
        AdministratorActivityTransactionFake $transaction,
    ): AdministratorActivityService {
        return new AdministratorActivityService(
            $repository,
            $audit,
            $transaction,
            static fn (): DateTimeImmutable => new DateTimeImmutable('2026-07-30 08:30:00 UTC'),
        );
    }

    private function request(): ActivityContentRequest
    {
        return ActivityContentRequest::fromInput(
            'Jungle Walk',
            'jungle-walk',
            'Explore the surrounding forest.',
            '2500.00',
            10,
        );
    }

    private function activity(
        int $id = 7,
        bool $active = true,
        bool $published = true,
    ): Activity {
        return new Activity(
            $id,
            'Jungle Walk',
            'jungle-walk',
            'Explore the surrounding forest.',
            '2500.00',
            $active,
            $published,
            10,
            new DateTimeImmutable('2026-07-30 07:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 07:00:00 UTC'),
        );
    }
}

final class AdministratorActivityRepositoryFake implements ActivityCatalogRepository
{
    /** @var array<int, Activity> */
    public array $items = [];

    /** @var list<int> */
    public array $lockedIdentifiers = [];

    public int $setActiveCalls = 0;
    public int $setPublishedCalls = 0;
    public int $removeCalls = 0;
    public ?Throwable $readFailure = null;
    public ?Throwable $writeFailure = null;

    public function findById(int $id): ?Activity
    {
        if ($this->readFailure !== null) {
            throw $this->readFailure;
        }

        return $this->items[$id] ?? null;
    }

    public function lockById(int $id): ?Activity
    {
        $this->lockedIdentifiers[] = $id;

        return $this->items[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Activity
    {
        foreach ($this->items as $item) {
            if ($item->slug() === $slug) {
                return $item;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        if ($this->readFailure !== null) {
            throw $this->readFailure;
        }

        return array_values($this->items);
    }

    public function findAllPublic(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (Activity $activity): bool => $activity->isPubliclyVisible(),
        ));
    }

    public function create(
        string $title,
        string $slug,
        string $description,
        string $price,
        bool $active,
        bool $published,
        int $sortOrder,
    ): Activity {
        $this->throwWriteFailure();
        $id = $this->items === [] ? 1 : max(array_keys($this->items)) + 1;
        $activity = $this->newActivity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $active,
            $published,
            $sortOrder,
        );
        $this->items[$id] = $activity;

        return $activity;
    }

    public function update(
        int $id,
        string $title,
        string $slug,
        string $description,
        string $price,
        int $sortOrder,
    ): ?Activity {
        $this->throwWriteFailure();
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $current->isActive(),
            $current->isPublished(),
            $sortOrder,
        );
    }

    public function setActive(int $id, bool $active): ?Activity
    {
        $this->setActiveCalls++;
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $current->title(),
            $current->slug(),
            $current->description(),
            $current->price(),
            $active,
            $current->isPublished(),
            $current->sortOrder(),
        );
    }

    public function setPublished(int $id, bool $published): ?Activity
    {
        $this->setPublishedCalls++;
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $current->title(),
            $current->slug(),
            $current->description(),
            $current->price(),
            $current->isActive(),
            $published,
            $current->sortOrder(),
        );
    }

    public function deactivateAndUnpublish(int $id): ?Activity
    {
        $this->removeCalls++;
        $current = $this->items[$id] ?? null;

        if (!$current instanceof Activity) {
            return null;
        }

        return $this->items[$id] = $this->newActivity(
            $id,
            $current->title(),
            $current->slug(),
            $current->description(),
            $current->price(),
            false,
            false,
            $current->sortOrder(),
        );
    }

    private function throwWriteFailure(): void
    {
        if ($this->writeFailure !== null) {
            throw $this->writeFailure;
        }
    }

    private function newActivity(
        int $id,
        string $title,
        string $slug,
        string $description,
        string $price,
        bool $active,
        bool $published,
        int $sortOrder,
    ): Activity {
        return new Activity(
            $id,
            $title,
            $slug,
            $description,
            $price,
            $active,
            $published,
            $sortOrder,
            new DateTimeImmutable('2026-07-30 07:00:00 UTC'),
            new DateTimeImmutable('2026-07-30 08:30:00 UTC'),
        );
    }
}

final class AdministratorActivityAuditFake implements AuditLogWriteRepository
{
    /** @var list<AuditLogEntry> */
    public array $entries = [];
    public ?Throwable $failure = null;

    public function append(AuditLogEntry $entry): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->entries[] = $entry;
    }
}

final class AdministratorActivityTransactionFake implements ActivityTransaction
{
    public bool $active = false;
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

    public function begin(): void
    {
        $this->begins++;
        $this->active = true;
    }

    public function commit(): void
    {
        $this->commits++;
        $this->active = false;
    }

    public function rollBack(): void
    {
        $this->rollbacks++;
        $this->active = false;
    }

    public function inProgress(): bool
    {
        return $this->active;
    }
}
