<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\CmsPage;
use App\Models\ContentMedia;
use App\Models\PageContentProfile;
use App\Models\PublishedContentItem;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProfessionalContentModelsTest extends TestCase
{
    public function testContentMediaExposesSafeReusablePublicState(): void
    {
        $time = new DateTimeImmutable('2026-08-04 16:30:00 UTC');
        $media = new ContentMedia(
            7,
            'uploads/content/' . str_repeat('a', 64) . '.webp',
            'garden.webp',
            'image/webp',
            'webp',
            128000,
            1600,
            900,
            str_repeat('b', 64),
            'Garden view',
            'Tara Guest House garden overlooking the hills',
            'The garden in the early morning.',
            3,
            $time,
            $time,
            null,
            2,
        );

        self::assertSame('/uploads/content/' . str_repeat('a', 64) . '.webp', $media->publicUrl());
        self::assertSame(1600, $media->width());
        self::assertSame(2, $media->usageCount());
        self::assertTrue($media->isInUse());
        self::assertFalse($media->isDeleted());
    }

    public function testContentMediaRejectsAnUncontrolledStoragePath(): void
    {
        $time = new DateTimeImmutable('2026-08-04 16:30:00 UTC');
        $this->expectException(InvalidArgumentException::class);

        new ContentMedia(
            7,
            'https://images.example.test/garden.webp',
            'garden.webp',
            'image/webp',
            'webp',
            128000,
            1600,
            900,
            str_repeat('b', 64),
            null,
            'Garden view',
            null,
            3,
            $time,
            $time,
            null,
            0,
        );
    }

    public function testPageContentProfileCarriesProfessionalPublicationMetadata(): void
    {
        $created = new DateTimeImmutable('2026-08-04 16:30:00 UTC');
        $publish = new DateTimeImmutable('2026-08-05 01:00:00 UTC');
        $expires = new DateTimeImmutable('2026-08-10 01:00:00 UTC');
        $profile = new PageContentProfile(
            12,
            'notice',
            'Temporary road-access information.',
            'important',
            $publish,
            $expires,
            true,
            true,
            4,
            'Road access notice',
            'Please review the temporary route before arriving.',
            5,
            false,
            3,
            $created,
            $created,
        );

        self::assertSame('notice', $profile->contentType());
        self::assertSame('important', $profile->noticeStyle());
        self::assertTrue($profile->isFeatured());
        self::assertTrue($profile->isPinned());
        self::assertSame($publish, $profile->publishAt());
        self::assertSame($expires, $profile->expiresAt());
    }

    public function testPublishedContentItemRequiresMatchingPublishedPageAndProfile(): void
    {
        $time = new DateTimeImmutable('2026-08-04 16:30:00 UTC');
        $page = new CmsPage(
            12,
            'Garden update',
            'garden-update',
            'Garden update body.',
            null,
            null,
            CmsPage::PUBLISHED,
            $time,
            3,
            3,
            $time,
            $time,
        );
        $profile = new PageContentProfile(
            12,
            'news',
            'A fresh garden update.',
            null,
            $time,
            null,
            true,
            false,
            null,
            null,
            null,
            null,
            false,
            3,
            $time,
            $time,
        );
        $item = new PublishedContentItem($page, $profile, null);

        self::assertSame($page, $item->page());
        self::assertSame($profile, $item->profile());
        self::assertNull($item->featuredMedia());
    }

    public function testNonNoticeProfileRejectsNoticePresentationStyle(): void
    {
        $time = new DateTimeImmutable('2026-08-04 16:30:00 UTC');
        $this->expectException(InvalidArgumentException::class);

        new PageContentProfile(
            12,
            'news',
            null,
            'warning',
            null,
            null,
            false,
            false,
            null,
            null,
            null,
            null,
            false,
            3,
            $time,
            $time,
        );
    }
}
