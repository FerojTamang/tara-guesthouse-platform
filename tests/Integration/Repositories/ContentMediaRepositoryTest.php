<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Core\MigrationRunner;
use App\Repositories\PdoContentMediaRepository;
use App\Repositories\PdoPageContentProfileRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CmsFixture;
use Tests\Support\DatabaseTestCase;

#[Group('database')]
final class ContentMediaRepositoryTest extends DatabaseTestCase
{
    private PdoContentMediaRepository $media;
    private PdoPageContentProfileRepository $profiles;
    private int $administratorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new MigrationRunner(
            $this->connection,
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
        ))->migrate();
        $this->administratorId = CmsFixture::insertAdministrator($this->connection, 'content-media-repo');
        $this->media = new PdoContentMediaRepository($this->connection);
        $this->profiles = new PdoPageContentProfileRepository($this->connection);
    }

    public function testMediaMetadataAndPageUsageArePersistedWithoutPhysicalPaths(): void
    {
        $media = $this->media->create(
            'uploads/content/' . str_repeat('a', 64) . '.webp',
            'garden.webp',
            'image/webp',
            'webp',
            154000,
            1600,
            900,
            str_repeat('b', 64),
            'Garden view',
            'Garden terrace overlooking the hills',
            null,
            $this->administratorId,
        );
        self::assertSame(0, $media->usageCount());
        self::assertSame('/uploads/content/' . str_repeat('a', 64) . '.webp', $media->publicUrl());

        $pageId = CmsFixture::insertPage($this->connection, $this->administratorId);
        $profile = $this->profiles->upsertCore(
            $pageId,
            'news',
            'A short update from Tara Guest House.',
            $this->administratorId,
        );
        self::assertSame('news', $profile->contentType());
        self::assertSame('A short update from Tara Guest House.', $profile->summary());

        $profile = $this->profiles->updatePublishing(
            $pageId,
            null,
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            null,
            true,
            true,
            $media->id(),
            'Garden update',
            'A social preview for the garden update.',
            $media->id(),
            true,
            $this->administratorId,
        );
        self::assertTrue($profile->isFeatured());
        self::assertTrue($profile->isPinned());
        self::assertSame($media->id(), $profile->featuredMediaId());
        self::assertSame($media->id(), $profile->socialMediaId());
        self::assertTrue($profile->seoNoindex());

        $this->profiles->replaceBlockMedia($pageId, [[
            'media_id' => $media->id(),
            'block_key' => 'block-1',
            'sort_order' => 0,
        ]]);

        $used = $this->media->findActiveById($media->id());
        self::assertNotNull($used);
        self::assertSame(3, $used->usageCount());
        self::assertTrue($used->isInUse());

        $updated = $this->media->updateMetadata(
            $media->id(),
            'Morning garden',
            'Garden terrace in the morning',
            'A quiet morning at Tara Guest House.',
        );
        self::assertNotNull($updated);
        self::assertSame('Morning garden', $updated->title());
        self::assertSame('A quiet morning at Tara Guest House.', $updated->caption());

        $this->profiles->replaceBlockMedia($pageId, []);
        $this->profiles->updatePublishing(
            $pageId,
            null,
            new DateTimeImmutable('2026-08-10 00:00:00 UTC'),
            null,
            false,
            false,
            null,
            null,
            null,
            null,
            false,
            $this->administratorId,
        );
        self::assertTrue($this->media->softDelete($media->id()));
        self::assertNull($this->media->findActiveById($media->id()));
        self::assertSame(0, (int) $this->connection->query('SELECT COUNT(*) FROM page_block_media')->fetchColumn());
    }


    public function testPageProfilesCanBeLoadedInOneKeyedBatch(): void
    {
        $firstPageId = CmsFixture::insertPage($this->connection, $this->administratorId, [
            'title' => 'First bulk page',
            'slug' => 'first-bulk-page',
        ]);
        $secondPageId = CmsFixture::insertPage($this->connection, $this->administratorId, [
            'title' => 'Second bulk page',
            'slug' => 'second-bulk-page',
        ]);
        $this->profiles->upsertCore($firstPageId, 'news', 'First summary.', $this->administratorId);
        $this->profiles->upsertCore($secondPageId, 'notice', 'Second summary.', $this->administratorId);

        $profiles = $this->profiles->findByPageIds([
            $secondPageId,
            $firstPageId,
            $secondPageId,
            0,
        ]);

        self::assertSame([$firstPageId, $secondPageId], array_keys($profiles));
        self::assertSame('news', $profiles[$firstPageId]->contentType());
        self::assertSame('notice', $profiles[$secondPageId]->contentType());
        self::assertSame([], $this->profiles->findByPageIds([0, -1]));
    }

    public function testActiveChecksumIsUniqueButCanBeReusedAfterSoftDeletion(): void
    {
        $checksum = str_repeat('d', 64);
        $first = $this->media->create(
            'uploads/content/' . str_repeat('c', 64) . '.jpg',
            'first.jpg',
            'image/jpeg',
            'jpg',
            1000,
            800,
            600,
            $checksum,
            null,
            'First reusable image',
            null,
            $this->administratorId,
        );

        try {
            $this->media->create(
                'uploads/content/' . str_repeat('e', 64) . '.jpg',
                'duplicate.jpg',
                'image/jpeg',
                'jpg',
                1000,
                800,
                600,
                $checksum,
                null,
                'Duplicate reusable image',
                null,
                $this->administratorId,
            );
            self::fail('The database unexpectedly accepted a duplicate active checksum.');
        } catch (\App\Exceptions\ContentMediaException $exception) {
            self::assertTrue($exception->isConflict());
        }

        self::assertTrue($this->media->softDelete($first->id()));
        $replacement = $this->media->create(
            'uploads/content/' . str_repeat('f', 64) . '.jpg',
            'replacement.jpg',
            'image/jpeg',
            'jpg',
            1000,
            800,
            600,
            $checksum,
            null,
            'Replacement reusable image',
            null,
            $this->administratorId,
        );
        self::assertGreaterThan($first->id(), $replacement->id());
    }

    public function testInvalidIdentifiersAndChecksumsReturnNoMedia(): void
    {
        self::assertNull($this->media->findActiveById(0));
        self::assertNull($this->media->findActiveByChecksum('../invalid'));
        self::assertFalse($this->media->softDelete(0));
    }
}
