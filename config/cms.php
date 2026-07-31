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
];
