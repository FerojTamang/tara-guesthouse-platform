<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\CmsException;
use App\Models\Activity;
use App\Models\CmsPage;
use App\Models\GalleryItem;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use App\Presentation\RoomImageUrl;
use App\Repositories\ActivityCatalogRepository;
use App\Repositories\GalleryCatalogRepository;
use App\Repositories\PageRepository;
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
