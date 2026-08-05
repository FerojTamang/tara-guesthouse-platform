<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Provides presentation-only metadata for approved Administrator settings.
 *
 * The environment allowlist remains the authority for keys, types, visibility,
 * and maximum lengths. This catalog only turns approved keys into a polished,
 * understandable grouped form.
 */
final class SettingPresentationCatalog
{
    /** @var array<string, array{title: string, description: string, order: int}> */
    private const GROUPS = [
        'general' => [
            'title' => 'General',
            'description' => 'Core identity and regional display preferences for Tara Guest House.',
            'order' => 10,
        ],
        'contact' => [
            'title' => 'Contact information',
            'description' => 'Public details guests use to reach and locate the guest house.',
            'order' => 20,
        ],
        'social' => [
            'title' => 'Social media',
            'description' => 'Official profiles that may be shown on public pages and in the footer.',
            'order' => 30,
        ],
        'guest' => [
            'title' => 'Guest information',
            'description' => 'Helpful arrival, departure, reception, and emergency information.',
            'order' => 40,
        ],
        'seo' => [
            'title' => 'Website and SEO',
            'description' => 'Default search-preview and footer content used across public pages.',
            'order' => 50,
        ],
        'notifications' => [
            'title' => 'Internal notifications',
            'description' => 'Private operational recipients. These addresses are never displayed publicly.',
            'order' => 60,
        ],
        'advanced' => [
            'title' => 'Advanced',
            'description' => 'Additional approved settings that do not yet have a dedicated presentation group.',
            'order' => 90,
        ],
    ];

    /**
     * @var array<string, array{
     *     group: string,
     *     label: string,
     *     description: string,
     *     placeholder: string,
     *     control: string,
     *     input_type: string,
     *     rows: int,
     *     width: string,
     *     required: bool,
     *     autocomplete: string,
     *     order: int
     * }>
     */
    private const FIELDS = [
        'site.name' => [
            'group' => 'general',
            'label' => 'Guest house name',
            'description' => 'The public business name used in branded content.',
            'placeholder' => 'Tara Guest House',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'half',
            'required' => true,
            'autocomplete' => 'organization',
            'order' => 10,
        ],
        'site.tagline' => [
            'group' => 'general',
            'label' => 'Short tagline',
            'description' => 'A concise brand statement for selected public sections.',
            'placeholder' => 'Warm hospitality in the heart of Sauraha',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 20,
        ],
        'site.description' => [
            'group' => 'general',
            'label' => 'Public description',
            'description' => 'A short reusable introduction to the property.',
            'placeholder' => 'Describe the guest house, location, and hospitality in two or three sentences.',
            'control' => 'textarea',
            'input_type' => 'text',
            'rows' => 4,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 30,
        ],
        'site.timezone' => [
            'group' => 'general',
            'label' => 'Timezone',
            'description' => 'Display reference for operational and guest-facing times.',
            'placeholder' => 'Asia/Kathmandu',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'half',
            'required' => true,
            'autocomplete' => 'off',
            'order' => 40,
        ],
        'site.currency' => [
            'group' => 'general',
            'label' => 'Currency display',
            'description' => 'Three-letter display code. This does not change stored booking calculations.',
            'placeholder' => 'NPR',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'half',
            'required' => true,
            'autocomplete' => 'off',
            'order' => 50,
        ],
        'contact.email' => [
            'group' => 'contact',
            'label' => 'Public email',
            'description' => 'The email address visitors may see and use. This is not the SMTP password.',
            'placeholder' => 'taraguesthouse2026@gmail.com',
            'control' => 'input',
            'input_type' => 'email',
            'rows' => 1,
            'width' => 'half',
            'required' => true,
            'autocomplete' => 'email',
            'order' => 10,
        ],
        'contact.phone' => [
            'group' => 'contact',
            'label' => 'Primary phone',
            'description' => 'Main public telephone number including country code.',
            'placeholder' => '+977 98XXXXXXXX',
            'control' => 'input',
            'input_type' => 'tel',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'tel',
            'order' => 20,
        ],
        'contact.whatsapp' => [
            'group' => 'contact',
            'label' => 'WhatsApp number',
            'description' => 'Guest-facing WhatsApp number including country code.',
            'placeholder' => '+977 98XXXXXXXX',
            'control' => 'input',
            'input_type' => 'tel',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'tel',
            'order' => 30,
        ],
        'contact.address' => [
            'group' => 'contact',
            'label' => 'Address',
            'description' => 'Public location text shown to guests.',
            'placeholder' => 'Sauraha, Chitwan, Nepal',
            'control' => 'textarea',
            'input_type' => 'text',
            'rows' => 3,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'street-address',
            'order' => 40,
        ],
        'contact.maps' => [
            'group' => 'contact',
            'label' => 'Google Maps link',
            'description' => 'A safe public map URL guests can open for directions.',
            'placeholder' => 'https://maps.google.com/...',
            'control' => 'input',
            'input_type' => 'url',
            'rows' => 1,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'url',
            'order' => 50,
        ],
        'social.facebook' => [
            'group' => 'social',
            'label' => 'Facebook URL',
            'description' => 'Official Facebook page.',
            'placeholder' => 'https://www.facebook.com/...',
            'control' => 'input',
            'input_type' => 'url',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'url',
            'order' => 10,
        ],
        'social.instagram' => [
            'group' => 'social',
            'label' => 'Instagram URL',
            'description' => 'Official Instagram profile.',
            'placeholder' => 'https://www.instagram.com/...',
            'control' => 'input',
            'input_type' => 'url',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'url',
            'order' => 20,
        ],
        'social.youtube' => [
            'group' => 'social',
            'label' => 'YouTube URL',
            'description' => 'Official YouTube channel.',
            'placeholder' => 'https://www.youtube.com/@...',
            'control' => 'input',
            'input_type' => 'url',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'url',
            'order' => 30,
        ],
        'social.tiktok' => [
            'group' => 'social',
            'label' => 'TikTok URL',
            'description' => 'Official TikTok profile.',
            'placeholder' => 'https://www.tiktok.com/@...',
            'control' => 'input',
            'input_type' => 'url',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'url',
            'order' => 40,
        ],
        'guest.checkin' => [
            'group' => 'guest',
            'label' => 'Displayed check-in time',
            'description' => 'Guest-facing arrival guidance. This does not alter booking status rules.',
            'placeholder' => 'From 2:00 PM',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 10,
        ],
        'guest.checkout' => [
            'group' => 'guest',
            'label' => 'Displayed check-out time',
            'description' => 'Guest-facing departure guidance. This does not alter booking status rules.',
            'placeholder' => 'By 11:00 AM',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 20,
        ],
        'guest.reception' => [
            'group' => 'guest',
            'label' => 'Reception hours',
            'description' => 'Hours during which guests can expect direct assistance.',
            'placeholder' => 'Daily, 6:00 AM–10:00 PM',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 30,
        ],
        'guest.emergency' => [
            'group' => 'guest',
            'label' => 'Emergency contact',
            'description' => 'A guest-facing emergency telephone number.',
            'placeholder' => '+977 98XXXXXXXX',
            'control' => 'input',
            'input_type' => 'tel',
            'rows' => 1,
            'width' => 'half',
            'required' => false,
            'autocomplete' => 'tel',
            'order' => 40,
        ],
        'seo.title' => [
            'group' => 'seo',
            'label' => 'Default page title',
            'description' => 'Fallback title for public pages without a dedicated SEO title.',
            'placeholder' => 'Tara Guest House | Sauraha, Chitwan',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 10,
        ],
        'seo.description' => [
            'group' => 'seo',
            'label' => 'Default meta description',
            'description' => 'A concise search-result description, ideally around 140–160 characters.',
            'placeholder' => 'Comfortable rooms and warm local hospitality in Sauraha, near Chitwan National Park.',
            'control' => 'textarea',
            'input_type' => 'text',
            'rows' => 3,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 20,
        ],
        'footer.description' => [
            'group' => 'seo',
            'label' => 'Footer description',
            'description' => 'Short supporting text for the public footer.',
            'placeholder' => 'A peaceful retreat in the heart of Sauraha.',
            'control' => 'textarea',
            'input_type' => 'text',
            'rows' => 3,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 30,
        ],
        'footer.copyright' => [
            'group' => 'seo',
            'label' => 'Copyright text',
            'description' => 'Optional custom copyright wording for the public footer.',
            'placeholder' => 'Tara Guest House. All rights reserved.',
            'control' => 'input',
            'input_type' => 'text',
            'rows' => 1,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'off',
            'order' => 40,
        ],
        'notification.contact' => [
            'group' => 'notifications',
            'label' => 'Contact-message recipient',
            'description' => 'Private operational address for contact-message notifications.',
            'placeholder' => 'operations@example.com',
            'control' => 'input',
            'input_type' => 'email',
            'rows' => 1,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'email',
            'order' => 10,
        ],
        'notification.booking' => [
            'group' => 'notifications',
            'label' => 'Booking notification recipient',
            'description' => 'Private operational address for booking notifications.',
            'placeholder' => 'bookings@example.com',
            'control' => 'input',
            'input_type' => 'email',
            'rows' => 1,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'email',
            'order' => 20,
        ],
        'notification.review' => [
            'group' => 'notifications',
            'label' => 'Review notification recipient',
            'description' => 'Private operational address for review notifications.',
            'placeholder' => 'reviews@example.com',
            'control' => 'input',
            'input_type' => 'email',
            'rows' => 1,
            'width' => 'full',
            'required' => false,
            'autocomplete' => 'email',
            'order' => 30,
        ],
    ];

    /**
     * @param list<array<string, mixed>> $settings
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     fields: list<array<string, mixed>>
     * }>
     */
    public function grouped(array $settings): array
    {
        $groups = [];

        foreach ($settings as $setting) {
            if (!is_array($setting) || !isset($setting['key']) || !is_string($setting['key'])) {
                continue;
            }

            if (in_array($setting['key'], ['site.logo', 'site.logo.enabled'], true)) {
                continue;
            }

            $presentation = $this->field($setting['key'], (string) ($setting['valueType'] ?? 'string'));
            $groupKey = $presentation['group'];

            if (!isset($groups[$groupKey])) {
                $group = self::GROUPS[$groupKey] ?? self::GROUPS['advanced'];
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'title' => $group['title'],
                    'description' => $group['description'],
                    'order' => $group['order'],
                    'fields' => [],
                ];
            }

            $groups[$groupKey]['fields'][] = [...$setting, ...$presentation];
        }

        foreach ($groups as &$group) {
            usort(
                $group['fields'],
                static fn (array $left, array $right): int => ($left['order'] <=> $right['order'])
                    ?: strcmp((string) $left['key'], (string) $right['key']),
            );
        }
        unset($group);

        uasort(
            $groups,
            static fn (array $left, array $right): int => ($left['order'] <=> $right['order'])
                ?: strcmp($left['title'], $right['title']),
        );

        return array_values(array_map(
            static fn (array $group): array => [
                'key' => $group['key'],
                'title' => $group['title'],
                'description' => $group['description'],
                'fields' => $group['fields'],
            ],
            $groups,
        ));
    }

    /** @return array<string, mixed> */
    private function field(string $key, string $valueType): array
    {
        $field = self::FIELDS[$key] ?? null;

        if (is_array($field)) {
            return $field;
        }

        return [
            'group' => 'advanced',
            'label' => ucwords(str_replace(['.', '_', '-'], ' ', $key)),
            'description' => 'An additional server-approved setting.',
            'placeholder' => '',
            'control' => $valueType === 'json' ? 'textarea' : 'input',
            'input_type' => match ($valueType) {
                'email' => 'email',
                'url' => 'url',
                'integer' => 'number',
                default => 'text',
            },
            'rows' => $valueType === 'json' ? 5 : 1,
            'width' => 'full',
            'required' => true,
            'autocomplete' => 'off',
            'order' => 1000,
        ];
    }
}
