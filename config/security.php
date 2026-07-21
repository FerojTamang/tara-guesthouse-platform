<?php

declare(strict_types=1);

return [
    'administrator_bootstrap' => [
        'allowed_environments' => ['local', 'testing', 'staging', 'production'],
        'force_required_environments' => ['staging', 'production'],
        'full_name_min_characters' => 2,
        'full_name_max_characters' => 100,
        'username_min_characters' => 3,
        'username_max_characters' => 50,
        'email_max_bytes' => 150,
        'phone_max_characters' => 20,
        'password_min_characters' => 15,
        'password_max_bytes' => 72,
    ],
];
