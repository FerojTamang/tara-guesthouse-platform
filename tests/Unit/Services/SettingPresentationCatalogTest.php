<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SettingPresentationCatalog;
use PHPUnit\Framework\TestCase;

final class SettingPresentationCatalogTest extends TestCase
{
    public function testItGroupsKnownSettingsWithFriendlyProfessionalMetadata(): void
    {
        $groups = (new SettingPresentationCatalog())->grouped([
            self::row('social.instagram', 'url', true),
            self::row('contact.email', 'email', true),
            self::row('notification.contact', 'email', false),
            self::row('site.name', 'string', true),
        ]);

        self::assertSame(
            ['General', 'Contact information', 'Social media', 'Internal notifications'],
            array_column($groups, 'title'),
        );
        self::assertSame('Guest house name', $groups[0]['fields'][0]['label']);
        self::assertTrue($groups[0]['fields'][0]['required']);
        self::assertSame('Public email', $groups[1]['fields'][0]['label']);
        self::assertSame('email', $groups[1]['fields'][0]['input_type']);
        self::assertFalse($groups[2]['fields'][0]['required']);
        self::assertSame('Contact-message recipient', $groups[3]['fields'][0]['label']);
        self::assertFalse($groups[3]['fields'][0]['public']);
    }

    public function testLogoSettingsAreManagedByTheDedicatedBrandingControl(): void
    {
        $groups = (new SettingPresentationCatalog())->grouped([
            self::row('site.logo', 'relative_path', true),
            self::row('site.logo.enabled', 'boolean', true),
            self::row('site.name', 'string', true),
        ]);

        self::assertCount(1, $groups);
        self::assertSame('General', $groups[0]['title']);
        self::assertSame(['site.name'], array_column($groups[0]['fields'], 'key'));
    }

    public function testUnknownApprovedSettingsRemainAvailableInAdvancedGroup(): void
    {
        $groups = (new SettingPresentationCatalog())->grouped([
            self::row('homepage.rooms.limit', 'integer', false),
        ]);

        self::assertCount(1, $groups);
        self::assertSame('Advanced', $groups[0]['title']);
        self::assertSame('Homepage Rooms Limit', $groups[0]['fields'][0]['label']);
        self::assertSame('number', $groups[0]['fields'][0]['input_type']);
        self::assertTrue($groups[0]['fields'][0]['required']);
    }

    /** @return array<string, mixed> */
    private static function row(string $key, string $valueType, bool $public): array
    {
        return [
            'key' => $key,
            'valueType' => $valueType,
            'public' => $public,
            'maximumLength' => 254,
            'value' => '',
            'configured' => false,
            'updatedAt' => null,
        ];
    }
}
