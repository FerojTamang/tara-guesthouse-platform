<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\Activity;
use App\Models\CmsPage;
use App\Models\ContentMedia;
use App\Models\PageContentProfile;
use App\Models\PublishedContentItem;
use App\Models\GalleryItem;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Presentation\RoomImageUrl;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\ContentMediaRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Repositories\PageContentProfileRepository;
use App\Repositories\PageRepository;
use App\Repositories\PublicContentCatalogRepository;
use App\Repositories\RoomCatalogRepository;
use App\Repositories\RoomImageCatalogRepository;
use App\Repositories\SettingRepository;
use App\Services\PublicActivityService;
use App\Services\PublicCmsService;
use App\Services\PublicGalleryService;
use App\Services\PublicRoomCatalogService;
use App\Services\SettingDefinitionRegistry;
use App\Storage\GalleryImageReader;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicCmsServiceTest extends TestCase
{
    public function testPublishedPageProjectionUsesMetadataAndOnlyPublishedNavigation(): void
    {
        $page = self::page(5, 'about', CmsPage::PUBLISHED, '<script>About</script>');
        $policy = self::page(6, 'policy', CmsPage::PUBLISHED, 'Policy body.');
        $reserved = self::page(7, 'rooms', CmsPage::PUBLISHED, 'Reserved.');
        $draft = self::page(8, 'draft-page', CmsPage::DRAFT, 'Draft.');
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findPublishedBySlug')->willReturn($page);
        $pages->method('findAllPublished')->willReturn([$page, $policy, $reserved, $draft]);
        $service = $this->service($pages);

        $result = $service->page('about');

        self::assertSame(5, $result['page_id']);
        self::assertSame('<script>About</script>', $result['content']);
        self::assertSame('https://example.test/about', $result['canonical_url']);
        $layout = $service->layoutContext();
        self::assertSame([
            ['title' => 'About', 'slug' => 'about'],
            ['title' => 'Policy', 'slug' => 'policy'],
        ], $layout['navigation_pages']);
        self::assertSame([], $layout['footer_settings']);
    }


    public function testNavigationPagesLoadPublishingProfilesInOneBatch(): void
    {
        $about = self::page(5, 'about', CmsPage::PUBLISHED, 'About.');
        $news = self::page(6, 'garden-update', CmsPage::PUBLISHED, 'News.');
        $notice = self::page(7, 'arrival-notice', CmsPage::PUBLISHED, 'Notice.');
        $reserved = self::page(8, 'rooms', CmsPage::PUBLISHED, 'Reserved.');
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findAllPublished')->willReturn([$about, $news, $notice, $reserved]);
        $profiles = $this->createMock(PageContentProfileRepository::class);
        $profiles->expects(self::once())
            ->method('findByPageIds')
            ->with([5, 6, 7])
            ->willReturn([
                5 => self::profile(5, 'page', 'About summary.'),
                6 => self::profile(6, 'news', 'News summary.'),
                7 => self::profile(7, 'notice', 'Notice summary.'),
            ]);
        $profiles->expects(self::never())->method('findByPageId');

        $layout = $this->service(pages: $pages, profiles: $profiles)->layoutContext();

        self::assertSame([
            ['title' => 'About', 'slug' => 'about'],
        ], $layout['navigation_pages']);
    }

    public function testPublishingProfileProjectsNoticeMediaSocialAndNoindexMetadata(): void
    {
        $page = self::page(5, 'guest-update', CmsPage::PUBLISHED, 'Notice body.');
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findPublishedBySlug')->willReturn($page);

        $profiles = $this->createMock(PageContentProfileRepository::class);
        $profiles->expects(self::once())
            ->method('findByPageId')
            ->with(5)
            ->willReturn(new PageContentProfile(
            5,
            'notice',
            'Important information for arriving guests.',
            'important',
            new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
            new DateTimeImmutable('2026-08-10 04:00:00 UTC'),
            true,
            true,
            9,
            'Arrival update',
            'Please review this information before arrival.',
            null,
            true,
            1,
            new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
            new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
        ));

        $media = $this->createMock(ContentMediaRepository::class);
        $media->expects(self::once())
            ->method('findActiveById')
            ->with(9)
            ->willReturn(self::media(9));

        $result = $this->service(
            pages: $pages,
            profiles: $profiles,
            contentMedia: $media,
            clock: static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-05 00:00:00 UTC'),
        )->page('guest-update');

        self::assertSame('notice', $result['content_type']);
        self::assertSame('important', $result['notice_style']);
        self::assertSame('Important information for arriving guests.', $result['summary']);
        self::assertSame('/uploads/content/' . str_repeat('a', 64) . '.webp', $result['featured_image']['url']);
        self::assertSame('Garden view', $result['featured_image']['alt_text']);
        self::assertSame('Arrival update', $result['social_title']);
        self::assertSame('https://example.test/uploads/content/' . str_repeat('a', 64) . '.webp', $result['social_image']);
        self::assertTrue($result['seo_noindex']);
        self::assertSame('https://example.test/notices/guest-update', $result['canonical_url']);
    }

    public function testPublicCollectionsOrderPinnedAndImportantContentAndFeedHomepage(): void
    {
        $newsRegular = self::publishedItem(11, 'regular-update', 'news', 'Regular update.');
        $newsPinned = self::publishedItem(12, 'pinned-update', 'news', 'Pinned update.', pinned: true);
        $noticeInfo = self::publishedItem(21, 'information-notice', 'notice', 'Information notice.', 'information');
        $noticeImportant = self::publishedItem(22, 'important-notice', 'notice', 'Important notice.', 'important');
        $catalog = $this->createStub(PublicContentCatalogRepository::class);
        $catalog->method('findAllPublishedByType')->willReturnCallback(
            static fn (string $type): array => $type === 'news'
                ? [$newsRegular, $newsPinned]
                : [$noticeInfo, $noticeImportant],
        );

        $service = $this->service(
            publicContent: $catalog,
            clock: static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-05 00:00:00 UTC'),
        );
        $news = $service->newsIndex();
        $notices = $service->noticesIndex();
        $home = $service->homepage();

        self::assertSame(['pinned-update', 'regular-update'], array_column($news['items'], 'slug'));
        self::assertSame('/news/pinned-update', $news['items'][0]['href']);
        self::assertSame(['important-notice', 'information-notice'], array_column($notices['items'], 'slug'));
        self::assertSame('/notices/important-notice', $notices['items'][0]['href']);
        self::assertSame(['pinned-update', 'regular-update'], array_column($home['latest_news'], 'slug'));
        self::assertSame('important-notice', $home['active_notice']['slug']);
        self::assertSame('https://example.test/news', $news['canonical_url']);
        self::assertSame('https://example.test/notices', $notices['canonical_url']);
    }

    public function testStructuredContentWithoutSummaryNeverLeaksStoredJsonIntoPublicCards(): void
    {
        $content = json_encode([
            'format' => 'tara-blocks-v1',
            'version' => 1,
            'type' => 'news',
            'summary' => '',
            'blocks' => [[
                'type' => 'image',
                'media_id' => 9,
                'url' => '/uploads/content/' . str_repeat('a', 64) . '.webp',
                'width' => 1600,
                'height' => 900,
                'alt' => 'Garden view',
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $item = new PublishedContentItem(
            self::page(13, 'image-update', CmsPage::PUBLISHED, $content),
            self::profile(13, 'news', null),
            null,
        );
        $catalog = $this->createStub(PublicContentCatalogRepository::class);
        $catalog->method('findAllPublishedByType')->willReturn([$item]);

        $news = $this->service(
            publicContent: $catalog,
            clock: static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-05 00:00:00 UTC'),
        )->newsIndex();

        self::assertSame('Read the full update.', $news['items'][0]['summary']);
        self::assertStringNotContainsString('tara-blocks-v1', $news['items'][0]['summary']);
    }

    public function testTypedPublicRoutesRejectContentFromTheWrongCollection(): void
    {
        $page = self::page(5, 'arrival-notice', CmsPage::PUBLISHED, 'Notice body.');
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findPublishedBySlug')->willReturn($page);
        $profiles = $this->createStub(PageContentProfileRepository::class);
        $profiles->method('findByPageId')->willReturn(self::profile(5, 'notice', 'Arrival details.', 'important'));
        $service = $this->service(pages: $pages, profiles: $profiles);

        self::assertSame('notice', $service->notice('arrival-notice')['content_type']);

        try {
            $service->newsArticle('arrival-notice');
            self::fail('A notice must not be available under the news route.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testExpiredPublishingProfileReturnsNotFound(): void
    {
        $page = self::page(5, 'expired-notice', CmsPage::PUBLISHED, 'Expired.');
        $pages = $this->createStub(PageRepository::class);
        $pages->method('findPublishedBySlug')->willReturn($page);
        $profiles = $this->createStub(PageContentProfileRepository::class);
        $profiles->method('findByPageId')->willReturn(new PageContentProfile(
            5,
            'notice',
            null,
            'information',
            new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
            new DateTimeImmutable('2026-08-04 04:00:00 UTC'),
            false,
            false,
            null,
            null,
            null,
            null,
            false,
            1,
            new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
            new DateTimeImmutable('2026-07-31 04:00:00 UTC'),
        ));

        try {
            $this->service(
                pages: $pages,
                profiles: $profiles,
                clock: static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-05 00:00:00 UTC'),
            )->page('expired-notice');
            self::fail('Expired content must not remain public.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testUnpublishedMalformedAndMissingPagesReturnNotFound(): void
    {
        foreach (['', 'Bad Slug', '../about', 'rooms', str_repeat('a', 201)] as $slug) {
            try {
                $this->service()->page($slug);
                self::fail('Expected a safe page not-found failure.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isNotFound(), $slug);
            }
        }

        $pages = $this->createStub(PageRepository::class);
        $pages->method('findPublishedBySlug')->willReturn(null);

        try {
            $this->service($pages)->page('unknown');
            self::fail('Expected a missing page failure.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isNotFound());
        }
    }

    public function testCanonicalBaseUrlRejectsCredentialsQueriesAndFragments(): void
    {
        foreach ([
            'https://user@example.test',
            'https://example.test?preview=1',
            'https://example.test#preview',
        ] as $applicationUrl) {
            try {
                $this->service(applicationUrl: $applicationUrl);
                self::fail('Expected a safe public CMS configuration failure.');
            } catch (CmsException $exception) {
                self::assertTrue($exception->isSystemFailure(), $applicationUrl);
            }
        }
    }

    public function testPublicSettingsAreAllowlistedTypedAndFooterDataIsSemanticOnly(): void
    {
        $settings = $this->createStub(SettingRepository::class);
        $settings->method('findAllPublic')->willReturn([
            self::setting(1, 'contact.email', 'hello@example.test', 'email'),
            self::setting(2, 'homepage.enabled', 'true', 'boolean'),
            self::setting(3, 'homepage.limit', '3', 'integer'),
            self::setting(4, 'footer.links', '["/rooms"]', 'json'),
        ]);
        $registry = new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
            new SettingDefinition('homepage.enabled', 'boolean', true, 5),
            new SettingDefinition('homepage.limit', 'integer', true, 3),
            new SettingDefinition('footer.links', 'json', true, 100),
        ]);
        $service = $this->service(settings: $settings, registry: $registry);

        self::assertSame([
            ['setting_key' => 'contact.email', 'value' => 'hello@example.test', 'value_type' => 'email'],
            ['setting_key' => 'homepage.enabled', 'value' => true, 'value_type' => 'boolean'],
            ['setting_key' => 'homepage.limit', 'value' => 3, 'value_type' => 'integer'],
            ['setting_key' => 'footer.links', 'value' => ['/rooms'], 'value_type' => 'json'],
        ], $service->publicSettings());

        $home = $service->homepage();
        self::assertSame([[
            'setting_key' => 'contact.email',
            'value' => 'hello@example.test',
            'value_type' => 'email',
            'label' => 'Contact Email',
        ]], $home['footer_settings']);
        self::assertSame([], $home['reviews']);
    }

    public function testMissingDefinitionsExposeNoStoredSettingsAndDoNotReadTheRepository(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $settings->expects(self::never())->method('findAllPublic');
        $service = $this->service(
            settings: $settings,
            definitions: static fn (): never => throw CmsException::failure(),
        );

        self::assertSame([], $service->publicSettings());
    }

    public function testHomepageUsesEligibleModuleProjectionsInRepositoryOrder(): void
    {
        $activities = $this->createMock(ActivityCatalogRepository::class);
        $activities->method('findHomepageActivities')->with(4)->willReturn([
            self::activity(2, 'Village Walk'),
            self::activity(4, 'Sunrise Hike'),
        ]);
        $gallery = $this->createMock(GalleryCatalogRepository::class);
        $gallery->method('findHomepageGalleryItems')->with(6)->willReturn([
            self::gallery(9, 'Courtyard'),
        ]);
        $service = $this->service(activities: $activities, gallery: $gallery);

        $home = $service->homepage();

        self::assertSame(['Village Walk', 'Sunrise Hike'], array_column($home['activities'], 'title'));
        self::assertSame(['Courtyard'], array_column($home['gallery'], 'title'));
        self::assertSame('https://example.test/', $home['canonical_url']);
        self::assertSame('Tara Guest House', $home['site_name']);
    }

    public function testHomepageUsesTheApprovedReviewProjectionWithoutChangingOtherModules(): void
    {
        $reviews = [[
            'guest_name' => 'Verified Stay Guest',
            'rating' => 5,
            'title' => 'A warm stay',
            'comment' => 'The room was clean and the team was welcoming.',
            'created_at' => '2026-08-01T00:30:00+00:00',
        ]];
        $service = $this->service(reviews: static fn (): array => $reviews);

        $home = $service->homepage();

        self::assertSame($reviews, $home['reviews']);
        self::assertSame([], $home['rooms']);
        self::assertSame([], $home['activities']);
        self::assertSame([], $home['gallery']);
    }

    public function testStoredSettingContractFailureIsSanitized(): void
    {
        $settings = $this->createStub(SettingRepository::class);
        $settings->method('findAllPublic')->willReturn([
            self::setting(1, 'contact.email', 'hello@example.test', 'string'),
        ]);
        $registry = new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
        ]);

        try {
            $this->service(settings: $settings, registry: $registry)->publicSettings();
            self::fail('Expected a safe stored-setting failure.');
        } catch (CmsException $exception) {
            self::assertTrue($exception->isSystemFailure());
            self::assertSame('CMS operation failed.', $exception->getMessage());
            self::assertStringNotContainsString('contact.email', $exception->getMessage());
        }
    }

    private function service(
        ?PageRepository $pages = null,
        ?SettingRepository $settings = null,
        ?SettingDefinitionRegistry $registry = null,
        ?callable $definitions = null,
        ?ActivityCatalogRepository $activities = null,
        ?GalleryCatalogRepository $gallery = null,
        ?string $applicationUrl = 'https://example.test',
        ?callable $reviews = null,
        ?PageContentProfileRepository $profiles = null,
        ?ContentMediaRepository $contentMedia = null,
        ?callable $clock = null,
        ?PublicContentCatalogRepository $publicContent = null,
    ): PublicCmsService {
        $pages ??= $this->createStub(PageRepository::class);
        $settings ??= $this->createStub(SettingRepository::class);
        $registry ??= new SettingDefinitionRegistry([
            new SettingDefinition('contact.email', 'email', true, 254),
        ]);
        $definitions ??= static fn (): SettingDefinitionRegistry => $registry;
        $roomRepository = $this->createStub(RoomCatalogRepository::class);
        $roomRepository->method('findAllPublic')->willReturn([]);
        $roomImages = $this->createStub(RoomImageCatalogRepository::class);
        $activityRepository = $activities ?? $this->createStub(ActivityCatalogRepository::class);
        $galleryRepository = $gallery ?? $this->createStub(GalleryCatalogRepository::class);

        return new PublicCmsService(
            $pages,
            $settings,
            $definitions,
            new PublicRoomCatalogService($roomRepository, $roomImages, new RoomImageUrl()),
            new PublicActivityService($activityRepository),
            new PublicGalleryService(
                $galleryRepository,
                $this->createStub(GalleryImageReader::class),
            ),
            'Tara Guest House',
            $applicationUrl,
            $reviews,
            $profiles,
            $contentMedia,
            $clock,
            $publicContent,
        );
    }

    private static function page(int $id, string $slug, string $status, string $content): CmsPage
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');

        return new CmsPage(
            $id,
            ucwords(str_replace('-', ' ', $slug)),
            $slug,
            $content,
            'SEO ' . $slug,
            'Description ' . $slug,
            $status,
            $status === CmsPage::PUBLISHED ? $timestamp : null,
            1,
            1,
            $timestamp,
            $timestamp,
        );
    }


    private static function publishedItem(
        int $id,
        string $slug,
        string $contentType,
        string $summary,
        ?string $noticeStyle = null,
        bool $featured = false,
        bool $pinned = false,
    ): PublishedContentItem {
        return new PublishedContentItem(
            self::page($id, $slug, CmsPage::PUBLISHED, $summary),
            self::profile($id, $contentType, $summary, $noticeStyle, $featured, $pinned),
            null,
        );
    }

    private static function profile(
        int $pageId,
        string $contentType,
        ?string $summary,
        ?string $noticeStyle = null,
        bool $featured = false,
        bool $pinned = false,
    ): PageContentProfile {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');

        return new PageContentProfile(
            $pageId,
            $contentType,
            $summary,
            $noticeStyle,
            $timestamp,
            $contentType === 'notice' ? new DateTimeImmutable('2026-08-31 04:00:00 UTC') : null,
            $featured,
            $pinned,
            null,
            null,
            null,
            null,
            false,
            1,
            $timestamp,
            $timestamp,
        );
    }

    private static function media(int $id): ContentMedia
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');

        return new ContentMedia(
            $id,
            'uploads/content/' . str_repeat('a', 64) . '.webp',
            'garden.webp',
            'image/webp',
            'webp',
            145000,
            1600,
            900,
            str_repeat('b', 64),
            'Garden',
            'Garden view',
            null,
            1,
            $timestamp,
            $timestamp,
            null,
            0,
        );
    }

    private static function setting(int $id, string $key, string $value, string $type): SiteSetting
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');

        return new SiteSetting($id, $key, $value, $type, true, 1, $timestamp, $timestamp);
    }

    private static function activity(int $id, string $title): Activity
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');

        return new Activity(
            $id,
            $title,
            strtolower(str_replace(' ', '-', $title)),
            'Description for ' . $title,
            '500.00',
            true,
            true,
            $id,
            $timestamp,
            $timestamp,
        );
    }

    private static function gallery(int $id, string $title): GalleryItem
    {
        $timestamp = new DateTimeImmutable('2026-07-31 04:00:00 UTC');

        return new GalleryItem(
            $id,
            null,
            $title,
            null,
            'gallery/' . str_repeat(dechex($id % 16), 64) . '.png',
            $title . ' image',
            $id,
            true,
            true,
            $timestamp,
            $timestamp,
        );
    }
}
