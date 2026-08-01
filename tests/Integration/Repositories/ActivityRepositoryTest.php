<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Exceptions\ActivityException;
use App\Exceptions\DatabaseException;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\ActivityFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ActivityRepositoryTest extends DatabaseTestCase
{
    private ActivityRepository $repository;

    /** @var array<string, int> */
    private array $activityIds;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3)
                . DIRECTORY_SEPARATOR . 'database'
                . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->repository = new ActivityRepository($this->connection);
        $this->activityIds = [
            'public-late' => ActivityFixture::insert($this->connection, [
                'title' => 'Jungle Walk',
                'slug' => 'jungle-walk',
                'price' => '2500.00',
                'is_active' => 1,
                'is_published' => 1,
                'sort_order' => 20,
                'created_at' => '2026-07-30 07:00:00',
                'updated_at' => '2026-07-30 08:00:00',
            ]),
            'inactive' => ActivityFixture::insert($this->connection, [
                'title' => 'Canoeing',
                'slug' => 'canoeing',
                'description' => 'A calm river excursion.',
                'price' => '1800.00',
                'is_active' => 0,
                'is_published' => 1,
                'sort_order' => 10,
            ]),
            'draft' => ActivityFixture::insert($this->connection, [
                'title' => 'Bird Watching',
                'slug' => 'bird-watching',
                'description' => 'Observe local bird life.',
                'price' => '0.00',
                'is_active' => 1,
                'is_published' => 0,
                'sort_order' => 5,
            ]),
            'public-early' => ActivityFixture::insert($this->connection, [
                'title' => 'Jeep Safari',
                'slug' => 'jeep-safari',
                'description' => 'Explore nearby areas by jeep.',
                'price' => '5500.00',
                'is_active' => 1,
                'is_published' => 1,
                'sort_order' => 10,
            ]),
        ];
    }

    public function testItFindsAndMapsActivitiesByIdentifierAndSlug(): void
    {
        $activity = $this->repository->findById($this->activityIds['public-late']);

        self::assertInstanceOf(Activity::class, $activity);
        self::assertSame('Jungle Walk', $activity->title());
        self::assertSame('jungle-walk', $activity->slug());
        self::assertSame('2500.00', $activity->price());
        self::assertTrue($activity->isPubliclyVisible());
        self::assertSame(20, $activity->sortOrder());
        self::assertEquals(
            new DateTimeImmutable('2026-07-30 07:00:00 UTC'),
            $activity->createdAt(),
        );
        self::assertEquals(
            new DateTimeImmutable('2026-07-30 08:00:00 UTC'),
            $activity->updatedAt(),
        );
        self::assertSame(
            $activity->id(),
            $this->repository->findBySlug('jungle-walk')?->id(),
        );
    }

    public function testItLocksOneActivityInsideACallerOwnedTransaction(): void
    {
        self::assertTrue($this->connection->beginTransaction());

        try {
            $activity = $this->repository->lockById($this->activityIds['public-late']);

            self::assertInstanceOf(Activity::class, $activity);
            self::assertSame('jungle-walk', $activity->slug());
            self::assertNull($this->repository->lockById(999999));
        } finally {
            self::assertTrue($this->connection->rollBack());
        }
    }

    public function testItListsAllAndPublicActivitiesInStableApprovedOrder(): void
    {
        self::assertSame(
            ['Bird Watching', 'Canoeing', 'Jeep Safari', 'Jungle Walk'],
            array_map(
                static fn (Activity $activity): string => $activity->title(),
                $this->repository->findAll(),
            ),
        );
        self::assertSame(
            ['Jeep Safari', 'Jungle Walk'],
            array_map(
                static fn (Activity $activity): string => $activity->title(),
                $this->repository->findAllPublic(),
            ),
        );
    }

    public function testItCreatesAndUpdatesContentWithoutOverwritingVisibilityControls(): void
    {
        $created = $this->repository->create(
            'Elephant Breeding Center',
            'elephant-breeding-center',
            'Learn about elephant conservation.',
            '1200.00',
            false,
            true,
            30,
        );

        self::assertGreaterThan(0, $created->id());
        self::assertFalse($created->isActive());
        self::assertTrue($created->isPublished());

        $updated = $this->repository->update(
            $created->id(),
            'Elephant Conservation Center',
            'elephant-conservation-center',
            'Learn about local elephant conservation.',
            '1500.00',
            25,
        );

        self::assertInstanceOf(Activity::class, $updated);
        self::assertSame('Elephant Conservation Center', $updated->title());
        self::assertSame('elephant-conservation-center', $updated->slug());
        self::assertSame('1500.00', $updated->price());
        self::assertSame(25, $updated->sortOrder());
        self::assertFalse($updated->isActive());
        self::assertTrue($updated->isPublished());
    }

    public function testItChangesActiveAndPublicationControlsIndependentlyThenRemovesSafely(): void
    {
        $id = $this->activityIds['draft'];
        $activated = $this->repository->setActive($id, false);

        self::assertInstanceOf(Activity::class, $activated);
        self::assertFalse($activated->isActive());
        self::assertFalse($activated->isPublished());

        $published = $this->repository->setPublished($id, true);
        self::assertInstanceOf(Activity::class, $published);
        self::assertFalse($published->isActive());
        self::assertTrue($published->isPublished());

        $reactivated = $this->repository->setActive($id, true);
        self::assertInstanceOf(Activity::class, $reactivated);
        self::assertTrue($reactivated->isActive());
        self::assertTrue($reactivated->isPublished());

        $removed = $this->repository->deactivateAndUnpublish($id);
        self::assertInstanceOf(Activity::class, $removed);
        self::assertFalse($removed->isActive());
        self::assertFalse($removed->isPublished());
        self::assertNotNull($this->repository->findById($id));
    }

    public function testInvalidAndMissingIdentifiersReturnNullWithoutMutation(): void
    {
        $before = $this->snapshot();

        self::assertNull($this->repository->findById(0));
        self::assertNull($this->repository->findById(-1));
        self::assertNull($this->repository->lockById(0));
        self::assertNull($this->repository->findById(999999));
        self::assertNull($this->repository->findBySlug(''));
        self::assertNull($this->repository->findBySlug('missing'));
        self::assertNull($this->repository->update(
            0,
            'Missing',
            'missing',
            'Missing activity.',
            '0.00',
            0,
        ));
        self::assertNull($this->repository->setActive(0, false));
        self::assertNull($this->repository->setPublished(0, false));
        self::assertNull($this->repository->deactivateAndUnpublish(0));
        self::assertNull($this->repository->setActive(999999, false));
        self::assertNull($this->repository->setPublished(999999, false));
        self::assertNull($this->repository->deactivateAndUnpublish(999999));

        self::assertSame($before, $this->snapshot());
    }

    public function testItRejectsNonCanonicalPricesBeforeDatabaseCoercion(): void
    {
        $before = $this->snapshot();

        foreach (['1.0', '1.001', '-0.01', 'NPR 1.00'] as $price) {
            try {
                $this->repository->create(
                    'Invalid Price Activity',
                    'invalid-price-' . str_replace(['.', ' '], '-', $price),
                    'Invalid price fixture.',
                    $price,
                    true,
                    true,
                    99,
                );
                self::fail('Expected an exact activity price validation failure.');
            } catch (ActivityException $exception) {
                self::assertTrue($exception->isValidationFailure());
                self::assertSame(
                    'Activity price must be a non-negative NPR amount with exactly two decimal places.',
                    $exception->getMessage(),
                );
            }
        }

        try {
            $this->repository->update(
                $this->activityIds['public-late'],
                'Jungle Walk',
                'jungle-walk',
                'Explore the surrounding forest with a local guide.',
                '1.001',
                20,
            );
            self::fail('Expected an exact activity price validation failure.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isValidationFailure());
        }

        self::assertSame($before, $this->snapshot());
    }

    public function testDuplicateSlugsBecomeSafeConflicts(): void
    {
        try {
            $this->repository->create(
                'Duplicate Jungle Walk',
                'jungle-walk',
                'Duplicate fixture.',
                '100.00',
                true,
                true,
                99,
            );
            self::fail('Expected a duplicate activity slug conflict.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame(
                'An activity with this slug already exists.',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }

        try {
            $this->repository->update(
                $this->activityIds['public-late'],
                'Conflicting Jungle Walk',
                'canoeing',
                'Conflicting fixture.',
                '100.00',
                99,
            );
            self::fail('Expected an updated duplicate activity slug conflict.');
        } catch (ActivityException $exception) {
            self::assertTrue($exception->isConflict());
            self::assertSame(
                'An activity with this slug already exists.',
                $exception->getMessage(),
            );
        }

        self::assertSame(
            'jungle-walk',
            $this->repository->findById($this->activityIds['public-late'])?->slug(),
        );
    }

    public function testDatabaseFailuresExposeOnlyStableMessages(): void
    {
        $this->dropTables(['activities']);

        foreach ([
            fn () => $this->repository->findById(1),
            fn () => $this->repository->findAll(),
            fn () => $this->repository->create(
                'Unavailable Activity',
                'unavailable-activity',
                'Unavailable fixture.',
                '0.00',
                false,
                false,
                0,
            ),
            fn () => $this->repository->setActive(1, false),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected a sanitized activity database failure.');
            } catch (DatabaseException $exception) {
                self::assertStringStartsWith('The activity ', $exception->getMessage());
                self::assertStringNotContainsString('SQLSTATE', $exception->getMessage());
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function snapshot(): array
    {
        $rows = $this->connection->query(
            'SELECT id, title, slug, description, price, is_active, is_published, '
            . 'sort_order, created_at, updated_at FROM activities ORDER BY id',
        )->fetchAll();
        self::assertIsArray($rows);

        return $rows;
    }
}
