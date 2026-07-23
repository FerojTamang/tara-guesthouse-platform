<?php

declare(strict_types=1);

return [
    'room_images' => [
        'allowed_mime_extensions' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ],
        'maximum_file_size_bytes' => 5 * 1024 * 1024,
        'minimum_width_pixels' => 640,
        'minimum_height_pixels' => 480,
        'maximum_width_pixels' => 6000,
        'maximum_height_pixels' => 6000,
        'maximum_images_per_room' => 10,
        'filesystem_root' => 'public/uploads/rooms',
        'database_path_prefix' => 'uploads/rooms/',
    ],
];
