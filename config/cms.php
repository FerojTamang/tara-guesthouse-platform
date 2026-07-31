<?php

declare(strict_types=1);

return [
    'settings' => [
        'definitions_environment_key' => 'CMS_SETTING_DEFINITIONS_JSON',
        'supported_value_types' => [
            'string',
            'integer',
            'boolean',
            'email',
            'url',
            'relative_path',
            'json',
        ],
    ],
    'page_content' => [
        'policy_environment_key' => 'CMS_PAGE_CONTENT_POLICY_JSON',
        'supported_formats' => [
            'plain_text',
        ],
    ],
];
