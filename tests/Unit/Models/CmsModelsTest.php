<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Exceptions\CmsException;
use App\Models\CmsPage;
use App\Models\SettingDefinition;
use App\Models\SiteSetting;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CmsModelsTest extends TestCase
{
    public function testSiteSettingAndPageExposeTypedImmutableState(): void
    {
        $time = new DateTimeImmutable('2026-07-31 00:00:00', new DateTimeZone('UTC'));
        $setting = new SiteSetting(1, 'contact.email', 'hello@example.test', 'email', true, 9, $time, $time);
        $page = new CmsPage(
            2,
            'About',
            'about',
            'Structured page content.',
            'About Tara',
            null,
            CmsPage::PUBLISHED,
            $time,
            9,
            10,
            $time,
            $time,
        );

        self::assertSame('contact.email', $setting->key());
        self::assertTrue($setting->isPublic());
        self::assertSame(9, $setting->updatedBy());
        self::assertSame('about', $page->slug());
        self::assertTrue($page->isPublished());
        self::assertSame(10, $page->updatedBy());
    }

    public function testPublishedPagesRequirePublicationTimestamp(): void
    {
        $time = new DateTimeImmutable('2026-07-31 00:00:00', new DateTimeZone('UTC'));
        $this->expectException(InvalidArgumentException::class);
        new CmsPage(1, 'About', 'about', 'Body', null, null, CmsPage::PUBLISHED, null, 1, 1, $time, $time);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function settingValues(): iterable
    {
        yield 'string' => ['string', 'Tara Guest House', 'Tara Guest House'];
        yield 'integer' => ['integer', '12', '12'];
        yield 'boolean' => ['boolean', 'true', 'true'];
        yield 'email normalization' => ['email', 'HELLO@example.test', 'hello@example.test'];
        yield 'url' => ['url', 'https://example.test/contact', 'https://example.test/contact'];
        yield 'relative path' => ['relative_path', 'gallery/default.jpg', 'gallery/default.jpg'];
        yield 'json canonicalization' => ['json', '{"enabled":true}', '{"enabled":true}'];
    }

    #[DataProvider('settingValues')]
    public function testSettingDefinitionsNormalizeApprovedTypes(string $type, string $input, string $expected): void
    {
        $definition = new SettingDefinition('example.value', $type, true, 500);
        self::assertSame($expected, $definition->normalize($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidSettingValues(): iterable
    {
        yield 'boolean' => ['boolean', 'yes'];
        yield 'email' => ['email', 'not-an-email'];
        yield 'credential url' => ['url', 'https://user:secret@example.test'];
        yield 'traversal' => ['relative_path', '../secret'];
        yield 'scalar json' => ['json', 'true'];
    }

    #[DataProvider('invalidSettingValues')]
    public function testSettingDefinitionsRejectInvalidTypedValues(string $type, string $value): void
    {
        $definition = new SettingDefinition('example.value', $type, false, 500);
        $this->expectException(CmsException::class);
        $definition->normalize($value);
    }
}
